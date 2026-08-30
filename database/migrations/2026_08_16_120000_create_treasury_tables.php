<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_transactions', function (Blueprint $table): void {
            $table->id();
            $table->string('direction', 8);          // in | out
            $table->string('account', 16);           // cash | bank
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3)->default((string) config('store.currency', 'LYD'));

            // The document that caused the movement: a Payment, a Refund, or
            // nothing at all for a manual deposit/withdrawal.
            $table->nullableMorphs('source');
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();

            $table->string('reference')->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['account', 'direction']);
            $table->index('created_at');

            // One movement per source document — replaying a payment or refund
            // must never double-post to the treasury.
            $table->unique(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_transactions');
    }
};
