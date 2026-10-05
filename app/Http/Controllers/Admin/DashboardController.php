<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\User;
use App\Support\Dates;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

final class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        // Same rule as ReportService::NET_TOTAL: everything except cancelled is revenue;
        // an open order counts at its total (a refund on it only returns a prepayment),
        // a finished one at what the shop kept — payments minus refunds.
        $revenueStatuses = [OrderStatus::InProgress, OrderStatus::Completed, OrderStatus::Refunded];
        $netTotal = "(CASE WHEN status = 'in_progress' THEN total_cents ELSE"
            ." COALESCE((SELECT SUM(amount_cents) FROM payments WHERE payments.order_id = orders.id AND payments.status = 'confirmed'), 0)"
            ." - COALESCE((SELECT SUM(amount_cents) FROM refunds WHERE refunds.order_id = orders.id AND refunds.status = 'completed'), 0) END)";

        $revenue = fn ($query) => (int) $query->whereIn('status', $revenueStatuses)->sum(DB::raw($netTotal));

        $lowStock = StockLevel::query()
            ->join('products', 'products.id', '=', 'stock_levels.product_id')
            ->leftJoin('product_variants', 'product_variants.id', '=', 'stock_levels.product_variant_id')
            ->whereRaw('stock_levels.quantity <= COALESCE(product_variants.low_stock_threshold, products.low_stock_threshold)')
            ->where('stock_levels.quantity', '>', 0)
            ->count();

        // Products on sale that a customer cannot buy anywhere right now — counted
        // per product, from the same sellable stock the storefront reads.
        $outOfStock = Product::query()
            ->whereIn('status', [ProductStatus::Active, ProductStatus::OutOfStock])
            ->whereDoesntHave('stockLevels', fn ($q) => $q->sellable()->where('quantity', '>', 0))
            ->count();

        // Last 30 days of revenue for the chart.
        $revenueSeries = Order::query()
            ->whereIn('status', $revenueStatuses)
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->groupByRaw('DATE(created_at)')
            ->orderByRaw('DATE(created_at)')
            ->selectRaw("DATE(created_at) as day, SUM({$netTotal}) as revenue_cents, COUNT(*) as orders_count")
            ->get()
            ->map(fn ($row) => [
                'day' => $row->day,
                'revenue' => Money::toDecimal((int) $row->revenue_cents),
                'orders' => (int) $row->orders_count,
            ]);

        // Per-product lines can't be netted against an order-level refund, so refunded
        // orders stay out — the same rule as the report's product breakdown.
        $bestSellers = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.status', [OrderStatus::InProgress->value, OrderStatus::Completed->value])
            ->whereNull('orders.deleted_at')
            ->groupBy('order_items.product_id', 'order_items.product_name')
            ->orderByDesc(DB::raw('SUM(order_items.quantity)'))
            ->limit(5)
            ->selectRaw('order_items.product_id, order_items.product_name, SUM(order_items.quantity) as sold, SUM(order_items.line_total_cents) as revenue_cents')
            ->get()
            ->map(fn ($row) => [
                'product_id' => $row->product_id,
                'name' => $row->product_name,
                'sold' => (int) $row->sold,
                'revenue_formatted' => Money::format((int) $row->revenue_cents),
            ]);

        return Inertia::render('admin/Dashboard', [
            'kpis' => [
                'revenue_today' => Money::format($revenue(Order::query()->whereDate('created_at', today()))),
                'revenue_month' => Money::format($revenue(Order::query()->whereBetween('created_at', [now()->startOfMonth(), now()]))),
                'orders_total' => Order::query()->count(),
                'orders_in_progress' => Order::query()->where('status', OrderStatus::InProgress)->count(),
                'orders_completed' => Order::query()->where('status', OrderStatus::Completed)->count(),
                'orders_cancelled' => Order::query()->where('status', OrderStatus::Cancelled)->count(),
                'orders_refunded' => Order::query()->where('status', OrderStatus::Refunded)->count(),
                'customers_total' => User::query()->role('customer')->count(),
                'products_total' => Product::query()->count(),
                'products_active' => Product::query()->where('status', ProductStatus::Active)->count(),
                'low_stock' => $lowStock,
                'out_of_stock' => $outOfStock,
            ],
            // Cash-on-delivery orders still open and unpaid after 3 days: not delivered
            // and no cash collected yet. Never cancelled automatically — just flagged.
            'staleCodOrders' => Order::query()
                ->where('status', OrderStatus::InProgress)
                ->where('payment_method', PaymentMethod::CashOnDelivery)
                ->where('payment_status', PaymentStatus::Unpaid)
                ->where('created_at', '<=', now()->subDays(3))
                ->oldest()
                ->take(10)
                ->get()
                ->map(fn (Order $o) => [
                    'id' => $o->id,
                    'number' => $o->number,
                    'customer' => $o->customer['name'] ?? '—',
                    'days' => (int) $o->created_at->diffInDays(now()),
                    'total_formatted' => Money::format($o->total_cents, $o->currency),
                ]),
            'revenueSeries' => $revenueSeries,
            'bestSellers' => $bestSellers,
            'recentOrders' => Order::query()
                ->latest()
                ->take(8)
                ->get()
                ->map(fn (Order $o) => [
                    'id' => $o->id,
                    'number' => $o->number,
                    'customer' => $o->customer['name'] ?? '—',
                    'status' => $o->status->value,
                    'status_label' => $o->status->label(),
                    'total_formatted' => Money::format($o->total_cents, $o->currency),
                    'created_at' => Dates::relative($o->created_at),
                ]),
            // The audit trail names staff and customers: only for those allowed to read it.
            'recentActivity' => ! request()->user()?->can('audit.view') ? [] : Activity::query()
                ->with('causer')
                ->latest()
                ->take(10)
                ->get()
                ->map(fn (Activity $a) => [
                    'id' => $a->id,
                    'description' => $a->description,
                    'causer' => $a->causer?->name ?? __('admin.system'),
                    'created_at' => Dates::relative($a->created_at),
                ]),
        ]);
    }
}
