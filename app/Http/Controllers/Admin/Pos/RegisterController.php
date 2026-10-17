<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Pos;

use App\Actions\Pos\OpenShift;
use App\Http\Controllers\Controller;
use App\Services\Admin\PosQueryService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The register. A cashier without an open drawer gets the open-shift screen
 * instead — there is no way to sell without one, so this is a redirect in
 * spirit, rendered in place to save a round trip.
 */
final class RegisterController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:pos.use'];
    }

    public function __construct(
        private readonly PosQueryService $query,
        private readonly OpenShift $openShift,
    ) {}

    public function __invoke(Request $request): Response
    {
        $shift = $this->openShift->current($request->user());

        if ($shift === null) {
            return Inertia::render('admin/pos/OpenShift', $this->query->openShiftProps());
        }

        return Inertia::render('admin/pos/Register', [
            // Owner rule (2026-10-10): admins see what the stock cost them; cashiers never get it.
            ...$this->query->registerProps($shift, withCost: $request->user()->hasAnyRole(['admin', 'super_admin'])),
            // Returns at the till are for staff who may refund.
            'canReturn' => $request->user()->can('refunds.manage'),
            // The most a cashier may take off by hand, in percent; null = uncapped (admins).
            'manualDiscountMaxPercent' => $request->user()->hasAnyRole(['admin', 'super_admin'])
                ? null
                : (int) config('store.pos_manual_discount_max_percent'),
            // Present for exactly one render, straight after a sale: the slip to
            // print and the figures to confirm. Null on every other visit.
            'justSold' => $request->session()->get('justSold'),
        ]);
    }
}
