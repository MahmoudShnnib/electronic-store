<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Customers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

/** Activates or deactivates a customer account, logging the change. */
final class ToggleCustomerActiveController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:customers.manage'];
    }

    public function __invoke(Request $request, User $customer): RedirectResponse
    {
        abort_if($customer->id === $request->user()->id, 403);

        $customer->update(['is_active' => ! $customer->is_active]);

        activity()
            ->performedOn($customer)
            ->event($customer->is_active ? 'account_activated' : 'account_deactivated')
            ->log(($customer->is_active ? 'Activated' : 'Deactivated')." account {$customer->email}");

        return back()->with('success', $customer->is_active ? __('Account activated.') : __('Account deactivated.'));
    }
}
