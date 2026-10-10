<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FIFO costing: every arrival of stock keeps the price paid for it, sales use up
 * the oldest units first, and each order line remembers the costs it took.
 *
 * Existing stock becomes one batch per stock row at today's cost; existing order
 * lines get their snapshot cost (or today's cost where none was taken) as one slice.
 * Run with the app down: old code writing stock between this and the deploy would
 * leave rows without batches (invariant S10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_level_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('unit_cost_cents');
            $table->integer('quantity_received');
            $table->integer('quantity_remaining');
            $table->timestamp('received_at');
            $table->timestamps();

            $table->index(['stock_level_id', 'received_at', 'id']);
        });

        DB::statement('ALTER TABLE stock_batches ADD CONSTRAINT stock_batches_quantities_check
            CHECK (quantity_received > 0 AND quantity_remaining >= 0 AND quantity_remaining <= quantity_received)');

        Schema::table('order_items', function (Blueprint $table): void {
            $table->bigInteger('cost_cents')->nullable()->after('unit_cost_cents');
            $table->jsonb('cost_layers')->nullable()->after('cost_cents');
        });

        $now = now();

        DB::statement('
            INSERT INTO stock_batches (stock_level_id, unit_cost_cents, quantity_received, quantity_remaining, received_at, created_at, updated_at)
            SELECT stock_levels.id, COALESCE(NULLIF(product_variants.cost_price_cents, 0), products.cost_price_cents, 0),
                   stock_levels.quantity, stock_levels.quantity, ?, ?, ?
            FROM stock_levels
            JOIN products ON products.id = stock_levels.product_id
            LEFT JOIN product_variants ON product_variants.id = stock_levels.product_variant_id
            WHERE stock_levels.quantity > 0
        ', [$now, $now, $now]);

        DB::statement("
            UPDATE order_items SET
                unit_cost_cents = c.unit_cost,
                cost_cents = c.unit_cost * order_items.quantity,
                cost_layers = jsonb_build_array(jsonb_build_object('cost', c.unit_cost, 'qty', order_items.quantity, 'received_at', c.placed_at))
            FROM (
                SELECT order_items.id,
                       COALESCE(order_items.unit_cost_cents, NULLIF(product_variants.cost_price_cents, 0), products.cost_price_cents, 0) AS unit_cost,
                       to_char(orders.created_at, 'YYYY-MM-DD\"T\"HH24:MI:SS\"Z\"') AS placed_at
                FROM order_items
                JOIN orders ON orders.id = order_items.order_id
                LEFT JOIN products ON products.id = order_items.product_id
                LEFT JOIN product_variants ON product_variants.id = order_items.product_variant_id
            ) c
            WHERE c.id = order_items.id
        ");

        Schema::table('order_items', function (Blueprint $table): void {
            $table->bigInteger('cost_cents')->nullable(false)->change();
            $table->jsonb('cost_layers')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn(['cost_cents', 'cost_layers']);
        });

        Schema::dropIfExists('stock_batches');
    }
};
