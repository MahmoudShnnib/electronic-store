<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the product shipping/dimension fields. Nothing in the store ever read
 * them for shipping — they only filled a form section — so they go with it.
 *
 * The create migration no longer adds these columns, hence the per-column
 * guard: on a fresh install there is nothing here to drop.
 */
return new class extends Migration
{
    private const COLUMNS = ['warranty_months', 'weight_grams', 'length_mm', 'width_mm', 'height_mm'];

    public function up(): void
    {
        $present = array_values(array_filter(
            self::COLUMNS,
            fn (string $column): bool => Schema::hasColumn('products', $column),
        ));

        if ($present === []) {
            return;
        }

        Schema::table('products', function (Blueprint $table) use ($present): void {
            $table->dropColumn($present);
        });
    }

    public function down(): void
    {
        // One-way: the form section these backed no longer exists.
    }
};
