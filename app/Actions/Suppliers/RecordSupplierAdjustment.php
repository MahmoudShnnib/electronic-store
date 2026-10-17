<?php

declare(strict_types=1);

namespace App\Actions\Suppliers;

use App\Enums\SupplierTransactionType;
use App\Models\Supplier;
use App\Models\SupplierTransaction;
use Illuminate\Validation\ValidationException;

/**
 * A debt or credit written into a supplier's account by hand, with no goods and
 * no money moving through the shop: an opening balance carried in from before
 * the system ("we already owe him 500"), a credit note for goods sent back, a
 * correction. It never touches stock or the treasury, so it must say why.
 */
final class RecordSupplierAdjustment
{
    /**
     * @param  int  $amountCents  positive: we owe more; negative: we owe less
     */
    public function handle(Supplier $supplier, int $amountCents, string $note, ?int $userId = null): SupplierTransaction
    {
        if ($amountCents === 0) {
            throw ValidationException::withMessages(['amount' => __('The amount must be more than zero.')]);
        }

        if (trim($note) === '') {
            throw ValidationException::withMessages(['note' => __('Say what this is for.')]);
        }

        $adjustment = SupplierTransaction::create([
            'supplier_id' => $supplier->id,
            'type' => SupplierTransactionType::Adjustment,
            'amount_cents' => $amountCents,
            'note' => trim($note),
            'created_by' => $userId,
        ]);

        activity()
            ->performedOn($supplier)
            ->withProperties(['amount_cents' => $amountCents, 'note' => trim($note)])
            ->event('supplier_adjustment')
            ->log("Supplier account adjusted for {$supplier->company_name}: {$amountCents} cents");

        return $adjustment;
    }
}
