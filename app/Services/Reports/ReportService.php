<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Enums\MovementType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * All admin reporting queries. Revenue counts orders that were confirmed or
 * further along (not pending/cancelled/refunded); everything is aggregated in
 * SQL so reports stay fast on large datasets.
 */
final class ReportService
{
    /**
     * Everything except cancelled counts as revenue. Refunded orders stay in,
     * because money given back is subtracted per order (see NET_TOTAL): a small
     * refund must not wipe the whole sale out, and a full one nets to zero.
     */
    private const REVENUE_STATUSES = ['in_progress', 'completed', 'refunded'];

    /**
     * What an order is worth as revenue; needs the `rf` refunds join. An open order is
     * booked at its total — a refund on it only gives back a prepayment, the sale is
     * unchanged. A finished order is worth what the shop kept: payments minus refunds
     * (completing always collects the full total, so this is the total less whatever
     * went back afterwards). A cancelled order keeps the old figure (it is only shown
     * in the by-status table).
     */
    private const NET_TOTAL = "(CASE orders.status WHEN 'in_progress' THEN orders.total_cents"
        ." WHEN 'cancelled' THEN orders.total_cents - COALESCE(rf.refunded_cents, 0)"
        ." ELSE COALESCE((SELECT SUM(p.amount_cents) FROM payments p WHERE p.order_id = orders.id AND p.status = 'confirmed'), 0)"
        .' - COALESCE(rf.refunded_cents, 0) END)';

    /**
     * What is still owed on an order: exactly what completing it would collect
     * (UpdateOrderStatus::settleOutstanding — total minus payments net of refunds).
     * A cancelled or refunded order owes nothing.
     */
    private const OWED = "CASE WHEN orders.status IN ('in_progress', 'completed') THEN GREATEST(orders.total_cents"
        ." - COALESCE((SELECT SUM(p.amount_cents) FROM payments p WHERE p.order_id = orders.id AND p.status = 'confirmed'), 0)"
        ." + COALESCE((SELECT SUM(r.amount_cents) FROM refunds r WHERE r.order_id = orders.id AND r.status = 'completed'), 0), 0) ELSE 0 END";

    private function revenueOrders(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        $refunds = DB::table('refunds')
            ->where('status', 'completed')
            ->groupBy('order_id')
            ->selectRaw('order_id, SUM(amount_cents) AS refunded_cents');

        // Columns are qualified because salesByCustomer() joins users, which
        // shares column names (deleted_at, created_at) with orders.
        return DB::table('orders')
            ->leftJoinSub($refunds, 'rf', 'rf.order_id', '=', 'orders.id')
            ->whereIn('orders.status', self::REVENUE_STATUSES)
            ->whereNull('orders.deleted_at')
            ->whereBetween('orders.created_at', [$from, $to]);
    }

    private function revenueItems(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        // Per-product lines can't be netted against an order-level refund, so
        // refunded orders stay out of the product/category/brand breakdowns.
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.status', ['in_progress', 'completed'])
            ->whereNull('orders.deleted_at')
            ->whereBetween('orders.created_at', [$from, $to]);
    }

