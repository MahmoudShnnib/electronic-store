<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Purchasing\SupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

final class SupplierController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:suppliers.manage'];
    }

    public function index(Request $request): Response
    {
        $suppliers = Supplier::query()
            ->withCount(['products', 'purchaseOrders'])
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($qq) => $qq
                ->whereRaw('company_name ILIKE ?', ['%'.$request->query('q').'%'])
                ->orWhereRaw('contact_person ILIKE ?', ['%'.$request->query('q').'%'])
                ->orWhereRaw('email ILIKE ?', ['%'.$request->query('q').'%'])))
            ->when($request->filled('status'), fn ($q) => $q->where('status', (string) $request->query('status')))
            ->orderBy('company_name')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('admin/suppliers/Index', [
            'suppliers' => $suppliers,
            'filters' => (object) $request->only(['q', 'status']),
        ]);
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        Supplier::create($this->validated($request));

        return back()->with('success', 'Supplier created.');
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($this->validated($request));

        return back()->with('success', 'Supplier updated.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        if ($supplier->purchaseOrders()->exists()) {
            return back()->with('error', 'This supplier has purchase orders; deactivate it instead.');
        }

        $supplier->delete();

        return back()->with('success', 'Supplier deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(SupplierRequest $request): array
    {
        return $request->validated();
    }
}
