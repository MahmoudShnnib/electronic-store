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

    /** The product's sale window, column-qualified so it works inside variant subqueries. */
    private const SALE_WINDOW_OPEN = '(products.sale_starts_at IS NULL OR products.sale_starts_at <= NOW())'
        .' AND (products.sale_ends_at IS NULL OR products.sale_ends_at >= NOW())';

    /** A variant's live price: its sale price while the parent's window is open (variants have no window of their own). */
    private const VARIANT_PRICE = '(CASE WHEN v.sale_price_cents IS NOT NULL AND '.self::SALE_WINDOW_OPEN
        .' THEN v.sale_price_cents ELSE v.price_cents END)';

    private const ACTIVE_VARIANTS = 'FROM product_variants v WHERE v.product_id = products.id AND v.is_active AND v.deleted_at IS NULL AND products.has_variants';

    /**
     * The cheapest and dearest a shopper can pay right now, variants included —
     * what a listing's price filter, price sort and "from" price must use. A
     * variant product is priced by its active variants (the product row's own
     * price is not for sale); with none left it falls back to the product row.
     */
    public const MIN_PRICE = '(COALESCE((SELECT MIN('.self::VARIANT_PRICE.') '.self::ACTIVE_VARIANTS.'), '.self::EFFECTIVE_PRICE.'))';

    public const MAX_PRICE = '(COALESCE((SELECT MAX('.self::VARIANT_PRICE.') '.self::ACTIVE_VARIANTS.'), '.self::EFFECTIVE_PRICE.'))';

    protected $fillable = [
        'category_id', 'brand_id', 'supplier_id', 'name', 'name_ar', 'slug', 'sku',
        'barcode', 'short_description', 'short_description_ar', 'description',
        'description_ar', 'cost_price_cents', 'price_cents', 'sale_price_cents',
        'sale_starts_at', 'sale_ends_at', 'has_variants',
        'low_stock_threshold', 'status', 'is_featured',
        'published_at', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'cost_price_cents' => 'integer',
            'price_cents' => 'integer',
            'sale_price_cents' => 'integer',
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

    /**
     * Whether any unit is still on premises (shelf, reserved, damaged) or on a sent
     * purchase order. Such a product can't be deleted: its units would keep counting
     * in inventory value with nothing to sell them under.
     */
    public function holdsStock(): bool
    {
        return $this->stockLevels()
            ->where(fn ($q) => $q->where('quantity', '>', 0)->orWhere('reserved', '>', 0)->orWhere('damaged', '>', 0)->orWhere('incoming', '>', 0))
            ->exists();
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
     * Whether "now" falls inside the scheduled sale window, regardless of
     * whether a sale price is actually set. Variants have no schedule of their
     * own, so their sale prices follow this one.
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
        return $query->whereIn('status', [ProductStatus::Active, ProductStatus::OutOfStock])
            // A deactivated category takes its products off the shop with it.
            ->whereHas('category', fn (Builder $q) => $q->where('is_active', true));
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
    // An order line ships from one warehouse, so a customer can buy at most the
    // biggest single stock row — never the total spread across warehouses.
    public function scopeWithStock(Builder $query): Builder
    {
        return $query->withMax(['stockLevels as stock_quantity' => fn ($q) => $q->sellable()], 'quantity');
    }

    /** Falls back to a query only when scopeWithStock() was not applied. */
    public function availableQuantity(): int
    {
        return max(0, (int) ($this->stock_quantity ?? $this->stockLevels()->sellable()->max('quantity')));
    }

    /**
     * Sellable units per variant and for the product, each the biggest single
     * stock row (an order line ships from one warehouse). A variant product
     * with no row of its own reports the sum of its variants. One query.
     *
     * @return array{product: int, variants: array<int, int>}
     */
    public function sellableQuantities(): array
    {
        $levels = $this->stockLevels()
            ->sellable()
            ->selectRaw('product_variant_id, MAX(quantity) as qty')
            ->groupBy('product_variant_id')
            ->pluck('qty', 'product_variant_id');

        $variants = [];
        foreach ($levels as $variantId => $qty) {
            if ($variantId !== null && $variantId !== '') {
                $variants[(int) $variantId] = (int) $qty;
            }
        }

        return [
            'product' => (int) ($levels[null] ?? $levels[''] ?? array_sum($variants)),
            'variants' => $variants,
        ];
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

    /** onSale(), or a variant product with any active variant on sale (the cart charges those sale prices). */
    public function scopeOnSaleIncludingVariants(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where(fn (Builder $q) => $q->onSale())
            ->orWhereRaw('EXISTS (SELECT 1 '.self::ACTIVE_VARIANTS.' AND v.sale_price_cents IS NOT NULL AND '.self::SALE_WINDOW_OPEN.')'));
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
