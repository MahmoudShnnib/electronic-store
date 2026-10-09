<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The ledger recorded only changes to sellable `quantity`, so units leaving the
 * damaged count (a supplier return "from damaged") left no trace of how many.
 * Every movement now records its damaged delta too.
 *
 * Backfill: a `damaged` write-off always moved exactly its units into damaged.
 * Older supplier returns from damaged cannot be reconstructed and stay at 0.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->integer('damaged_change')->default(0)->after('quantity_after');
        });

        DB::table('inventory_movements')->where('type', 'damaged')->update(['damaged_change' => DB::raw('-quantity_change')]);
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->dropColumn('damaged_change');
        });
    }
};
