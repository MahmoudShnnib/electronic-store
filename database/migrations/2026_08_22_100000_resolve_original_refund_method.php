<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `refunds.method` used to accept an 'original' placeholder meaning "however
 * the customer paid". It read badly in the refunds list — a row said "original
 * payment method" instead of the channel the money actually went back through,
 * and answering that meant opening the order.
 *
 * The placeholder is gone from the enum; this resolves the rows that still
 * carry it against their order's payment method, which is exactly what
 * TreasuryAccount::fromRefundMethod() did at the time the money moved, so no
 * ledger row changes meaning.
 */
return new class extends Migration
{
    /** Order payment method → the refund channel it resolves to. */
    private const RESOLUTION = [
        'cod' => 'cash',
        'bank_transfer' => 'bank_transfer',
    ];

    public function up(): void
    {
        foreach (self::RESOLUTION as $paymentMethod => $refundMethod) {
            DB::table('refunds')
                ->where('method', 'original')
                ->whereIn('order_id', DB::table('orders')->where('payment_method', $paymentMethod)->pluck('id'))
                ->update(['method' => $refundMethod]);
        }

        // An order whose payment method is not in the map above (none exist
        // today) would otherwise keep a value the enum can no longer cast.
        DB::table('refunds')->where('method', 'original')->update(['method' => 'cash']);
    }

    public function down(): void
    {
        // The original rows are indistinguishable from ones deliberately
        // issued as cash or bank transfer, so this is not reversible.
    }
};
