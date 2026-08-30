<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Customers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only customer screens: list and detail.
 * Activation lives in ToggleCustomerActiveController.
 */
final class CustomerController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:customers.view'];
    }

    public function index(Request $request): Response
    {
        $customers = User::query()
            ->role('customer')
            ->withCount('orders')
            ->withSum('orders as lifetime_cents', 'total_cents')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($qq) => $qq
                ->whereRaw('name ILIKE ?', ['%'.$request->query('q').'%'])
                ->orWhereRaw('email ILIKE ?', ['%'.$request->query('q').'%'])
                ->orWhereRaw('phone ILIKE ?', ['%'.$request->query('q').'%'])))
            ->when($request->query('status') === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->query('status') === 'inactive', fn ($q) => $q->where('is_active', false))
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'is_active' => $u->is_active,
                'verified' => $u->email_verified_at !== null,
                'orders_count' => $u->orders_count,
                'lifetime_formatted' => Money::format((int) ($u->lifetime_cents ?? 0)),
                'joined_at' => $u->created_at->toDateString(),
                'last_login_at' => $u->last_login_at?->diffForHumans(),
            ]);

        return Inertia::render('admin/customers/Index', [
            'customers' => $customers,
            'filters' => (object) $request->only(['q', 'status']),
        ]);
    }

    public function show(User $customer): Response
    {
        abort_unless($customer->hasRole('customer'), 404);

        return Inertia::render('admin/customers/Show', [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'is_active' => $customer->is_active,
                'verified' => $customer->email_verified_at !== null,
                'joined_at' => $customer->created_at->toDateString(),
                'addresses' => $customer->addresses()->get(),
                'orders' => $customer->orders()->latest()->take(20)->get()->map(fn ($o) => [
                    'id' => $o->id,
                    'number' => $o->number,
                    'status_label' => $o->status->label(),
                    'total_formatted' => Money::format($o->total_cents, $o->currency),
                    'created_at' => $o->created_at->toDateString(),
                ]),
                'reviews_count' => $customer->reviews()->count(),
            ],
        ]);
    }
}
