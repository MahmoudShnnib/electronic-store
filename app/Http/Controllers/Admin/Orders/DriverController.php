<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Orders;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Driver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

/** The shop's own drivers: who can take orders out, and what each is carrying now. */
final class DriverController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:orders.manage'];
    }

    public function index(): Response
    {
        return Inertia::render('admin/drivers/Index', [
            'drivers' => Driver::query()
                ->withCount(['orders as out_count' => fn ($q) => $q->where('status', OrderStatus::InProgress)->whereNotNull('shipped_at')])
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(['id', 'name', 'phone', 'is_active']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Driver::query()->create($this->validated($request));

        return back()->with('success', __('Driver saved.'));
    }

    public function update(Request $request, Driver $driver): RedirectResponse
    {
        $driver->update($this->validated($request));

        return back()->with('success', __('Driver saved.'));
    }

    /**
     * @return array{name: string, phone: string|null, is_active: bool}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:32'],
            'is_active' => ['boolean'],
        ]);

        return ['name' => trim($data['name']), 'phone' => $data['phone'] ?? null, 'is_active' => (bool) ($data['is_active'] ?? true)];
    }
}
