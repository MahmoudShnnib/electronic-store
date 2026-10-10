<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the units of each stock movement cost (from the FIFO batches they left or
 * joined), so write-offs — damaged, lost, missing — can be reported in money.
 * Rows written before this migration have no cost (null).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->bigInteger('cost_cents')->nullable()->after('damaged_change');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->dropColumn('cost_cents');
        });
    }
};
