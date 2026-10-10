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
use App\Services\Reports\ReportService;
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
        // Same rule as the reports: revenue is money received — payments on the day
        // they're confirmed, minus refunds on the day they're given back
        // (ReportService::moneyEvents). Days are the shop's days (timestamps are UTC).
        $tz = (string) config('store.timezone');
        $revenue = fn ($from, $to) => (int) ReportService::moneyEvents($from, $to)->sum('ev.amount');

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
        $day = ReportService::localDayOf('ev.at');
        $revenueSeries = ReportService::moneyEvents(now($tz)->subDays(29)->startOfDay()->utc(), now())
            ->groupByRaw($day)
            ->orderByRaw($day)
            ->selectRaw("{$day} as day, SUM(ev.amount) as revenue_cents, COUNT(DISTINCT CASE WHEN ev.sign = 1 THEN ev.order_id END) as orders_count")
            ->get()
            ->map(fn ($row) => [
                'day' => $row->day,
                'revenue' => Money::toDecimal((int) $row->revenue_cents),
                'orders' => (int) $row->orders_count,
            ]);

        // Sold means paid and kept: orders the shop holds money for. Unpaid, cancelled
        // and refunded (always whole) orders hold nothing — the report's rule.
        $bestSellers = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNull('orders.deleted_at')
            ->whereRaw("COALESCE((SELECT SUM(amount_cents) FROM payments WHERE payments.order_id = orders.id AND payments.status = 'confirmed'), 0)"
                ." > COALESCE((SELECT SUM(amount_cents) FROM refunds WHERE refunds.order_id = orders.id AND refunds.status = 'completed'), 0)")
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
                'revenue_today' => Money::format($revenue(now($tz)->startOfDay()->utc(), now($tz)->endOfDay()->utc())),
                'revenue_month' => Money::format($revenue(now($tz)->startOfMonth()->utc(), now())),
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
