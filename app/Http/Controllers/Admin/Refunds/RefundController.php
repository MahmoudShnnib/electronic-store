<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Refunds;

use App\Http\Controllers\Controller;
use App\Models\Refund;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

/** Lists issued refunds. Creating one lives in StoreRefundController. */
final class RefundController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:refunds.manage'];
    }

    public function index(Request $request): Response
    {
        $refunds = Refund::query()
            ->with(['order:id,number,currency', 'creator:id,name'])
            ->when($request->filled('q'), fn ($q) => $q->whereHas(
                'order',
                fn ($qq) => $qq->whereRaw('number ILIKE ?', ['%'.$request->query('q').'%']),
            ))
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Refund $r) => [
                'id' => $r->id,
                'order_number' => $r->order->number,
                'order_id' => $r->order_id,
                'amount_formatted' => Money::format($r->amount_cents, $r->order->currency),
                'method' => $r->method->label(),
                'reference' => $r->reference,
                'reason' => $r->reason,
                'created_by' => $r->creator?->name,
                'created_at' => $r->created_at->toDateTimeString(),
            ]);

        return Inertia::render('admin/refunds/Index', [
            'refunds' => $refunds,
            'filters' => (object) $request->only(['q']),
        ]);
    }
}
