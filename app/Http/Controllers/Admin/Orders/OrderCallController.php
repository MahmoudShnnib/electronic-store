<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Orders;

use App\Actions\Orders\RecordOrderCall;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderCallAttempt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Validation\Rule;

/** "Confirmed" / "no answer" after calling the customer about a cash-on-delivery order. */
final class OrderCallController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:orders.manage'];
    }

    public function store(Request $request, Order $order, RecordOrderCall $record): RedirectResponse
    {
        $validated = $request->validate([
            'outcome' => ['required', Rule::in([OrderCallAttempt::CONFIRMED, OrderCallAttempt::NO_ANSWER])],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $record->handle($order, $validated['outcome'], $validated['note'] ?? null, $request->user()->id);

        return back()->with('success', $validated['outcome'] === OrderCallAttempt::CONFIRMED ? __('Order confirmed.') : __('Call recorded.'));
    }
}
