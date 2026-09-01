<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

final class Product extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * What the shopper actually pays right now: the sale price while the sale
     * is live, the regular price otherwise. Shared by storefront and API
     * filtering/sorting so a price sort never ignores an expired/future sale.
     */
    public const EFFECTIVE_PRICE = '(CASE WHEN sale_price_cents IS NOT NULL'
        .' AND (sale_starts_at IS NULL OR sale_starts_at <= NOW())'
        .' AND (sale_ends_at IS NULL OR sale_ends_at >= NOW())'
        .' THEN sale_price_cents ELSE price_cents END)';

    protected $fillable = [
        'category_id', 'brand_id', 'supplier_id', 'name', 'name_ar', 'slug', 'sku',
        'barcode', 'short_description', 'short_description_ar', 'description',
        'description_ar', 'cost_price_cents', 'price_cents', 'sale_price_cents',
        'sale_starts_at', 'sale_ends_at', 'tax_rate', 'has_variants',
        'low_stock_threshold', 'warranty_months', 'weight_grams', 'length_mm',
        'width_mm', 'height_mm', 'status', 'is_featured', 'meta_title',
        'meta_description', 'published_at', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'cost_price_cents' => 'integer',
            'price_cents' => 'integer',
            'sale_price_cents' => 'integer',
            'tax_rate' => 'float',
            'has_variants' => 'boolean',
            'is_featured' => 'boolean',
            'sale_starts_at' => 'immutable_datetime',
            'sale_ends_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
        ];
    }

    // -- Relationships --------------------------------------------------------

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('position');
    }

    public function activeVariants(): HasMany
    {
        return $this->variants()->where('is_active', true);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    public function primaryImage(): HasMany
    {
        return $this->hasMany(ProductImage::class)->where('is_primary', true);
    }

    public function specificationValues(): HasMany
    {
        return $this->hasMany(ProductSpecificationValue::class);
    }

    public function priceHistories(): HasMany
    {
        return $this->hasMany(PriceHistory::class);
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function approvedReviews(): HasMany
    {
        return $this->reviews()->where('status', 'approved');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // -- Pricing --------------------------------------------------------------

    /**
     * Whether the sale schedule is open right now, ignoring whether a sale price
     * is set. Variants have no schedule of their own and follow this window.
     */
    public function saleWindowIsOpen(): bool
    {
        if ($this->sale_starts_at !== null && $this->sale_starts_at->isFuture()) {
            return false;
        }

        if ($this->sale_ends_at !== null && $this->sale_ends_at->isPast()) {
            return false;
        }

        return true;
    }

    /** Whether the sale price is currently in effect (inside its schedule window). */
    public function saleIsActive(): bool
    {
        return $this->sale_price_cents !== null && $this->saleWindowIsOpen();
    }

    /** The price a customer pays right now, in cents. */
    public function currentPriceCents(): int
    {
        return $this->saleIsActive() ? (int) $this->sale_price_cents : (int) $this->price_cents;
    }

    // -- Scopes ---------------------------------------------------------------

    public function scopeVisibleInStore(Builder $query): Builder
    {
        return $query->whereIn('status', [ProductStatus::Active, ProductStatus::OutOfStock]);
    }

    public function scopePurchasable(Builder $query): Builder
    {
        return $query->where('status', ProductStatus::Active);
    }

    /**
     * Sellable units across every warehouse, variants included (variant stock
     * rows carry this product's id too). Nothing ever flips a product to
     * `out_of_stock`, so the grid has to ask the warehouse, not the status.
     */
    public function scopeWithStock(Builder $query): Builder
    {
        return $query->withSum(['stockLevels as stock_quantity' => fn ($q) => $q->sellable()], 'quantity');
    }

    /** Falls back to a query only when scopeWithStock() was not applied. */
    public function availableQuantity(): int
    {
        return (int) ($this->stock_quantity ?? $this->stockLevels()->sellable()->sum('quantity'));
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeOnSale(Builder $query): Builder
    {
        return $query->whereNotNull('sale_price_cents')
            ->where(fn (Builder $q) => $q->whereNull('sale_starts_at')->orWhere('sale_starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('sale_ends_at')->orWhere('sale_ends_at', '>=', now()));
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($term)).'%';

        return $query->where(fn (Builder $q) => $q
            ->whereRaw('name ILIKE ?', [$like])
            ->orWhereRaw('name_ar ILIKE ?', [$like])
            ->orWhereRaw('sku ILIKE ?', [$like])
            ->orWhereRaw('barcode ILIKE ?', [$like]));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name', 'sku', 'status', 'price_cents', 'sale_price_cents',
                'cost_price_cents', 'category_id', 'brand_id', 'is_featured',
            ])
            ->logOnlyDirty()->dontLogEmptyChanges();
    }
}
