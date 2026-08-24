<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Account;

use App\Actions\Returns\RequestReturn;
use App\Enums\ReturnReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Returns\RequestReturnRequest;
use App\Models\Order;
use App\Models\ProductReturn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

final class ReturnController extends Controller
{
    public function index(Request $request): Response
    {
        $returns = $request->user()->returns()
            ->with('order:id,number')
            ->latest()
            ->paginate(10)
            ->through(fn (ProductReturn $return) => [
                'id' => $return->id,
                'rma_number' => $return->rma_number,
                'order_number' => $return->order->number,
                'status' => $return->status->value,
                'status_label' => $return->status->label(),
                'reason_label' => $return->reason->label(),
                'created_at' => $return->created_at->toDateString(),
            ]);

        return Inertia::render('storefront/account/Returns', ['returns' => $returns]);
    }

    public function create(Request $request, Order $order): Response|RedirectResponse
    {
        Gate::authorize('view', $order);

        // Eligibility lived only in the action, so this page happily rendered
        // for any order and only failed once the form was submitted.
        if (! $order->isReturnable()) {
            return redirect()
                ->route('account.orders.show', $order->id)
                ->with('error', __('This order is not eligible for a return.'));
        }

        $order->load('items');

        return Inertia::render('storefront/account/RequestReturn', [
            'order' => [
                'id' => $order->id,
                'number' => $order->number,
                'items' => $order->items->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->variant_name !== null
                        ? "{$item->product_name} — {$item->variant_name}"
                        : $item->product_name,
                    'quantity' => $item->quantity,
                    'returnable' => $item->quantity - $item->returnedQuantity(),
                ]),
            ],
            'reasons' => array_map(fn (ReturnReason $r) => ['value' => $r->value, 'label' => $r->label()], ReturnReason::cases()),
        ]);
    }

    public function store(RequestReturnRequest $request, Order $order, RequestReturn $requestReturn): RedirectResponse
    {
        Gate::authorize('view', $order);

        $validated = $request->validated();

        $paths = [];
        foreach ($request->file('images', []) as $file) {
            $paths[] = $file->store('returns', 'public');
        }

        try {
            $return = $requestReturn->handle(
                $order,
                $request->user(),
                $validated['items'],
                $validated['reason'],
                $validated['customer_note'] ?? null,
                $paths,
            );
        } catch (\Throwable $e) {
            // The photos were written before the action's checks ran; a failed
            // request must not leave them on the disk forever.
            Storage::disk('public')->delete($paths);

            throw $e;
        }

        return redirect()
            ->route('account.returns.index')
            ->with('success', __('Return request :rma submitted.', ['rma' => $return->rma_number]));
    }
}
