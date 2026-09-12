<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Pos;

use App\Actions\Pos\CompleteSale;
use App\Actions\Pos\OpenShift;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Pos\CompleteSaleRequest;
use App\Services\Admin\PosQueryService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;

/** Rings up one counter sale and hands the slip back to the register to print. */
final class CompleteSaleController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:pos.use'];
    }

    public function __construct(
        private readonly CompleteSale $completeSale,
        private readonly OpenShift $openShift,
        private readonly PosQueryService $query,
    ) {}

    public function __invoke(CompleteSaleRequest $request, CompleteSale $sale): RedirectResponse
    {
        $shift = $this->openShift->current($request->user());

        if ($shift === null) {
            return back()->with('error', __('Open a shift before selling.'));
        }

        $validated = $request->validated();

        // A cashier without pos.discount can still ring up sales; the keyed-in
        // discount is simply dropped rather than failing the sale.
        $discount = $request->user()->can('pos.discount')
            ? Money::fromDecimal($validated['discount'] ?? 0)
            : 0;

        $order = $this->completeSale->handle(
            shift: $shift,
            lines: $validated['items'],
            paymentMethod: PaymentMethod::from($validated['payment_method']),
            customer: $validated['customer'] ?? [],
            couponCode: $validated['coupon_code'] ?? null,
            manualDiscountCents: $discount,
            note: $validated['note'] ?? null,
        );

        // Back to the register rather than off to the receipt page. A cashier
        // with a queue does not want to be moved to another screen and then have
        // to navigate back; the slip prints from the till and the confirmation
        // is a dialog over the basket they are already looking at.
        return back()->with('justSold', $this->query->receiptProps($order));
    }
}
