<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What one unit cost the shop when it was sold, so profit reports don't
 * change every time a cost price is edited later. Null on lines sold before
 * this column existed; reports fall back to the current cost for those.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('unit_cost_cents')->nullable()->after('unit_price_cents');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn('unit_cost_cents');
        });
    }
};
