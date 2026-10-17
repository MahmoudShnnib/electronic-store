<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SupplierTransactionType;
use App\Enums\TreasuryAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line in a supplier's account. Positive amounts raise what the shop owes,
 * negative ones lower it; the balance is the sum.
 */
final class SupplierTransaction extends Model
{
    protected $fillable = [
        'supplier_id', 'type', 'amount_cents', 'purchase_order_id',
        'account', 'reference', 'note', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => SupplierTransactionType::class,
            'account' => TreasuryAccount::class,
            'amount_cents' => 'integer',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
