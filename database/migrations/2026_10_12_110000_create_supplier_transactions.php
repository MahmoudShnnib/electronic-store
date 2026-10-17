<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The account kept for each supplier: what the shop owes them. A supplier is not
 * a user and never signs in — this is the shop's own ledger of the debt.
 *
 * amount_cents is signed from the shop's side: positive raises what we owe
 * (goods received, a debt written in by hand), negative lowers it (a payment, a
 * credit). The balance is always the sum; no running total is stored.
 *
 * Purchase orders received before this table existed are not backfilled — whether
 * they were paid is not recorded anywhere. An opening balance (an adjustment)
 * carries any old debt in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('type'); // purchase | payment | adjustment
            $table->bigInteger('amount_cents');
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            // Where a payment came from (cash or bank); null for everything else.
            $table->string('account')->nullable();
            $table->string('reference')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['supplier_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_transactions');
    }
};
