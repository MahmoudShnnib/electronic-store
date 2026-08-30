<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The customer-facing RMA workflow is gone: returns are handled straight from
 * the order screen (and later the POS). This drops the tables behind it.
 *
 * `refunds` stays — refunds outlived returns and no longer point at one — so
 * only its `return_id` foreign key goes with them. On a fresh install the
 * create-migration never made these, hence the existence guards.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('refunds', 'return_id')) {
            Schema::table('refunds', function (Blueprint $table): void {
                $table->dropForeign(['return_id']);
                $table->dropColumn('return_id');
            });
        }

        Schema::dropIfExists('return_items');
        Schema::dropIfExists('returns');
    }

    public function down(): void
    {
        // One-way: the RMA workflow it belonged to no longer exists in the app.
    }
};
