<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keeps the discount a cashier keyed in by hand apart from the coupon, with
 * the reason they gave. `discount_cents` stays the order's whole discount
 * (coupon + manual); this is the manual part of it.
 *
 * Old register sales without a coupon can only have had a manual discount, so
 * they are backfilled. Sales that had both cannot be split after the fact and
 * are left at 0.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('manual_discount_cents')->default(0)->after('discount_cents');
            $table->string('manual_discount_reason')->nullable()->after('manual_discount_cents');
        });

        DB::table('orders')
            ->where('placed_via', 'pos')
            ->whereNull('coupon_code')
            ->where('discount_cents', '>', 0)
            ->update(['manual_discount_cents' => DB::raw('discount_cents')]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['manual_discount_cents', 'manual_discount_reason']);
        });
    }
};
