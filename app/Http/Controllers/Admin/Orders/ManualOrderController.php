<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Orders;

use App\Actions\Orders\CreateManualOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Orders\StoreManualOrderRequest;
use App\Services\Admin\OrderQueryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

/** Takes an order the shop received off-site (WhatsApp, phone, counter). */
final class ManualOrderController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:orders.manage'];
    }

    public function create(OrderQueryService $orders): Response
    {
        return Inertia::render('admin/orders/Create', $orders->createProps());
    }

    public function store(StoreManualOrderRequest $request, CreateManualOrder $createManualOrder): RedirectResponse
    {
        $validated = $request->validated();

        $order = $createManualOrder->handle(
            items: $validated['items'],
            data: $request->toCheckoutData(),
            userId: $request->user()->id,
            placedVia: $validated['source'] ?? 'manual',
        );

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('success', __('Order :number created.', ['number' => $order->number]));
    }
}
