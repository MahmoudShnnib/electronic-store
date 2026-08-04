<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

final class ProductVariant extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'product_id', 'name', 'sku', 'barcode', 'attributes', 'cost_price_cents',
        'price_cents', 'sale_price_cents', 'low_stock_threshold', 'image_path',
        'position', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'cost_price_cents' => 'integer',
            'price_cents' => 'integer',
            'sale_price_cents' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function currentPriceCents(): int
    {
        return $this->sale_price_cents !== null ? (int) $this->sale_price_cents : (int) $this->price_cents;
    }

    public function displayName(): string
    {
        return $this->product->name.' — '.$this->name;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'sku', 'price_cents', 'sale_price_cents', 'cost_price_cents', 'is_active'])
            ->logOnlyDirty();
    }
}
