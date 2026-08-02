<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per (warehouse, product, variant).
 *
 *  - `quantity` = available-to-sell stock (what the storefront can promise).
 *  - `reserved` = allocated to open orders but still physically on premises.
 *  - Physical on-hand = quantity + reserved.
 *
 * Placing an order moves qty from `quantity` into `reserved` (with a ledger
 * entry); shipping clears `reserved`; cancelling moves it back. Rows are only
 * ever mutated inside a transaction holding a row lock that also writes an
 * InventoryMovement (see Actions\Inventory\RecordMovement).
 */
final class StockLevel extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_id', 'product_id', 'product_variant_id',
        'quantity', 'reserved', 'damaged', 'incoming',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'reserved' => 'integer',
            'damaged' => 'integer',
            'incoming' => 'integer',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function available(): int
    {
        return max(0, $this->quantity);
    }

    public function physicalOnHand(): int
    {
        return $this->quantity + $this->reserved;
    }
}
