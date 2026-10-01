<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Inventory;

use App\Actions\Inventory\AdjustStock;
use App\Actions\Inventory\InsufficientStockException;
use App\Enums\MovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Inventory\AdjustStockRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;

/** Applies a manual stock adjustment (increase, decrease, damage, correction). */
final class AdjustStockController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:inventory.adjust'];
    }

    public function __invoke(AdjustStockRequest $request, AdjustStock $adjustStock): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $adjustStock->handle(
                Warehouse::query()->findOrFail($validated['warehouse_id']),
                Product::query()->findOrFail($validated['product_id']),
                isset($validated['product_variant_id']) ? ProductVariant::withTrashed()->find($validated['product_variant_id']) : null,
                (int) $validated['quantity_change'],
                MovementType::from($validated['type']),
                $validated['reason'],
                $request->user()->id,
                (bool) ($validated['from_damaged'] ?? false),
            );
        } catch (InsufficientStockException $e) {
            return back()->withErrors(['quantity_change' => $e->getMessage()]);
        }

        return back()->with('success', __('Stock adjusted.'));
    }
}
