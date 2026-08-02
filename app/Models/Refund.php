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
        'order_id', 'return_id', 'amount_cents', 'method', 'reference',
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

    public function productReturn(): BelongsTo
    {
        return $this->belongsTo(ProductReturn::class, 'return_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
