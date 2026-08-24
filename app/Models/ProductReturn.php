<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReturnReason;
use App\Enums\ReturnStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Named ProductReturn because "Return" is a PHP reserved word; the table is `returns`.
 */
final class ProductReturn extends Model
{
    use HasFactory;

    protected $table = 'returns';

    protected $fillable = [
        'rma_number', 'order_id', 'user_id', 'status', 'reason',
        'customer_note', 'admin_note', 'images', 'reviewed_by',
        'reviewed_at', 'received_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReturnStatus::class,
            'reason' => ReturnReason::class,
            'images' => 'array',
            'reviewed_at' => 'immutable_datetime',
            'received_at' => 'immutable_datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnItem::class, 'return_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'return_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
