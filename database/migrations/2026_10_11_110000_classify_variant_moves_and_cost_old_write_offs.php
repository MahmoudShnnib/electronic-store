<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Two fixes for the write-off report:
 *  - Stock moved from a product onto its variants used to be recorded as two
 *    "correction" rows; it is not a gain or a loss, so those rows get their own
 *    type (variant_split), recognised by the reasons SaveProduct always wrote.
 *  - Write-offs recorded before movements carried a cost are costed the way stock
 *    was valued then: the variant's cost, else the product's.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('inventory_movements')
            ->where('type', 'correction')
            ->whereIn('reason', ['Moved to variants when variants were added', 'Moved from product-level stock when variants were added'])
            ->update(['type' => 'variant_split']);

        DB::statement("
            UPDATE inventory_movements SET cost_cents = -quantity_change * COALESCE(
                NULLIF((SELECT cost_price_cents FROM product_variants WHERE product_variants.id = inventory_movements.product_variant_id), 0),
                (SELECT cost_price_cents FROM products WHERE products.id = inventory_movements.product_id),
                0)
            WHERE cost_cents IS NULL
              AND quantity_change < 0
              AND type IN ('damaged', 'lost', 'manual_decrease', 'supplier_return', 'correction')
        ");
    }

    public function down(): void
    {
        DB::table('inventory_movements')->where('type', 'variant_split')->update(['type' => 'correction']);
    }
};
