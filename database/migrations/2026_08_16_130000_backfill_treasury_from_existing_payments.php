<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The treasury ledger was added after money had already changed hands. Every
 * confirmed payment and completed refund that predates it left no row behind,
 * so a refunded old order showed as a negative balance — the money going out
 * was recorded while the money that came in was not.
 *
 * This posts the missing legs once. The unique index on (source_type, source_id)
 * makes it safe to re-run: rows that already exist are skipped, not duplicated.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $payments = DB::table('payments')
            ->join('orders', 'orders.id', '=', 'payments.order_id')
            ->where('payments.status', 'confirmed')
            ->whereNotExists(fn ($q) => $q
                ->select(DB::raw(1))
                ->from('treasury_transactions')
                ->whereColumn('treasury_transactions.source_id', 'payments.id')
                ->where('treasury_transactions.source_type', 'App\Models\Payment'))
            ->get([
                'payments.id', 'payments.order_id', 'payments.amount_cents',
                'payments.reference', 'payments.confirmed_by', 'payments.created_at',
                'orders.payment_method', 'orders.currency', 'orders.number',
            ]);

        foreach ($payments as $payment) {
            DB::table('treasury_transactions')->insert([
                'direction' => 'in',
                'account' => $payment->payment_method === 'bank_transfer' ? 'bank' : 'cash',
                'amount_cents' => $payment->amount_cents,
                'currency' => $payment->currency,
                'source_type' => 'App\Models\Payment',
                'source_id' => $payment->id,
                'order_id' => $payment->order_id,
                'reference' => $payment->reference,
                'note' => "Payment for order {$payment->number}",
                'created_by' => $payment->confirmed_by,
                'created_at' => $payment->created_at ?? $now,
                'updated_at' => $now,
            ]);
        }

        $refunds = DB::table('refunds')
            ->join('orders', 'orders.id', '=', 'refunds.order_id')
            ->where('refunds.status', 'completed')
            // Store credit never leaves the treasury, so it has no ledger row.
            ->where('refunds.method', '!=', 'store_credit')
            ->whereNotExists(fn ($q) => $q
                ->select(DB::raw(1))
                ->from('treasury_transactions')
                ->whereColumn('treasury_transactions.source_id', 'refunds.id')
                ->where('treasury_transactions.source_type', 'App\Models\Refund'))
            ->get([
                'refunds.id', 'refunds.order_id', 'refunds.amount_cents', 'refunds.method',
                'refunds.reference', 'refunds.reason', 'refunds.created_by', 'refunds.created_at',
                'orders.payment_method', 'orders.currency', 'orders.number',
            ]);

        foreach ($refunds as $refund) {
            $method = $refund->method === 'original' ? $refund->payment_method : $refund->method;

            DB::table('treasury_transactions')->insert([
                'direction' => 'out',
                'account' => $method === 'bank_transfer' ? 'bank' : 'cash',
                'amount_cents' => $refund->amount_cents,
                'currency' => $refund->currency,
                'source_type' => 'App\Models\Refund',
                'source_id' => $refund->id,
                'order_id' => $refund->order_id,
                'reference' => $refund->reference,
                'note' => $refund->reason ?? "Refund for order {$refund->number}",
                'created_by' => $refund->created_by,
                'created_at' => $refund->created_at ?? $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Historical postings are indistinguishable from live ones once written;
        // dropping the table (the previous migration) is the way back.
    }
};
