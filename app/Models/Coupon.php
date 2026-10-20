<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CouponType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

final class Coupon extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'code', 'type', 'value_cents', 'percent', 'min_order_cents',
        'max_discount_cents', 'usage_limit', 'per_customer_limit', 'used_count',
        'applies_to', 'applies_to_ids', 'starts_at', 'expires_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'value_cents' => 'integer',
            'percent' => 'float',
            'min_order_cents' => 'integer',
            'max_discount_cents' => 'integer',
            'usage_limit' => 'integer',
            'per_customer_limit' => 'integer',
            'used_count' => 'integer',
            'applies_to_ids' => 'array',
            'starts_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'is_active' => 'boolean',
        ];
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function isCurrentlyValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at !== null && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    public function usesByUser(int $userId): int
    {
        // A use released by a cancelled order no longer counts.
        return $this->redemptions()->where('user_id', $userId)->whereNull('released_at')->count();
    }

    /**
     * Uses by one person, recognised by account, phone or email — so a guest
     * (or a customer who signs out) can't reuse a once-per-customer coupon.
     * Keys come from App\Support\CustomerIdentity.
     */
    public function usesByCustomer(?int $userId, ?string $phoneKey, ?string $emailKey): int
    {
        if ($userId === null && $phoneKey === null && $emailKey === null) {
            return 0;
        }

        return $this->redemptions()
            ->whereNull('released_at')
            ->where(fn (Builder $q) => $q
                ->when($userId !== null, fn (Builder $q) => $q->orWhere('user_id', $userId))
                ->when($phoneKey !== null, fn (Builder $q) => $q->orWhere('customer_phone', $phoneKey))
                ->when($emailKey !== null, fn (Builder $q) => $q->orWhere('customer_email', $emailKey)))
            ->count();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['code', 'type', 'value_cents', 'percent', 'is_active', 'usage_limit'])
            ->logOnlyDirty()->dontLogEmptyChanges();
    }
}
