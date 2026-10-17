<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CashierShiftStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One cashier's session at a register. Sales made during it point back here,
 * which is what makes the drawer countable at the end.
 */
final class CashierShift extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'warehouse_id', 'status', 'opening_float_cents',
        'expected_cash_cents', 'counted_cash_cents', 'difference_cents',
        'opened_at', 'closed_at', 'note',
    ];

    protected function casts(): array
    {
        return [
            'status' => CashierShiftStatus::class,
            'opening_float_cents' => 'integer',
            'expected_cash_cents' => 'integer',
            'counted_cash_cents' => 'integer',
            'difference_cents' => 'integer',
            'opened_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class)->withTrashed();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', CashierShiftStatus::Open);
    }

    public function isOpen(): bool
    {
        return $this->status === CashierShiftStatus::Open;
    }

    /**
     * Confirmed payments taken during this shift, keyed by method. Bank
     * transfers are counted for the report but never expected in the drawer.
     *
     * @return array<string, int>
     */
    public function takingsByMethod(): array
    {
        return Payment::query()
            ->whereIn('order_id', $this->orders()->select('id'))
            ->where('status', 'confirmed')
            ->selectRaw('method, SUM(amount_cents) as total')
            ->groupBy('method')
            ->pluck('total', 'method')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    /**
     * What should physically be in the drawer right now: the opening float
     * plus every cash payment taken, minus every cash refund handed back.
     */
    public function expectedCashCents(): int
    {
        $takings = (int) (($this->takingsByMethod()[PaymentMethod::Cash->value]) ?? 0);

        $refunded = $this->cashRefundedCents();

        return $this->opening_float_cents + $takings - $refunded;
    }

    /**
     * Cash handed back out of this drawer: the refunds paid at this till
     * (refunds.cashier_shift_id). A refund made in the back office comes out of
     * the treasury, not out of any drawer — matching by order or by cashier used
     * to take one refund off two drawers at once.
     */
    public function cashRefundedCents(): int
    {
        return (int) Refund::query()
            ->where('cashier_shift_id', $this->id)
            ->where('status', 'completed')
            ->where('method', 'cash')
            ->sum('amount_cents');
    }
}
