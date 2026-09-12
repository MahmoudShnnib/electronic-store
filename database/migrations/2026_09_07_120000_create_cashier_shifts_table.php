<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A cashier's session at a register: who, which warehouse the goods leave
 * from, what was in the drawer at open, and what was physically counted at
 * close. The counted-vs-expected gap is stored rather than recomputed so a
 * later refund or correction cannot quietly rewrite a signed-off shift.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashier_shifts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained();
            $table->string('status', 16)->default('open');
            $table->unsignedBigInteger('opening_float_cents')->default(0);
            // Null until the shift is closed; all three are a snapshot of the
            // count, not a live calculation.
            $table->unsignedBigInteger('expected_cash_cents')->nullable();
            $table->unsignedBigInteger('counted_cash_cents')->nullable();
            $table->bigInteger('difference_cents')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            // "Does this cashier already have a drawer open?" is the hot query.
            $table->index(['user_id', 'status']);
            $table->index(['warehouse_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashier_shifts');
    }
};
