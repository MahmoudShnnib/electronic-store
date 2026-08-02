<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Inventory\WarehouseRequest;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

final class WarehouseController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:warehouses.manage'];
    }

    public function index(): Response
    {
        return Inertia::render('admin/warehouses/Index', [
            'warehouses' => Warehouse::query()
                ->withSum('stockLevels as total_quantity', 'quantity')
                ->orderBy('name')
                ->get()
                ->map(fn (Warehouse $w) => [
                    'id' => $w->id,
                    'name' => $w->name,
                    'code' => $w->code,
                    'address' => $w->address,
                    'is_default' => $w->is_default,
                    'is_active' => $w->is_active,
                    'total_quantity' => (int) ($w->total_quantity ?? 0),
                ]),
        ]);
    }

    public function store(WarehouseRequest $request): RedirectResponse
    {
        $validated = $this->validated($request);

        $warehouse = Warehouse::create($validated);

        $this->ensureSingleDefault($warehouse);

        return back()->with('success', 'Warehouse created.');
    }

    public function update(WarehouseRequest $request, Warehouse $warehouse): RedirectResponse
    {
        $warehouse->update($this->validated($request));

        $this->ensureSingleDefault($warehouse);

        return back()->with('success', 'Warehouse updated.');
    }

    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        if ($warehouse->is_default) {
            return back()->with('error', 'Cannot delete the default warehouse.');
        }

        if ($warehouse->stockLevels()->where('quantity', '>', 0)->exists()) {
            return back()->with('error', 'Transfer remaining stock out before deleting this warehouse.');
        }

        $warehouse->delete();

        return back()->with('success', 'Warehouse deleted.');
    }

    private function ensureSingleDefault(Warehouse $warehouse): void
    {
        if ($warehouse->is_default) {
            Warehouse::query()->whereKeyNot($warehouse->id)->update(['is_default' => false]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(WarehouseRequest $request): array
    {
        return $request->validated();
    }
}
