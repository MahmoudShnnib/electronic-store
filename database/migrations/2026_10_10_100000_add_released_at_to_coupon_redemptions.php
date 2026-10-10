<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cancelling an order used to delete its coupon redemption. The use is now kept
 * as history and marked released, so it stops counting against the coupon's
 * limits without losing the record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupon_redemptions', function (Blueprint $table): void {
            $table->timestamp('released_at')->nullable()->after('amount_cents');
        });
    }

    public function down(): void
    {
        Schema::table('coupon_redemptions', function (Blueprint $table): void {
            $table->dropColumn('released_at');
        });
    }
};