    public function salesSummary(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $row = $this->revenueOrders($from, $to)
            ->selectRaw('COUNT(*) as orders, COALESCE(SUM('.self::NET_TOTAL.'),0) as revenue, COALESCE(SUM(orders.discount_cents),0) as discounts, COALESCE(AVG('.self::NET_TOTAL.'),0) as avg_order')
            ->first();

        // Cost of what the customer kept: every order counted as revenue above
        // (refunded ones included, since their kept money is), minus the units
        // that came back on a return. Otherwise a partly refunded sale adds its
        // revenue but none of its cost, and profit is overstated.
        $returned = DB::table('inventory_movements')
            ->where('type', MovementType::CustomerReturn->value)
            ->where('reference_type', (new Order)->getMorphClass())
            ->whereColumn('reference_id', 'orders.id')
            ->whereColumn('inventory_movements.product_id', 'order_items.product_id')
            ->whereRaw('inventory_movements.product_variant_id IS NOT DISTINCT FROM order_items.product_variant_id')
            ->whereColumn('inventory_movements.warehouse_id', 'order_items.warehouse_id')
            ->selectRaw('COALESCE(SUM(quantity_change), 0)');

        $cost = (int) DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.status', self::REVENUE_STATUSES)
            ->whereNull('orders.deleted_at')
            ->whereBetween('orders.created_at', [$from, $to])
            ->leftJoin('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            // The cost recorded at sale time; older lines without one use today's cost.
            ->selectRaw('COALESCE(SUM(GREATEST(order_items.quantity - ('.$returned->toSql().'), 0) * COALESCE(order_items.unit_cost_cents, NULLIF(product_variants.cost_price_cents, 0), products.cost_price_cents, 0)),0) as cost', $returned->getBindings())
            ->value('cost');

        $refunded = (int) DB::table('refunds')->where('status', 'completed')->whereBetween('created_at', [$from, $to])->sum('amount_cents');
        // Customers only — staff accounts are not new customers.
        $newCustomers = User::query()->role('customer')->whereBetween('created_at', [$from, $to])->count();

        return [
            'orders' => (int) $row->orders,
            'revenue_formatted' => Money::format((int) $row->revenue),
            'discounts_formatted' => Money::format((int) $row->discounts),
            'avg_order_formatted' => Money::format((int) round((float) $row->avg_order)),
            'estimated_cost_formatted' => Money::format($cost),
            // A loss shows as a loss, not as zero.
            'estimated_profit_formatted' => Money::format((int) $row->revenue - $cost),
            'refunded_formatted' => Money::format($refunded),
            'new_customers' => $newCustomers,
        ];
    }

    public function salesByDay(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return $this->revenueOrders($from, $to)
            ->groupByRaw(self::localDay())
            ->orderByRaw(self::localDay())
            ->selectRaw(self::localDay().' as day, COUNT(*) as orders, SUM('.self::NET_TOTAL.') as revenue_cents')
            ->get()
            ->map(fn ($r) => [
                'day' => $r->day,
                'orders' => (int) $r->orders,
                'revenue' => Money::toDecimal((int) $r->revenue_cents),
            ]);
    }

    public function salesByProduct(CarbonImmutable $from, CarbonImmutable $to, ?int $limit = null): Collection
    {
        return $this->revenueItems($from, $to)
            ->groupBy('order_items.product_id', 'order_items.product_name', 'order_items.sku')
            ->orderByDesc(DB::raw('SUM(order_items.line_total_cents)'))
            ->when($limit !== null, fn ($q) => $q->limit($limit))
            ->selectRaw('order_items.product_id, order_items.product_name, order_items.sku, SUM(order_items.quantity) as units, SUM(order_items.line_total_cents) as revenue_cents')
            ->get()
            ->map(fn ($r) => [
                'product_id' => $r->product_id,
                'name' => $r->product_name,
                'sku' => $r->sku,
                'units' => (int) $r->units,
                'revenue_formatted' => Money::format((int) $r->revenue_cents),
            ]);
    }

    public function salesByCategory(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return $this->revenueItems($from, $to)
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc(DB::raw('SUM(order_items.line_total_cents)'))
            ->selectRaw('categories.name, SUM(order_items.quantity) as units, SUM(order_items.line_total_cents) as revenue_cents')
            ->get()
            ->map(fn ($r) => [
                'name' => $r->name,
                'units' => (int) $r->units,
                'revenue_formatted' => Money::format((int) $r->revenue_cents),
                'revenue' => Money::toDecimal((int) $r->revenue_cents),
            ]);
    }

    public function salesByBrand(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return $this->revenueItems($from, $to)
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->join('brands', 'brands.id', '=', 'products.brand_id')
            ->groupBy('brands.id', 'brands.name')
            ->orderByDesc(DB::raw('SUM(order_items.line_total_cents)'))
            ->selectRaw('brands.name, SUM(order_items.quantity) as units, SUM(order_items.line_total_cents) as revenue_cents')
            ->get()
            ->map(fn ($r) => [
                'name' => $r->name,
                'units' => (int) $r->units,
                'revenue_formatted' => Money::format((int) $r->revenue_cents),
                'revenue' => Money::toDecimal((int) $r->revenue_cents),
            ]);
    }

    public function salesByPaymentMethod(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return $this->revenueOrders($from, $to)
            ->groupBy('payment_method')
            ->selectRaw('orders.payment_method, COUNT(*) as orders, SUM('.self::NET_TOTAL.') as revenue_cents')
            ->get()
            ->map(fn ($r) => [
                'method' => $r->payment_method,
                'label' => PaymentMethod::tryFrom((string) $r->payment_method)?->label() ?? $r->payment_method,
                'orders' => (int) $r->orders,
                'revenue_formatted' => Money::format((int) $r->revenue_cents),
            ]);
    }

    public function salesByStatus(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        // Net of refunds like every other figure on the page, so a partly refunded
        // order doesn't show here at its full total.
        return DB::table('orders')
            ->leftJoinSub(
                DB::table('refunds')->where('status', 'completed')->groupBy('order_id')->selectRaw('order_id, SUM(amount_cents) AS refunded_cents'),
                'rf', 'rf.order_id', '=', 'orders.id',
            )
            ->whereNull('orders.deleted_at')
            ->whereBetween('orders.created_at', [$from, $to])
            ->groupBy('orders.status')
            ->selectRaw('orders.status, COUNT(*) as orders, SUM('.self::NET_TOTAL.') as revenue_cents')
            ->get()
            ->map(fn ($r) => [
                'status' => $r->status,
                'label' => OrderStatus::tryFrom((string) $r->status)?->label() ?? $r->status,
                'orders' => (int) $r->orders,
                'revenue_formatted' => Money::format((int) $r->revenue_cents),
            ]);
    }

    public function salesByCustomer(CarbonImmutable $from, CarbonImmutable $to, ?int $limit = null): Collection
    {
        return $this->revenueOrders($from, $to)
            ->whereNotNull('orders.user_id')
            ->join('users', 'users.id', '=', 'orders.user_id')
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc(DB::raw('SUM('.self::NET_TOTAL.')'))
            ->when($limit !== null, fn ($q) => $q->limit($limit))
            ->selectRaw('users.id, users.name, users.email, COUNT(*) as orders, SUM('.self::NET_TOTAL.') as revenue_cents')
            ->get()
            ->map(fn ($r) => [
                'customer_id' => $r->id,
                'name' => $r->name,
                'email' => $r->email,
                'orders' => (int) $r->orders,
                'revenue_formatted' => Money::format((int) $r->revenue_cents),
            ]);
    }

    /** Totals for the invoice report — computed without the row limit so the
     *  headline numbers stay correct even when the table is truncated. */
    /** The order's calendar day in the shop's timezone (timestamps are stored in UTC). */
    private static function localDay(): string
    {
        $tz = preg_replace('/[^A-Za-z_\/+-]/', '', (string) config('store.timezone'));

        return "DATE((orders.created_at AT TIME ZONE 'UTC') AT TIME ZONE '{$tz}')";
    }

    public function invoiceSummary(CarbonImmutable $from, CarbonImmutable $to): array
    {
        // A cancelled order was never owed.
        $orders = DB::table('orders')
            ->whereNull('deleted_at')
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('COUNT(*) as invoices, COALESCE(SUM(total_cents),0) as invoiced, COALESCE(SUM('.self::OWED.'),0) as owed')
            ->first();

        $paid = (int) DB::table('payments')
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->where('payments.status', 'confirmed')
            ->whereNull('orders.deleted_at')
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->whereBetween('orders.created_at', [$from, $to])
            ->sum('payments.amount_cents');

        return [
            'invoices' => (int) $orders->invoices,
            'invoiced_formatted' => Money::format((int) $orders->invoiced),
            'paid_formatted' => Money::format($paid),
            'outstanding_formatted' => Money::format((int) $orders->owed),
        ];
    }

    /** One row per order: what was invoiced, what has been collected, and
     *  what is still owed. Paid = confirmed payments only. */
    public function invoices(CarbonImmutable $from, CarbonImmutable $to, int $limit = 250): Collection
    {
        return DB::table('orders')
            ->leftJoin('payments', function ($join): void {
                $join->on('payments.order_id', '=', 'orders.id')->where('payments.status', 'confirmed');
            })
            ->whereNull('orders.deleted_at')
            ->whereBetween('orders.created_at', [$from, $to])
            ->groupBy('orders.id')
            ->orderByDesc('orders.created_at')
            ->limit($limit)
            ->selectRaw('orders.id, orders.number, orders.customer, orders.status, orders.payment_status, orders.total_cents, orders.created_at, COALESCE(SUM(payments.amount_cents),0) as paid_cents, '.self::OWED.' as owed_cents')
            ->get()
            ->map(function ($r): array {
                $customer = json_decode((string) $r->customer, true) ?: [];
                $paid = (int) $r->paid_cents;
                $outstanding = (int) $r->owed_cents;

                return [
                    'order_id' => (int) $r->id,
                    'number' => $r->number,
                    'date' => CarbonImmutable::parse($r->created_at)->toDateString(),
                    'customer' => $customer['name'] ?? '—',
                    'phone' => $customer['phone'] ?? '',
                    'status' => $r->status,
                    'status_label' => OrderStatus::from($r->status)->label(),
                    'payment_status' => $r->payment_status,
                    'payment_status_label' => PaymentStatus::from($r->payment_status)->label(),
                    'total_formatted' => Money::format((int) $r->total_cents),
                    'paid_formatted' => Money::format($paid),
                    'outstanding_cents' => $outstanding,
                    'outstanding_formatted' => Money::format($outstanding),
                ];
            });
    }

    public function inventoryValuation(): array
    {
        // A variant saved without its own cost stores 0 and falls back to the product's
        // cost — the same rule the sale-time cost snapshot and the profit report use.
        $rows = DB::table('stock_levels')
            ->leftJoin('product_variants', 'product_variants.id', '=', 'stock_levels.product_variant_id')
            ->join('products', 'products.id', '=', 'stock_levels.product_id')
            ->selectRaw('
                SUM((stock_levels.quantity + stock_levels.reserved) * COALESCE(NULLIF(product_variants.cost_price_cents, 0), products.cost_price_cents, 0)) as cost_value,
                SUM((stock_levels.quantity + stock_levels.reserved) * COALESCE(product_variants.price_cents, products.price_cents, 0)) as retail_value,
                SUM(stock_levels.quantity + stock_levels.reserved) as units
            ')
            ->first();

        return [
            'units' => (int) ($rows->units ?? 0),
            'cost_value_formatted' => Money::format((int) ($rows->cost_value ?? 0)),
            'retail_value_formatted' => Money::format((int) ($rows->retail_value ?? 0)),
        ];
    }

    public function lowStockProducts(int $limit = 50): Collection
    {
        return DB::table('stock_levels')
            ->join('products', 'products.id', '=', 'stock_levels.product_id')
            ->leftJoin('product_variants', 'product_variants.id', '=', 'stock_levels.product_variant_id')
            ->join('warehouses', 'warehouses.id', '=', 'stock_levels.warehouse_id')
            ->whereRaw('stock_levels.quantity <= COALESCE(product_variants.low_stock_threshold, products.low_stock_threshold)')
            ->where('stock_levels.quantity', '>', 0)
            ->orderBy('stock_levels.quantity')
            ->limit($limit)
            ->selectRaw("products.name, COALESCE(product_variants.name, '') as variant_name, COALESCE(product_variants.sku, products.sku) as sku, warehouses.name as warehouse, stock_levels.quantity, COALESCE(product_variants.low_stock_threshold, products.low_stock_threshold) as threshold")
            ->get();
    }

    public function outOfStockProducts(int $limit = 50): Collection
    {
        return DB::table('stock_levels')
            ->join('products', 'products.id', '=', 'stock_levels.product_id')
            ->leftJoin('product_variants', 'product_variants.id', '=', 'stock_levels.product_variant_id')
            ->join('warehouses', 'warehouses.id', '=', 'stock_levels.warehouse_id')
            ->where('stock_levels.quantity', '<=', 0)
            ->limit($limit)
            ->selectRaw("products.name, COALESCE(product_variants.name, '') as variant_name, COALESCE(product_variants.sku, products.sku) as sku, warehouses.name as warehouse, stock_levels.quantity")
            ->get();
    }

    public function exportCsv(string $report, CarbonImmutable $from, CarbonImmutable $to): StreamedResponse
    {
        [$header, $rows] = match ($report) {
            'sales_by_day' => [
                ['Day', 'Orders', 'Revenue'],
                $this->salesByDay($from, $to)->map(fn ($r) => [$r['day'], $r['orders'], $r['revenue']]),
            ],
            'sales_by_product' => [
                ['Product', 'SKU', 'Units', 'Revenue'],
                $this->salesByProduct($from, $to, 10000)->map(fn ($r) => [$r['name'], $r['sku'], $r['units'], $r['revenue_formatted']]),
            ],
            'sales_by_category' => [
                ['Category', 'Units', 'Revenue'],
                $this->salesByCategory($from, $to)->map(fn ($r) => [$r['name'], $r['units'], $r['revenue_formatted']]),
            ],
            'sales_by_brand' => [
                ['Brand', 'Units', 'Revenue'],
                $this->salesByBrand($from, $to)->map(fn ($r) => [$r['name'], $r['units'], $r['revenue_formatted']]),
            ],
            'sales_by_customer' => [
                ['Customer', 'Email', 'Orders', 'Revenue'],
                $this->salesByCustomer($from, $to, 10000)->map(fn ($r) => [$r['name'], $r['email'], $r['orders'], $r['revenue_formatted']]),
            ],
            'invoices' => [
                ['Invoice', 'Date', 'Customer', 'Phone', 'Status', 'Payment status', 'Total', 'Paid', 'Outstanding'],
                $this->invoices($from, $to, 100000)->map(fn ($r) => [
                    $r['number'], $r['date'], $r['customer'], $r['phone'], $r['status'],
                    $r['payment_status'], $r['total_formatted'], $r['paid_formatted'], $r['outstanding_formatted'],
                ]),
            ],
            'inventory_valuation' => [
                ['Units', 'Cost value', 'Retail value'],
                collect([$this->inventoryValuation()])->map(fn ($r) => [$r['units'], $r['cost_value_formatted'], $r['retail_value_formatted']]),
            ],
            'low_stock' => [
                ['Product', 'Variant', 'SKU', 'Warehouse', 'Quantity', 'Threshold'],
                $this->lowStockProducts(10000)->map(fn ($r) => [$r->name, $r->variant_name, $r->sku, $r->warehouse, $r->quantity, $r->threshold]),
            ],
            'movements' => [
                ['Date', 'Product', 'Warehouse', 'Type', 'Before', 'Change', 'After', 'Reason'],
                DB::table('inventory_movements')
                    ->join('products', 'products.id', '=', 'inventory_movements.product_id')
                    ->join('warehouses', 'warehouses.id', '=', 'inventory_movements.warehouse_id')
                    ->whereBetween('inventory_movements.created_at', [$from, $to])
                    ->orderBy('inventory_movements.created_at')
                    ->selectRaw('inventory_movements.created_at, products.name, warehouses.name as warehouse, inventory_movements.type, quantity_before, quantity_change, quantity_after, reason')
                    ->get()
                    ->map(fn ($r) => [$r->created_at, $r->name, $r->warehouse, $r->type, $r->quantity_before, $r->quantity_change, $r->quantity_after, $r->reason]),
            ],
            'refunds' => [
                ['Order', 'Amount', 'Method', 'Reference', 'Date'],
                DB::table('refunds')
                    ->join('orders', 'orders.id', '=', 'refunds.order_id')
                    ->whereBetween('refunds.created_at', [$from, $to])
                    ->selectRaw('orders.number, refunds.amount_cents, refunds.method, refunds.reference, refunds.created_at')
                    ->get()
                    ->map(fn ($r) => [$r->number, Money::format((int) $r->amount_cents), $r->method, $r->reference, $r->created_at]),
            ],
        };

        $filename = sprintf('%s_%s_%s.csv', $report, $from->toDateString(), $to->toDateString());

        return response()->streamDownload(function () use ($header, $rows): void {
            $out = fopen('php://output', 'w');
            // UTF-8 BOM so Excel opens Arabic text correctly.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header);

            foreach ($rows as $row) {
                fputcsv($out, (array) $row);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
