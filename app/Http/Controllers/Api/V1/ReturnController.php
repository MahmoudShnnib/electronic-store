<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Returns\RequestReturn;
use App\Http\Controllers\Controller;
use App\Http\Requests\Returns\RequestReturnRequest;
use App\Models\Order;
use App\Models\ProductReturn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReturnController extends Controller
{
    /**
     * The authenticated user's return requests.
     */
    public function index(Request $request): JsonResponse
    {
        $returns = $request->user()->returns()
            ->with('order:id,number')
            ->latest()
            ->paginate(15)
            ->through(fn (ProductReturn $r) => [
                'id' => $r->id,
                'rma_number' => $r->rma_number,
                'order_number' => $r->order->number,
                'status' => $r->status->value,
                'reason' => $r->reason,
                'created_at' => $r->created_at->toIso8601String(),
            ]);

        return response()->json($returns);
    }

    /**
     * Request a return for delivered order items.
     */
    public function store(RequestReturnRequest $request, RequestReturn $requestReturn): JsonResponse
    {
        $validated = $request->validated();

        $return = $requestReturn->handle(
            Order::query()->findOrFail($validated['order_id']),
            $request->user(),
            $validated['items'],
            $validated['reason'],
            $validated['customer_note'] ?? null,
        );

        return response()->json([
            'data' => [
                'id' => $return->id,
                'rma_number' => $return->rma_number,
                'status' => $return->status->value,
            ],
        ], 201);
    }
}
