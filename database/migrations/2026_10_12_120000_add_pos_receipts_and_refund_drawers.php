<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Counter sales become their own thing (owner's call, 2026-10-12):
 *
 * - Each branch numbers its receipts in one gapless run (POS-MAIN-000127). The
 *   counter lives on the warehouse row and is bumped under its lock inside the
 *   sale's transaction, so a sale that fails gives its number back and two tills
 *   can never take the same one. Sales rung up before this keep their old numbers.
 * - A refund records which drawer the cash left from. Returns now happen at the
 *   till, and a drawer's expected cash counts exactly the refunds paid out of it —
 *   before, one refund could be taken off two drawers at once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table): void {
            $table->unsignedBigInteger('pos_receipt_seq')->default(0);
        });

        Schema::table('refunds', function (Blueprint $table): void {
            $table->foreignId('cashier_shift_id')->nullable()->after('order_id')->constrained()->nullOnDelete();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->index(['placed_via', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['placed_via', 'created_at']);
        });

        Schema::table('refunds', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cashier_shift_id');
        });

        Schema::table('warehouses', function (Blueprint $table): void {
            $table->dropColumn('pos_receipt_seq');
        });
    }
};
