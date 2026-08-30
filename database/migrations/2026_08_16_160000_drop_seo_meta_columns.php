<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the SEO meta fields from products and categories. The storefront now
 * titles a product page from the product's own name, so nothing reads them.
 *
 * The create migration no longer adds these columns, hence the guards: on a
 * fresh install there is nothing here to drop.
 */
return new class extends Migration
{
    private const COLUMNS = ['meta_title', 'meta_description'];

    public function up(): void
    {
        foreach (['products', 'categories'] as $table) {
            $present = array_values(array_filter(
                self::COLUMNS,
                fn (string $column): bool => Schema::hasColumn($table, $column),
            ));

            if ($present === []) {
                continue;
            }

            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn($present));
        }
    }

    public function down(): void
    {
        // One-way: the form section these backed no longer exists.
    }
};
