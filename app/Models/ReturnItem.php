<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ItemCondition;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ReturnItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'return_id', 'order_item_id', 'quantity', 'condition', 'restock', 'restocked_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'condition' => ItemCondition::class,
            'restock' => 'boolean',
            'restocked_at' => 'immutable_datetime',
        ];
    }

    public function productReturn(): BelongsTo
    {
        return $this->belongsTo(ProductReturn::class, 'return_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
