<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StockCountItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_count_id', 'product_id', 'product_variant_id',
        'expected_quantity', 'counted_quantity',
    ];

    protected function casts(): array
    {
        return [
            'expected_quantity' => 'integer',
            'counted_quantity' => 'integer',
        ];
    }

    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id')->withTrashed();
    }

    public function variance(): ?int
    {
        return $this->counted_quantity === null
            ? null
            : $this->counted_quantity - $this->expected_quantity;
    }
}
