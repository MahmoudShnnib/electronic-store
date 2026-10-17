<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\TreasuryAccount;
use App\Enums\TreasuryDirection;
use App\Models\TreasuryTransaction;
use App\Support\Dates;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Props for admin/treasury/Index: the cash and bank balances plus the ledger
 * of every movement behind them.
 */
final class TreasuryQueryService
{
    /**
     * @return array<string, mixed>
     */
    public function indexProps(Request $request): array
    {
        $balances = $this->balances();

        $transactions = TreasuryTransaction::query()
            ->with(['creator:id,name', 'order:id,number'])
            ->when(
                in_array($request->string('account')->value(), ['cash', 'bank'], true),
                fn ($q) => $q->where('account', $request->string('account')->value()),
            )
            ->when(
                in_array($request->string('direction')->value(), ['in', 'out'], true),
                fn ($q) => $q->where('direction', $request->string('direction')->value()),
            )
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (TreasuryTransaction $t) => [
                'id' => $t->id,
                'direction' => $t->direction->value,
                'direction_label' => $t->direction->label(),
                'account' => $t->account->value,
                'account_label' => $t->account->label(),
                'amount_formatted' => Money::format($t->amount_cents, $t->currency),
                'order' => $t->order?->only(['id', 'number']),
                'reference' => $t->reference,
                'note' => $t->note,
                'user' => $t->creator?->name,
                'at' => Dates::dateTime($t->created_at),
            ]);

        return [
            'balances' => [
                'cash_formatted' => Money::format($balances['cash']),
                'bank_formatted' => Money::format($balances['bank']),
                'total_formatted' => Money::format($balances['total']),
            ],
            'transactions' => $transactions,
            'filters' => (object) $request->only(['account', 'direction']),
        ];
    }

    /**
     * Balances derived from the ledger: money in minus money out, per account.
     *
     * @return array{cash: int, bank: int, total: int}
     */
    public function balances(): array
    {
        // The query builder, not the model: an aggregate row would otherwise be
        // handed back with `account`/`direction` already cast to enums.
        $rows = DB::table('treasury_transactions')
            ->selectRaw('account, direction, SUM(amount_cents) AS total')
            ->groupBy('account', 'direction')
            ->get();

        $balances = [TreasuryAccount::Cash->value => 0, TreasuryAccount::Bank->value => 0];

        foreach ($rows as $row) {
            $balances[(string) $row->account] += ((int) $row->total) * TreasuryDirection::from((string) $row->direction)->sign();
        }

        return [
            'cash' => $balances[TreasuryAccount::Cash->value],
            'bank' => $balances[TreasuryAccount::Bank->value],
            'total' => $balances[TreasuryAccount::Cash->value] + $balances[TreasuryAccount::Bank->value],
        ];
    }
}
