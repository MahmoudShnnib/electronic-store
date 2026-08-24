<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
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
        $revenueStatuses = [
            OrderStatus::Confirmed, OrderStatus::Processing, OrderStatus::ReadyForShipping,
            OrderStatus::Shipped, OrderStatus::Delivered, OrderStatus::Completed,
        ];

        $revenue = fn ($query) => (int) $query->whereIn('status', $revenueStatuses)->sum('total_cents');

        $lowStock = StockLevel::query()
            ->join('products', 'products.id', '=', 'stock_levels.product_id')
            ->leftJoin('product_variants', 'product_variants.id', '=', 'stock_levels.product_variant_id')
            ->whereRaw('stock_levels.quantity <= COALESCE(product_variants.low_stock_threshold, products.low_stock_threshold)')
            ->where('stock_levels.quantity', '>', 0)
            ->count();

        $outOfStock = StockLevel::query()->where('quantity', '<=', 0)->count();

        // Last 30 days of revenue for the chart.
        $revenueSeries = Order::query()
            ->whereIn('status', $revenueStatuses)
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->groupByRaw('DATE(created_at)')
            ->orderByRaw('DATE(created_at)')
            ->selectRaw('DATE(created_at) as day, SUM(total_cents) as revenue_cents, COUNT(*) as orders_count')
            ->get()
            ->map(fn ($row) => [
                'day' => $row->day,
                'revenue' => Money::toDecimal((int) $row->revenue_cents),
                'orders' => (int) $row->orders_count,
            ]);

        $bestSellers = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.status', array_map(fn ($s) => $s->value, $revenueStatuses))
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
                'orders_pending' => Order::query()->where('status', OrderStatus::Pending)->count(),
                'orders_completed' => Order::query()->where('status', OrderStatus::Completed)->count(),
                'orders_cancelled' => Order::query()->where('status', OrderStatus::Cancelled)->count(),
                'customers_total' => User::query()->role('customer')->count(),
                'products_total' => Product::query()->count(),
                'products_active' => Product::query()->where('status', ProductStatus::Active)->count(),
                'low_stock' => $lowStock,
                'out_of_stock' => $outOfStock,
            ],
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
            'recentActivity' => Activity::query()
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
