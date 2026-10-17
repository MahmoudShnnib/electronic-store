<?php

declare(strict_types=1);

namespace App\Actions\Suppliers;

use App\Actions\Treasury\RecordTreasuryMovement;
use App\Enums\SupplierTransactionType;
use App\Enums\TreasuryAccount;
use App\Enums\TreasuryDirection;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierTransaction;
use App\Services\Admin\TreasuryQueryService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pays a supplier from the cash drawer or the bank. One action, two ledgers: the
 * supplier's account goes down and the treasury records the money leaving, so
 * "what we owe" and "what we hold" can never disagree about a payment.
 *
 * Paying more than is owed is allowed — a deposit ahead of the goods — and shows
 * as a credit with the supplier.
 */
final class RecordSupplierPayment
{
    public function __construct(
        private readonly RecordTreasuryMovement $recordTreasuryMovement,
        private readonly TreasuryQueryService $treasury,
    ) {}

    public function handle(
        Supplier $supplier,
        int $amountCents,
        TreasuryAccount $account,
        ?PurchaseOrder $purchaseOrder = null,
        ?string $reference = null,
        ?string $note = null,
        ?int $userId = null,
    ): SupplierTransaction {
        if ($amountCents <= 0) {
            throw ValidationException::withMessages(['amount' => __('The amount must be more than zero.')]);
        }

        if ($purchaseOrder !== null && $purchaseOrder->supplier_id !== $supplier->id) {
            throw ValidationException::withMessages(['purchase_order_id' => __('That purchase order belongs to another supplier.')]);
        }

        return DB::transaction(function () use ($supplier, $amountCents, $account, $purchaseOrder, $reference, $note, $userId): SupplierTransaction {
            // One payment at a time per supplier.
            $supplier = Supplier::query()->lockForUpdate()->findOrFail($supplier->id);

            // The money has to be there to leave.
            if ($amountCents > $this->treasury->balances()[$account->value]) {
                throw ValidationException::withMessages([
                    'amount' => __('That is more than the :account holds (:balance).', [
                        'account' => $account->label(),
                        'balance' => Money::format($this->treasury->balances()[$account->value]),
                    ]),
                ]);
            }

            $payment = SupplierTransaction::create([
                'supplier_id' => $supplier->id,
                'type' => SupplierTransactionType::Payment,
                'amount_cents' => -$amountCents,
                'purchase_order_id' => $purchaseOrder?->id,
                'account' => $account,
                'reference' => $reference,
                'note' => $note,
                'created_by' => $userId,
            ]);

            $this->recordTreasuryMovement->handle(
                direction: TreasuryDirection::Out,
                account: $account,
                amountCents: $amountCents,
                source: $payment,
                reference: $reference,
                note: trim(__('Payment to supplier :name', ['name' => $supplier->company_name]).' '.($note ?? '')),
                userId: $userId,
            );

            activity()
                ->performedOn($supplier)
                ->withProperties(['amount_cents' => $amountCents, 'account' => $account->value, 'purchase_order_id' => $purchaseOrder?->id])
                ->event('supplier_payment')
                ->log("Paid supplier {$supplier->company_name}: {$amountCents} cents ({$account->value})");

            return $payment;
        });
    }
}
