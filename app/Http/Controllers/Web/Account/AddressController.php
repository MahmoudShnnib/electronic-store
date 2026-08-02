<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Account;

use App\Actions\Account\SaveAddress;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\AddressRequest;
use App\Models\Address;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class AddressController extends Controller
{
    public function __construct(private readonly SaveAddress $saveAddress)
    {
    }

    public function index(Request $request): Response
    {
        return Inertia::render('storefront/account/Addresses', [
            'addresses' => $request->user()->addresses()->latest()->get(),
            'cities' => config('store.cities', []),
        ]);
    }

    public function store(AddressRequest $request): RedirectResponse
    {
        $this->saveAddress->handle($request->user(), $request->validated());

        return back()->with('success', __('Address saved.'));
    }

    public function update(AddressRequest $request, Address $address): RedirectResponse
    {
        Gate::authorize('update', $address);

        $this->saveAddress->handle($request->user(), $request->validated(), $address);

        return back()->with('success', __('Address updated.'));
    }

    public function destroy(Request $request, Address $address): RedirectResponse
    {
        Gate::authorize('delete', $address);

        $address->delete();

        return back()->with('success', __('Address removed.'));
    }
}
