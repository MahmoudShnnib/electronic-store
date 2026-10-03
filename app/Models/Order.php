<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MovementType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'number', 'user_id', 'customer', 'billing_address', 'shipping_address',
        'status', 'payment_status', 'payment_method',
        'currency', 'subtotal_cents', 'discount_cents', 'coupon_code',
        'total_cents', 'customer_note',
        'internal_note', 'idempotency_key', 'placed_via', 'cashier_shift_id', 'confirmed_at',
        'shipped_at', 'delivered_at', 'completed_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'customer' => 'array',
            'billing_address' => 'array',
            'shipping_address' => 'array',
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'payment_method' => PaymentMethod::class,
            'subtotal_cents' => 'integer',
            'discount_cents' => 'integer',
            'total_cents' => 'integer',
            'confirmed_at' => 'immutable_datetime',
            'shipped_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cashierShift(): BelongsTo
    {
        return $this->belongsTo(CashierShift::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistories(): HasMany
    {
        // id breaks ties: a return logs two rows in the same second.
        return $this->hasMany(OrderStatusHistory::class)->latest('created_at')->latest('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function refundedTotalCents(): int
    {
        return (int) $this->refunds()->where('status', 'completed')->sum('amount_cents');
    }

    public function scopeStatus(Builder $query, OrderStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($term)).'%';

        return $query->where(fn (Builder $q) => $q
            ->whereRaw('number ILIKE ?', [$like])
            ->orWhereRaw("customer->>'name' ILIKE ?", [$like])
            ->orWhereRaw("customer->>'email' ILIKE ?", [$like])
            ->orWhereRaw("customer->>'phone' ILIKE ?", [$like]));
    }

    /** Whether the customer may cancel it: still open and no money held (that needs a refund first). */
    public function canBeCancelled(): bool
    {
        return $this->status->isCancellable() && $this->netPaidCents() <= 0;
    }

    /** Money the shop currently holds for this order: confirmed payments minus completed refunds. */
    public function netPaidCents(): int
    {
        return (int) $this->payments()->where('status', 'confirmed')->sum('amount_cents')
            - (int) $this->refunds()->where('status', 'completed')->sum('amount_cents');
    }

    /**
     * Per order line, how many units can still come back: sold minus already
     * returned (customer-return ledger rows for this order).
     *
     * @return array<int, int> order_item_id => units
     */
    public function returnableQuantities(): array
    {
        $returned = InventoryMovement::query()
            ->where('type', MovementType::CustomerReturn)
            ->where('reference_type', $this->getMorphClass())
            ->where('reference_id', $this->id)
            ->selectRaw('product_id, product_variant_id, warehouse_id, SUM(quantity_change) AS qty')
            ->groupBy('product_id', 'product_variant_id', 'warehouse_id')
            ->get()
            ->mapWithKeys(fn ($row) => ["{$row->product_id}:{$row->product_variant_id}:{$row->warehouse_id}" => (int) $row->qty])
            ->all();

        $left = [];

        foreach ($this->items()->orderBy('id')->get() as $item) {
            $key = "{$item->product_id}:{$item->product_variant_id}:{$item->warehouse_id}";
            $already = min($item->quantity, $returned[$key] ?? 0);
            $returned[$key] = ($returned[$key] ?? 0) - $already;
            $left[$item->id] = $item->quantity - $already;
        }

        return $left;
    }
}
