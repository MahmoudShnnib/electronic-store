<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Units that arrived on one stock row at one cost. Sales and write-offs use up
 * the oldest batches first (FIFO); only RecordMovement writes these, under the
 * stock row's lock, and the units left always add up to the row's quantity (S10).
 */
final class StockBatch extends Model
{
    protected $fillable = [
        'stock_level_id', 'unit_cost_cents', 'quantity_received', 'quantity_remaining', 'received_at',
    ];

    protected function casts(): array
    {
        return [
            'unit_cost_cents' => 'integer',
            'quantity_received' => 'integer',
            'quantity_remaining' => 'integer',
            'received_at' => 'immutable_datetime',
        ];
    }

    public function stockLevel(): BelongsTo
    {
        return $this->belongsTo(StockLevel::class);
    }
}
