<?php

use App\Models\Product;
use App\Models\ProductVariant;

it('stops applying the sale price once the end date has passed', function (): void {
    $product = Product::factory()->create([
        'price_cents' => 100_00,
        'sale_price_cents' => 60_00,
        'sale_ends_at' => now()->subHour(),
    ]);

    expect($product->saleIsActive())->toBeFalse()
        ->and($product->currentPriceCents())->toBe(100_00);
});

it('does not apply the sale price before it starts', function (): void {
    $product = Product::factory()->create([
        'price_cents' => 100_00,
        'sale_price_cents' => 60_00,
        'sale_starts_at' => now()->addDay(),
    ]);

    expect($product->saleIsActive())->toBeFalse()
        ->and($product->currentPriceCents())->toBe(100_00);
});

it('persists the end date through the product form', function (): void {
    $product = Product::factory()->create(['price_cents' => 100_00]);
    $endsAt = now()->addDays(3)->startOfMinute();

    $this->actingAs(staff('admin'))
        ->patch(route('admin.products.update', $product), [
            'name' => $product->name,
            'sku' => $product->sku,
            'category_id' => $product->category_id,
            'cost_price' => 50,
            'price' => 100,
            'sale_price' => 60,
            'sale_ends_at' => $endsAt->format('Y-m-d\TH:i'),
            'status' => $product->status->value,
        ])
        ->assertSessionHasNoErrors();

    expect($product->refresh()->sale_ends_at?->format('Y-m-d H:i'))->toBe($endsAt->format('Y-m-d H:i'));
});

// -- The variant leg of the same window --------------------------------------

it('honours the parent sale window for a variant sale price', function (): void {
    $product = Product::factory()->create([
        'price_cents' => 100_00,
        'sale_price_cents' => 60_00,
        'sale_ends_at' => now()->subHour(),
        'has_variants' => true,
    ]);

    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'price_cents' => 120_00,
        'sale_price_cents' => 80_00,
    ]);

    // The sale ended, so the variant must fall back to its regular price too.
    expect($variant->currentPriceCents())->toBe(120_00);
});
