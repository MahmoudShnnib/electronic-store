<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\CouponType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Marketing\CouponRequest;
use App\Models\Coupon;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

final class CouponController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:coupons.manage'];
    }

    public function index(Request $request): Response
    {
        $coupons = Coupon::query()
            ->withCount('redemptions')
            ->when($request->filled('q'), fn ($q) => $q->whereRaw('code ILIKE ?', ['%'.$request->query('q').'%']))
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Coupon $c) => [
                'id' => $c->id,
                'code' => $c->code,
                'type' => $c->type->value,
                'type_label' => $c->type->label(),
                'value' => $c->type === CouponType::Percentage
                    ? $c->percent.'%'
                    : ($c->value_cents !== null ? Money::format($c->value_cents) : '—'),
                'value_cents' => $c->value_cents,
                'percent' => $c->percent,
                'min_order' => $c->min_order_cents !== null ? Money::toDecimal($c->min_order_cents) : null,
                'max_discount' => $c->max_discount_cents !== null ? Money::toDecimal($c->max_discount_cents) : null,
                'usage_limit' => $c->usage_limit,
                'per_customer_limit' => $c->per_customer_limit,
                'used_count' => $c->used_count,
                'redemptions_count' => $c->redemptions_count,
                'applies_to' => $c->applies_to,
                'applies_to_ids' => $c->applies_to_ids,
                'starts_at' => $c->starts_at?->format('Y-m-d\TH:i'),
                'expires_at' => $c->expires_at?->format('Y-m-d\TH:i'),
                'is_active' => $c->is_active,
                'currently_valid' => $c->isCurrentlyValid(),
            ]);

        return Inertia::render('admin/coupons/Index', [
            'coupons' => $coupons,
            'types' => array_map(fn (CouponType $t) => ['value' => $t->value, 'label' => $t->label()], CouponType::cases()),
            'filters' => (object) $request->only(['q']),
        ]);
    }

    public function store(CouponRequest $request): RedirectResponse
    {
        Coupon::create($this->validated($request));

        return back()->with('success', __('Coupon created.'));
    }

    public function update(CouponRequest $request, Coupon $coupon): RedirectResponse
    {
        $coupon->update($this->validated($request));

        return back()->with('success', __('Coupon updated.'));
    }

    public function destroy(Coupon $coupon): RedirectResponse
    {
        $coupon->delete();

        return back()->with('success', __('Coupon deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(CouponRequest $request): array
    {
        $validated = $request->validated();

        return [
            'code' => strtoupper($validated['code']),
            'type' => $validated['type'],
            'value_cents' => isset($validated['value']) ? Money::fromDecimal($validated['value']) : null,
            'percent' => $validated['percent'] ?? null,
            'min_order_cents' => isset($validated['min_order']) ? Money::fromDecimal($validated['min_order']) : null,
            'max_discount_cents' => isset($validated['max_discount']) ? Money::fromDecimal($validated['max_discount']) : null,
            'usage_limit' => $validated['usage_limit'] ?? null,
            'per_customer_limit' => $validated['per_customer_limit'] ?? null,
            'applies_to' => $validated['applies_to'],
            'applies_to_ids' => $validated['applies_to'] === 'all' ? null : ($validated['applies_to_ids'] ?? null),
            'starts_at' => $validated['starts_at'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ];
    }
}
