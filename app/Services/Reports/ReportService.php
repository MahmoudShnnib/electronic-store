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
 * All admin reporting queries, aggregated in SQL so reports stay fast on large
 * datasets.
 *
 * Revenue is money received (owner decision 2026-10-10): a payment counts on the
 * day it is confirmed, a refund subtracts on the day it is given back. An unpaid
 * order is not revenue yet; for cash on delivery the money — and so the revenue —
 * arrives when the order is completed.
 */
final class ReportService
{
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

    /**
     * Every money movement on an order in the range, as rows of `ev`: order_id,
     * amount (+payment / −refund), at (when it happened) and sign (+1 / −1).
     * Payments are always the whole order and refunds always everything held
     * (2026-10-10), so a payment books the order and a refund un-books it.
     */
    public static function moneyEvents(CarbonImmutable|\DateTimeInterface $from, CarbonImmutable|\DateTimeInterface $to): Builder
    {
        $payments = DB::table('payments')
            ->where('status', 'confirmed')
            ->whereBetween('confirmed_at', [$from, $to])
            ->selectRaw('order_id, amount_cents AS amount, confirmed_at AS at, 1 AS sign');
        $refunds = DB::table('refunds')
            ->where('status', 'completed')
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('order_id, -amount_cents AS amount, created_at AS at, -1 AS sign');

        return DB::query()
            ->fromSub($payments->unionAll($refunds), 'ev')
            ->join('orders', 'orders.id', '=', 'ev.order_id')
            ->whereNull('orders.deleted_at');
    }

    /** Orders paid in the range (a refund doesn't make an order "unpaid" twice). */
    private const PAID_ORDERS = 'COUNT(DISTINCT CASE WHEN ev.sign = 1 THEN ev.order_id END)';

    private function revenueOrders(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return self::moneyEvents($from, $to);
    }

    /** Order lines booked by a payment (+) or un-booked by a refund (−) in the range. */
    private function revenueItems(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return self::moneyEvents($from, $to)
            ->join('order_items', 'order_items.order_id', '=', 'orders.id');
    }

    /** Signed units and line totals of revenueItems(); rows that net to nothing are dropped. */
    private const ITEM_UNITS = 'SUM(ev.sign * order_items.quantity)';

    private const ITEM_REVENUE = 'SUM(ev.sign * order_items.line_total_cents)';

