<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'number', 'user_id', 'customer', 'billing_address', 'shipping_address',
        'status', 'payment_status', 'payment_method',
        'currency', 'subtotal_cents', 'discount_cents', 'coupon_code',
        'tax_cents', 'total_cents', 'customer_note',
        'internal_note', 'idempotency_key', 'placed_via', 'confirmed_at',
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
            'tax_cents' => 'integer',
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

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->latest('created_at');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(ProductReturn::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function returnItems(): HasManyThrough
    {
        return $this->hasManyThrough(ReturnItem::class, OrderItem::class);
    }

    public function refundedTotalCents(): int
    {
        return (int) $this->refunds()->where('status', 'completed')->sum('amount_cents');
    }

    /**
     * Whether the return window is still open. Admin-editable via Settings;
     * the config value is only the default. An order with no delivery stamp
     * has no clock to run out.
     */
    public function returnWindowIsOpen(): bool
    {
        $windowDays = (int) (Setting::get('return_window_days') ?? config('store.return_window_days'));
        $deliveredAt = $this->delivered_at ?? $this->completed_at;

        return $deliveredAt === null || ! $deliveredAt->addDays($windowDays)->isPast();
    }

    /** The single answer to "can this customer open a return?" — page, link and action all ask it. */
    public function isReturnable(): bool
    {
        return $this->status->allowsReturn() && $this->returnWindowIsOpen() && $this->hasReturnableUnits();
    }

    /**
     * Whether any ordered unit is still uncovered by a return request. Reads
     * `items_count` / `returned_units` when the caller eager-loaded them via
     * `withSum('items as items_count', 'quantity')` and
     * `withSum('returnItems as returned_units', 'quantity')` (avoids an N+1
     * across a list of orders); falls back to a direct query otherwise.
     */
    private function hasReturnableUnits(): bool
    {
        $orderedUnits = array_key_exists('items_count', $this->attributes)
            ? (int) $this->attributes['items_count']
            : ($this->relationLoaded('items') ? (int) $this->items->sum('quantity') : (int) $this->items()->sum('quantity'));

        // hasManyThrough joins order_items and return_items, both of which have a
        // `quantity` column — must qualify or Postgres rejects it as ambiguous.
        $returnedUnits = array_key_exists('returned_units', $this->attributes)
            ? (int) $this->attributes['returned_units']
            : (int) $this->returnItems()->sum('return_items.quantity');

        return $orderedUnits > $returnedUnits;
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
}
