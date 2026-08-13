<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Inventory;

use App\Actions\Inventory\InsufficientStockException;
use App\Actions\Inventory\TransferStock;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Inventory\TransferStockRequest;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;

/** Moves stock between two warehouses. */
final class TransferStockController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:inventory.transfer'];
    }

    public function __invoke(TransferStockRequest $request, TransferStock $transferStock): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $transferStock->handle(
                Warehouse::query()->findOrFail($validated['from_warehouse_id']),
                Warehouse::query()->findOrFail($validated['to_warehouse_id']),
                $validated['items'],
                $validated['notes'] ?? null,
                $request->user()->id,
            );
        } catch (InsufficientStockException $e) {
            return back()->withErrors(['items' => $e->getMessage()]);
        }

        return back()->with('success', __('Stock transferred.'));
    }
}
