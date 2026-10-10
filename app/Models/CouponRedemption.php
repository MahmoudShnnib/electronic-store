<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CouponRedemption extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = ['coupon_id', 'user_id', 'order_id', 'amount_cents', 'released_at'];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'released_at' => 'immutable_datetime'];
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
