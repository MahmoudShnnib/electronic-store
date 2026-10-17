<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RefundMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Refund extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'cashier_shift_id', 'amount_cents', 'method', 'reference',
        'reason', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'method' => RefundMethod::class,
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** The till the cash went back out of; null for a refund made in the back office. */
    public function cashierShift(): BelongsTo
    {
        return $this->belongsTo(CashierShift::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
