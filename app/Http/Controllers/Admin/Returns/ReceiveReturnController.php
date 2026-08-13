<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Returns;

use App\Actions\Returns\ReceiveReturnItems;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Returns\ReceiveReturnRequest;
use App\Models\ProductReturn;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;

/** Books the physically returned items back into a warehouse. */
final class ReceiveReturnController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:returns.manage'];
    }

    public function __invoke(ReceiveReturnRequest $request, ProductReturn $productReturn, ReceiveReturnItems $receiveReturnItems): RedirectResponse
    {
        $validated = $request->validated();

        $receiveReturnItems->handle(
            $productReturn,
            $validated['conditions'],
            Warehouse::query()->findOrFail($validated['warehouse_id']),
            $request->user()->id,
        );

        return back()->with('success', __('Return items received.'));
    }
}
