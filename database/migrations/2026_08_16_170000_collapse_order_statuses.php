<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Collapses the ten-step order pipeline into four states.
 *
 * `delivered` maps to in_progress, not completed: completing an order now also
 * collects the outstanding balance into the treasury, and a raw UPDATE cannot
 * do that. Leaving those orders in progress lets the admin press "Completed"
 * so the money is booked properly.
 *
 * Only the `orders` table is rewritten. `order_status_histories` keeps its
 * original values — that is the audit trail of what actually happened, and
 * OrderStatusHistory reads them as plain strings so old labels still render.
 */
return new class extends Migration
{
    private const MAP = [
        'pending' => 'in_progress',
        'confirmed' => 'in_progress',
        'processing' => 'in_progress',
        'ready_for_shipping' => 'in_progress',
        'shipped' => 'in_progress',
        'delivered' => 'in_progress',
        'returned' => 'refunded',
    ];

    public function up(): void
    {
        foreach (self::MAP as $from => $to) {
            DB::table('orders')->where('status', $from)->update(['status' => $to]);
        }
    }

    public function down(): void
    {
        // One-way: the intermediate states no longer exist in the enum.
    }
};
