<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Orders;

use App\Actions\Orders\ResolveOrderIssue;
use App\Enums\RefundMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Orders\ResolveOrderIssueRequest;
use App\Models\Order;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;

/** Returns a completed order and, when an amount is given, refunds it. */
final class ResolveOrderIssueController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:refunds.manage'];
    }

    public function __invoke(ResolveOrderIssueRequest $request, Order $order, ResolveOrderIssue $resolveOrderIssue): RedirectResponse
    {
        $validated = $request->validated();
        $refundCents = Money::fromDecimal($validated['amount'] ?? 0);

        $resolveOrderIssue->handle(
            order: $order,
            reason: $validated['reason'],
            refundCents: $refundCents,
            method: RefundMethod::from($validated['method']),
            // Off unless asked: a full return is an explicit choice, a reason alone is just logged.
            restock: (bool) ($validated['restock'] ?? false),
            reference: $validated['reference'] ?? null,
            userId: $request->user()->id,
            restockQuantities: isset($validated['items']) ? array_map('intval', $validated['items']) : null,
        );

        return back()->with('success', $refundCents > 0
            ? __('Order returned and refunded.')
            : __('Order returned and the issue was recorded.'));
    }
}
