<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Dates;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The command palette's record search (Ctrl/Cmd+K): a few orders, products and
 * customers matching what was typed, each linking to its own screen.
 *
 * A section is only searched for staff who may open that screen, using the same
 * permission its controller checks, and comes back `null` otherwise — the
 * palette hides it rather than showing an empty group.
 */
final class SearchController extends Controller
{
    private const LIMIT = 5;

    public function __invoke(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        $user = $request->user();

        if (mb_strlen($term) < 2) {
            return response()->json(['orders' => [], 'products' => [], 'customers' => []]);
        }

        return response()->json([
            'orders' => $user->can('orders.view') ? $this->orders($term) : null,
            'products' => $user->can('products.view') ? $this->products($term, $user) : null,
            'customers' => $user->can('customers.view') ? $this->customers($term) : null,
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function orders(string $term): array
    {
        return Order::query()
            ->search($term)
            ->latest()
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Order $o) => [
                'id' => $o->id,
                'number' => $o->number,
                'customer' => $o->customer['name'] ?? '—',
                'status' => $o->status->value,
                'status_label' => $o->status->label(),
                'total_formatted' => Money::format($o->total_cents, $o->currency),
                'created_at' => Dates::relative($o->created_at),
                'url' => route('admin.orders.show', $o),
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function products(string $term, User $user): array
    {
        // The edit form needs products.update; view-only staff (cashiers) land on
        // the list filtered to the product instead of a 403.
        $canEdit = $user->can('products.update');

        return Product::query()
            ->search($term)
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'name_ar' => $p->name_ar,
                'sku' => $p->sku,
                'status' => $p->status->value,
                'status_label' => $p->status->label(),
                'price_formatted' => Money::format($p->currentPriceCents()),
                'url' => $canEdit ? route('admin.products.edit', $p) : route('admin.products.index', ['q' => $p->sku]),
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function customers(string $term): array
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return User::query()
            ->role('customer')
            ->where(fn ($q) => $q
                ->whereRaw('name ILIKE ?', [$like])
                ->orWhereRaw('email ILIKE ?', [$like])
                ->orWhereRaw('phone ILIKE ?', [$like]))
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get(['id', 'name', 'email', 'phone'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'phone' => $u->phone,
                'url' => route('admin.customers.show', $u),
            ])
            ->all();
    }
}