    public function salesSummary(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $row = $this->revenueOrders($from, $to)
            ->selectRaw('COALESCE(SUM(ev.amount),0) as revenue, '.self::PAID_ORDERS.' as orders, COALESCE(SUM(ev.sign * orders.discount_cents),0) as discounts, COALESCE(SUM(ev.sign * orders.manual_discount_cents),0) as manual_discounts')
            ->first();

        // Cost follows the money: the goods of an order paid in the range cost what the
        // units taken from stock cost (FIFO, oldest first); a refund (always with every
        // unit back) takes it out.
        $cost = (int) $this->revenueItems($from, $to)
            ->selectRaw('COALESCE(SUM(ev.sign * order_items.cost_cents),0) as cost')
            ->value('cost');
        // Goods given away — an order that came to 0 (a full coupon or discount) — bring
        // in no money, so no payment books their cost: it counts the day they're handed over.
        $cost += (int) DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNull('orders.deleted_at')
            ->where('orders.status', 'completed')
            ->where('orders.total_cents', 0)
            ->whereBetween('orders.completed_at', [$from, $to])
            ->sum('order_items.cost_cents');

        $refunded = (int) DB::table('refunds')->where('status', 'completed')->whereBetween('created_at', [$from, $to])->sum('amount_cents');
        // Customers only — staff accounts are not new customers.
        $newCustomers = User::query()->role('customer')->whereBetween('created_at', [$from, $to])->count();

        return [
            'orders' => (int) $row->orders,
            'revenue_formatted' => Money::format((int) $row->revenue),
            'discounts_formatted' => Money::format((int) $row->discounts),
            // The part of the discounts a cashier keyed in by hand (the rest came from coupons).
            'manual_discounts_formatted' => Money::format((int) $row->manual_discounts),
            'avg_order_formatted' => Money::format((int) $row->orders > 0 ? intdiv((int) $row->revenue, (int) $row->orders) : 0),
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
            ->groupByRaw(self::localDayOf('ev.at'))
            ->orderByRaw(self::localDayOf('ev.at'))
            ->selectRaw(self::localDayOf('ev.at').' as day, '.self::PAID_ORDERS.' as orders, SUM(ev.amount) as revenue_cents')
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
            ->havingRaw(self::ITEM_UNITS.' <> 0 OR '.self::ITEM_REVENUE.' <> 0')
            ->orderByDesc(DB::raw(self::ITEM_REVENUE))
            ->when($limit !== null, fn ($q) => $q->limit($limit))
            ->selectRaw('order_items.product_id, order_items.product_name, order_items.sku, '.self::ITEM_UNITS.' as units, '.self::ITEM_REVENUE.' as revenue_cents')
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
            ->havingRaw(self::ITEM_UNITS.' <> 0 OR '.self::ITEM_REVENUE.' <> 0')
            ->orderByDesc(DB::raw(self::ITEM_REVENUE))
            ->selectRaw('categories.name, '.self::ITEM_UNITS.' as units, '.self::ITEM_REVENUE.' as revenue_cents')
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
        // Products without a brand are allowed; they are kept as one "No brand"
        // row so the table still adds up to the revenue.
        return $this->revenueItems($from, $to)
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('brands', 'brands.id', '=', 'products.brand_id')
            ->groupBy('brands.id', 'brands.name')
            ->havingRaw(self::ITEM_UNITS.' <> 0 OR '.self::ITEM_REVENUE.' <> 0')
            ->orderByDesc(DB::raw(self::ITEM_REVENUE))
            ->selectRaw('brands.name, '.self::ITEM_UNITS.' as units, '.self::ITEM_REVENUE.' as revenue_cents')
            ->get()
            ->map(fn ($r) => [
                'name' => $r->name ?? __('No brand'),
                'units' => (int) $r->units,
                'revenue_formatted' => Money::format((int) $r->revenue_cents),
                'revenue' => Money::toDecimal((int) $r->revenue_cents),
            ]);
    }

    public function salesByPaymentMethod(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return $this->revenueOrders($from, $to)
            ->groupBy('orders.payment_method')
            ->selectRaw('orders.payment_method, '.self::PAID_ORDERS.' as orders, SUM(ev.amount) as revenue_cents')
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
            ->orderByDesc(DB::raw('SUM(ev.amount)'))
            ->when($limit !== null, fn ($q) => $q->limit($limit))
            ->selectRaw('users.id, users.name, users.email, '.self::PAID_ORDERS.' as orders, SUM(ev.amount) as revenue_cents')
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
    public static function localDay(): string
    {
        return self::localDayOf('orders.created_at');
    }

    /** A UTC timestamp column's calendar day in the shop's timezone. */
    public static function localDayOf(string $column): string
    {
        $tz = preg_replace('/[^A-Za-z_\/+-]/', '', (string) config('store.timezone'));

        return "DATE(({$column} AT TIME ZONE 'UTC') AT TIME ZONE '{$tz}')";
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
                    // The shop's day, like the range it was filtered by.
                    'date' => CarbonImmutable::parse($r->created_at, 'UTC')->setTimezone((string) config('store.timezone'))->toDateString(),
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

    /**
     * Stock lost in the range — damaged, lost, decreased by hand, or the net of the
     * count corrections — at what it cost (FIFO). Not part of the estimated profit,
     * which only covers goods sold.
     *
     * Count corrections are netted: a phone missing at one count and found at the
     * next is no loss. Goods sent back to a supplier are listed apart and left out
     * of the total — the supplier owes their value back, so they are not a loss.
     *
     * @return array{total_formatted: string, rows: list<array{type: string, label: string, units: int, cost_formatted: string}>, supplier_returns: array{units: int, cost_formatted: string}}
     */
    public function writeOffs(CarbonImmutable $from, CarbonImmutable $to): array
    {
        // cost_cents is what the moved units cost, always positive; the sign of
        // quantity_change says which way they went.
        $rows = DB::table('inventory_movements')
            ->whereBetween('created_at', [$from, $to])
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->whereIn('type', ['damaged', 'lost', 'manual_decrease', 'supplier_return'])->where('quantity_change', '<', 0))
                ->orWhere(fn ($q) => $q->where('type', 'correction')->where('quantity_change', '<>', 0)))
            ->groupBy('type')
            ->orderBy('type')
            ->selectRaw('type, -SUM(quantity_change) AS units, COALESCE(SUM(-SIGN(quantity_change) * cost_cents), 0) AS cost')
            ->get()
            ->keyBy('type');

        $returns = $rows->pull('supplier_return');
        // A count that nets to nothing either way is not worth a line.
        $losses = $rows->reject(fn ($row) => (int) $row->units === 0 && (int) $row->cost === 0);

        return [
            'total_formatted' => Money::format((int) $losses->sum('cost')),
            'rows' => $losses->map(fn ($row) => [
                'type' => $row->type,
                'label' => $row->type === 'correction' ? __('Count differences (net)') : MovementType::from($row->type)->label(),
                'units' => (int) $row->units,
                'cost_formatted' => Money::format((int) $row->cost),
            ])->values()->all(),
            'supplier_returns' => [
                'units' => (int) ($returns->units ?? 0),
                'cost_formatted' => Money::format((int) ($returns->cost ?? 0)),
            ],
        ];
    }

    public function inventoryValuation(): array
    {
        // The goods the shop owns, at what they cost (FIFO batches): units on the
        // shelf, plus units held for open orders not paid yet. Once an order is paid
        // its goods count as sold (their cost is in the sales report), so they leave
        // here even before delivery. One statement, so a checkout landing mid-read
        // can't count the same units twice or not at all.
        $rows = DB::selectOne("
            SELECT SUM(units) AS units, SUM(cost) AS cost_value, SUM(units * price) AS retail_value FROM (
                SELECT stock_batches.quantity_remaining AS units,
                       stock_batches.quantity_remaining * stock_batches.unit_cost_cents AS cost,
                       COALESCE(product_variants.price_cents, products.price_cents, 0) AS price
                FROM stock_batches
                JOIN stock_levels ON stock_levels.id = stock_batches.stock_level_id
                JOIN products ON products.id = stock_levels.product_id
                LEFT JOIN product_variants ON product_variants.id = stock_levels.product_variant_id
                WHERE stock_batches.quantity_remaining > 0
                UNION ALL
                SELECT order_items.quantity, order_items.cost_cents,
                       COALESCE(product_variants.price_cents, products.price_cents, 0)
                FROM order_items
                JOIN orders ON orders.id = order_items.order_id
                LEFT JOIN products ON products.id = order_items.product_id
                LEFT JOIN product_variants ON product_variants.id = order_items.product_variant_id
                WHERE orders.status = 'in_progress' AND orders.deleted_at IS NULL
                  AND COALESCE((SELECT SUM(amount_cents) FROM payments WHERE payments.order_id = orders.id AND payments.status = 'confirmed'), 0)
                    <= COALESCE((SELECT SUM(amount_cents) FROM refunds WHERE refunds.order_id = orders.id AND refunds.status = 'completed'), 0)
            ) owned
        ");

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

        $dates = ReportDateRange::localDates($from, $to);
        $filename = sprintf('%s_%s_%s.csv', $report, $dates['from'], $dates['to']);

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
