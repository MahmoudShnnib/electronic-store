<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Orders;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Orders\UpdateOrderNoteRequest;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;

/** Saves the staff-only internal note on an order. */
final class UpdateOrderNoteController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:orders.manage'];
    }

    public function __invoke(UpdateOrderNoteRequest $request, Order $order): RedirectResponse
    {
        $validated = $request->validated();

        $order->update(['internal_note' => $validated['internal_note'] ?? null]);

        return back()->with('success', __('Note saved.'));
    }
}
