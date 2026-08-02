<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockCountStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class StockCount extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference', 'warehouse_id', 'status', 'notes', 'created_by', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => StockCountStatus::class,
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockCountItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
