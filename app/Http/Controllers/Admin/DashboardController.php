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
use App\Models\User;
use App\Services\Admin\AdminAttention;
use App\Services\Reports\ReportService;
use App\Support\Dates;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

final class DashboardController extends Controller
{
    public function __invoke(Request $request, AdminAttention $attention): Response
    {
        // Every block is shown only to staff allowed to open what it summarises:
        // a cashier has no business seeing monthly revenue, and an inventory
        // manager gets a 403 from every order link anyway.
        $user = $request->user();
        $can = [
            'reports' => $user->can('reports.view'),
            'orders' => $user->can('orders.view'),
            'products' => $user->can('products.view'),
            'customers' => $user->can('customers.view'),
            'audit' => $user->can('audit.view'),
        ];

        // Same rule as the reports: revenue is money received — payments on the day
        // they're confirmed, minus refunds on the day they're given back
        // (ReportService::moneyEvents). Days are the shop's days (timestamps are UTC).
        $tz = (string) config('store.timezone');
        $revenue = fn ($from, $to) => (int) ReportService::moneyEvents($from, $to)->sum('ev.amount');

        return Inertia::render('admin/Dashboard', [
            'can' => $can,
            'kpis' => [
                'revenue_today' => $can['reports'] ? Money::format($revenue(now($tz)->startOfDay()->utc(), now($tz)->endOfDay()->utc())) : null,
                'revenue_month' => $can['reports'] ? Money::format($revenue(now($tz)->startOfMonth()->utc(), now())) : null,
                'orders_total' => $can['orders'] ? Order::query()->count() : null,
                'orders_in_progress' => $can['orders'] ? Order::query()->where('status', OrderStatus::InProgress)->count() : null,
                'orders_completed' => $can['orders'] ? Order::query()->where('status', OrderStatus::Completed)->count() : null,
                'orders_cancelled' => $can['orders'] ? Order::query()->where('status', OrderStatus::Cancelled)->count() : null,
                'orders_refunded' => $can['orders'] ? Order::query()->where('status', OrderStatus::Refunded)->count() : null,
                'customers_total' => $can['customers'] ? User::query()->role('customer')->count() : null,
                'products_total' => $can['products'] ? Product::query()->count() : null,
                'products_active' => $can['products'] ? Product::query()->where('status', ProductStatus::Active)->count() : null,
                'low_stock' => $can['products'] ? AdminAttention::lowStockCount() : null,
                'out_of_stock' => $can['products'] ? AdminAttention::outOfStockCount() : null,
            ],
            // What is waiting on this member of staff, filtered by what they may open.
            'attention' => $attention->for($user),
            // Cash-on-delivery orders still open and unpaid after 3 days: not delivered
            // and no cash collected yet. Never cancelled automatically — just flagged.
            'staleCodOrders' => ! $can['orders'] ? [] : Order::query()
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
            'revenueSeries' => $can['reports'] ? $this->revenueSeries($tz) : [],
            'bestSellers' => $can['reports'] ? $this->bestSellers() : [],
            'recentOrders' => ! $can['orders'] ? [] : Order::query()
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
            'recentActivity' => ! $can['audit'] ? [] : Activity::query()
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

    /**
     * Last 30 days of revenue and paid orders, per shop day, for the charts.
     *
     * @return Collection<int, array{day: string, revenue: float, orders: int}>
     */
    private function revenueSeries(string $tz)
    {
        $day = ReportService::localDayOf('ev.at');

        return ReportService::moneyEvents(now($tz)->subDays(29)->startOfDay()->utc(), now())
            ->groupByRaw($day)
            ->orderByRaw($day)
            ->selectRaw("{$day} as day, SUM(ev.amount) as revenue_cents, COUNT(DISTINCT CASE WHEN ev.sign = 1 THEN ev.order_id END) as orders_count")
            ->get()
            ->map(fn ($row) => [
                'day' => $row->day,
                'revenue' => Money::toDecimal((int) $row->revenue_cents),
                'orders' => (int) $row->orders_count,
            ]);
    }

    /**
     * Sold means paid and kept: orders the shop holds money for. Unpaid, cancelled
     * and refunded (always whole) orders hold nothing — the report's rule.
     *
     * @return Collection<int, array{product_id: int|null, name: string, sold: int, revenue_formatted: string}>
     */
    private function bestSellers()
    {
        return DB::table('order_items')
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
    }
}
