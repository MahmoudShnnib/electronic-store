<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Pos;

use App\Actions\Pos\CloseShift;
use App\Actions\Pos\OpenShift;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Pos\CloseShiftRequest;
use App\Http\Requests\Admin\Pos\OpenShiftRequest;
use App\Models\Warehouse;
use App\Services\Admin\PosQueryService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

/** Opening, counting down and reviewing cashier drawers. */
final class ShiftController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:pos.use', only: ['open', 'close', 'store']),
            new Middleware('can:pos.shifts.view', only: ['index']),
        ];
    }

    public function __construct(
        private readonly PosQueryService $query,
        private readonly OpenShift $openShift,
        private readonly CloseShift $closeShift,
    ) {}

    /** The shift log — every drawer, whoever ran it. */
    public function index(): Response
    {
        return Inertia::render('admin/pos/Shifts', $this->query->shiftsProps());
    }

    public function store(OpenShiftRequest $request): RedirectResponse
    {
        $this->openShift->handle(
            cashier: $request->user(),
            warehouse: Warehouse::query()->findOrFail($request->validated('warehouse_id')),
            openingFloatCents: Money::fromDecimal($request->validated('opening_float')),
            note: $request->validated('note'),
        );

        return redirect()->route('admin.pos.register')->with('success', __('Shift opened.'));
    }

    /** The count-down screen for the cashier's own drawer. */
    public function open(Request $request): Response|RedirectResponse
    {
        $shift = $this->openShift->current($request->user());

        if ($shift === null) {
            return redirect()->route('admin.pos.register');
        }

        return Inertia::render('admin/pos/CloseShift', $this->query->closeShiftProps($shift));
    }

    public function close(CloseShiftRequest $request): RedirectResponse
    {
        $shift = $this->openShift->current($request->user());

        if ($shift === null) {
            return back()->with('error', __('You have no open shift.'));
        }

        $shift = $this->closeShift->handle(
            shift: $shift,
            countedCashCents: Money::fromDecimal($request->validated('counted_cash')),
            note: $request->validated('note'),
            userId: $request->user()->id,
        );

        return redirect()->route('admin.pos.register')->with(
            'success',
            $shift->difference_cents === 0
                ? __('Shift closed — the drawer balanced.')
                : __('Shift closed with a variance of :amount.', ['amount' => Money::format(abs((int) $shift->difference_cents))]),
        );
    }
}
