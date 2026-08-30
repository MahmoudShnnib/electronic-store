<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TreasuryAccount;
use App\Enums\TreasuryDirection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One line in the store treasury ledger. Balances are always derived by
 * summing these rows — no running total is stored anywhere.
 */
final class TreasuryTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'direction', 'account', 'amount_cents', 'currency',
        'source_type', 'source_id', 'order_id',
        'reference', 'note', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'direction' => TreasuryDirection::class,
            'account' => TreasuryAccount::class,
            'amount_cents' => 'integer',
        ];
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Signed amount: positive for money in, negative for money out. */
    public function signedAmountCents(): int
    {
        return $this->amount_cents * $this->direction->sign();
    }

    public function scopeAccount(Builder $query, TreasuryAccount $account): Builder
    {
        return $query->where('account', $account);
    }
}
