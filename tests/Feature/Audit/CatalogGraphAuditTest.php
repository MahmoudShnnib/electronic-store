<?php

/*
 * Audit 2026-10-01 — catalog graph and how it reaches carts, coupons, the
 * storefront, the API and the home cache. Each test asserts the CORRECT
 * business behaviour, so a failing test here is a live bug.
 */

use App\Actions\Cart\AddToCart;
use App\Actions\Cart\CalculateCartTotals;
use App\Actions\Checkout\PlaceOrder;
use App\Actions\Inventory\AdjustStock;
use App\Actions\Orders\UpdateOrderStatus;
use App\Actions\Pos\CompleteSale;
use App\Actions\Reviews\SubmitReview;
use App\DTOs\CheckoutData;
use App\Enums\CouponType;
use App\Enums\MovementType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PriceType;
use App\Enums\ProductStatus;
use App\Models\Brand;
use App\Models\Cart;
use App\Models\CashierShift;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\User;
use App\Services\Storefront\HomePageService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

beforeEach(fn () => Notification::fake());

function catCheckout(Cart $cart, ?User $user): Order
{
    return app(PlaceOrder::class)->handle($cart, new CheckoutData(
        customer: ['name' => 'C', 'email' => 'c@example.test', 'phone' => '091'],
        shippingAddress: ['name' => 'C', 'phone' => '091', 'country' => 'LY', 'city' => 'Tripoli'],
        billingAddress: ['name' => 'C', 'phone' => '091', 'country' => 'LY', 'city' => 'Tripoli'],
        paymentMethod: PaymentMethod::CashOnDelivery,
    ), $user);
}

/** Minimal valid admin product payload. */
function catPayload(Product $product, array $overrides = []): array
{
    return [
        'name' => $product->name, 'sku' => $product->sku, 'category_id' => $product->category_id,
        'cost_price' => 10, 'price' => 20, 'status' => 'active', ...$overrides,
    ];
}

function catCardsOf(string $section): array
{
    $props = app(HomePageService::class)->props();

    return collect($props[$section])->map(fn ($r) => $r->resolve(request()))->all();
}

// --- Category tree ----------------------------------------------------------

it('CAT-1: a coupon scoped to a parent category discounts products filed in its subcategory', function (): void {
    $phones = Category::factory()->create();
    $smartphones = Category::factory()->create(['parent_id' => $phones->id]);
    $product = Product::factory()->create(['category_id' => $smartphones->id, 'price_cents' => 100_000]);
    giveStock($product, 5);

    $coupon = Coupon::factory()->create([
        'type' => CouponType::Percentage, 'percent' => 10, 'value_cents' => null,
        'applies_to' => 'categories', 'applies_to_ids' => [$phones->id],
    ]);

    $cart = Cart::create(['user_id' => customer()->id]);
    app(AddToCart::class)->handle($cart, $product, null, 1);
    $cart->update(['coupon_id' => $coupon->id]);

    $totals = app(CalculateCartTotals::class)->handle($cart->refresh());

    expect($totals->couponError)->toBeNull()
        ->and($totals->discountCents)->toBe(10_000);
});

it('CAT-2: a root category page lists every product its home tile counts (3-level tree after reparenting)', function (): void {
    $electronics = Category::factory()->create(['position' => 0]);
    $audio = Category::factory()->create(['position' => 1]);
    $headphones = Category::factory()->create(['parent_id' => $audio->id]);
    Product::factory()->count(3)->create(['category_id' => $headphones->id]);

    // Admin moves "Audio" (with its child) under "Electronics" — the UI offers every root as a parent.
    $this->actingAs(staff('admin'))->put(route('admin.categories.update', $audio), [
        'name' => $audio->name, 'slug' => $audio->slug, 'parent_id' => $electronics->id, 'is_active' => true,
    ])->assertSessionHasNoErrors();

    $tile = collect(app(HomePageService::class)->categoryCards())->firstWhere('id', $electronics->id);
    expect($tile['products_count'])->toBe(3);

    $this->get(route('catalog.category', $electronics))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('products.data', 3));
});

it('CAT-3: a category cannot be made the parent of its own parent (A→B→A cycle)', function (): void {
    $a = Category::factory()->create();
    $b = Category::factory()->create(['parent_id' => $a->id]);

    $this->actingAs(staff('admin'))->put(route('admin.categories.update', $a), [
        'name' => $a->name, 'slug' => $a->slug, 'parent_id' => $b->id, 'is_active' => true,
    ])->assertSessionHasErrors('parent_id');

    expect($a->refresh()->parent_id)->toBeNull();
});

it('CAT-4: creating a category whose name matches an existing one (slug left blank) is a validation error, not a 500', function (): void {
    Category::factory()->create(['name' => 'Phones', 'slug' => 'phones']);

    $this->actingAs(staff('admin'))
        ->post(route('admin.categories.store'), ['name' => 'Phones', 'is_active' => true])
        ->assertSessionHasErrors();
});

it('CAT-5: creating a brand whose name matches a deleted one (slug left blank) is a validation error, not a 500', function (): void {
    Brand::factory()->create(['name' => 'Acme', 'slug' => 'acme'])->delete();

    $this->actingAs(staff('admin'))
        ->post(route('admin.brands.store'), ['name' => 'Acme', 'is_active' => true])
        ->assertSessionHasErrors();
});

it('CAT-6: restoring a trashed product never leaves it in a deleted category', function (): void {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id]);
    $admin = staff('admin');

    $this->actingAs($admin)->delete(route('admin.products.destroy', $product));
    // The "has products" guard ignores trashed products, so this goes through.
    $this->actingAs($admin)->delete(route('admin.categories.destroy', $category));
    $this->actingAs($admin)->post(route('admin.products.restore', $product));

    $product = Product::query()->findOrFail($product->id);
    expect($product->category)->not->toBeNull();
});

it('CAT-7: a product cannot be assigned to a soft-deleted category', function (): void {
    $product = Product::factory()->create();
    $dead = Category::factory()->create();
    $dead->delete();

    $this->actingAs(staff('admin'))
        ->put(route('admin.products.update', $product), catPayload($product, ['category_id' => $dead->id]))
        ->assertSessionHasErrors('category_id');
});

it('CAT-8: the API category filter includes products filed in subcategories', function (): void {
    $phones = Category::factory()->create();
    $smartphones = Category::factory()->create(['parent_id' => $phones->id]);
    Product::factory()->count(2)->create(['category_id' => $smartphones->id]);

    $this->getJson('/api/v1/products?category='.$phones->slug)
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('CAT-9: deactivating a category hides its products from the storefront listing and home page', function (): void {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id, 'is_featured' => true]);
    $category->update(['is_active' => false]);

    $this->get(route('catalog.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('products.data', 0));

    expect(collect(catCardsOf('featured'))->pluck('id'))->not->toContain($product->id);
});

// --- Variants / stock flags -------------------------------------------------

it('CAT-10: a product whose only variant is inactive is not advertised as "in stock"', function (): void {
    $product = Product::factory()->create(['has_variants' => true]);
    $variant = $product->variants()->create(['name' => 'Red', 'sku' => 'CAT10-RED', 'price_cents' => 2_000, 'is_active' => true]);
    giveStock($product, 5, $variant);
    $variant->update(['is_active' => false]);

    $this->get(route('catalog.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('products.data.0.in_stock', false));
});

it('CAT-11: a variant SKU may not reuse another product\'s SKU', function (): void {
    $other = Product::factory()->create(['sku' => 'TAKEN-001']);
    $product = Product::factory()->create();

    $this->actingAs(staff('admin'))
        ->put(route('admin.products.update', $product), catPayload($product, [
            'variants' => [['name' => 'Red', 'sku' => 'TAKEN-001', 'price' => 20]],
        ]))
        ->assertSessionHasErrors('variants.0.sku');
});

it('CAT-12: two variants with identical attribute combinations are rejected', function (): void {
    $product = Product::factory()->create();

    $this->actingAs(staff('admin'))
        ->put(route('admin.products.update', $product), catPayload($product, [
            'variants' => [
                ['name' => 'Red A', 'sku' => 'CAT12-A', 'price' => 20, 'attributes' => ['color' => 'Red']],
                ['name' => 'Red B', 'sku' => 'CAT12-B', 'price' => 25, 'attributes' => ['color' => 'Red']],
            ],
        ]))
        ->assertSessionHasErrors();
});

// --- Price history ----------------------------------------------------------

it('CAT-13: changing a variant sale price is recorded in price history', function (): void {
    $product = Product::factory()->create();
    $admin = staff('admin');
    $this->actingAs($admin)->put(route('admin.products.update', $product), catPayload($product, [
        'variants' => [['name' => 'Red', 'sku' => 'CAT13-RED', 'price' => 20]],
    ]))->assertSessionHasNoErrors();
    $variant = $product->variants()->sole();

    $this->actingAs($admin)->put(route('admin.products.update', $product), catPayload($product, [
        'variants' => [['id' => $variant->id, 'name' => 'Red', 'sku' => 'CAT13-RED', 'price' => 20, 'sale_price' => 5]],
    ]))->assertSessionHasNoErrors();

    expect(PriceHistory::where('product_variant_id', $variant->id)->where('price_type', PriceType::Sale)->exists())->toBeTrue();
});

it('CAT-14: ending a product sale (clearing the sale price) is recorded in price history', function (): void {
    $product = Product::factory()->create(['price_cents' => 20_000, 'sale_price_cents' => 15_000, 'cost_price_cents' => 10_000]);

    $this->actingAs(staff('admin'))
        ->put(route('admin.products.update', $product), catPayload($product, ['price' => 200, 'cost_price' => 100]))
        ->assertSessionHasNoErrors();

    expect($product->refresh()->sale_price_cents)->toBeNull()
        ->and(PriceHistory::where('product_id', $product->id)->where('price_type', PriceType::Sale)->exists())->toBeTrue();
});

it('CAT-15: a sale with only an end date ("sale until Friday") is accepted', function (): void {
    $product = Product::factory()->create();

    $this->actingAs(staff('admin'))
        ->put(route('admin.products.update', $product), catPayload($product, [
            'sale_price' => 15, 'sale_ends_at' => now()->addDays(3)->toDateTimeString(),
        ]))
        ->assertSessionHasNoErrors();
});

// --- Home cache -------------------------------------------------------------

it('CAT-16: the home page stops showing "in stock" once the last unit sells', function (): void {
    $product = Product::factory()->create(['is_featured' => true]);
    giveStock($product, 1);
    expect(catCardsOf('featured')[0]['in_stock'])->toBeTrue(); // warms the cache

    app(AdjustStock::class)->handle(defaultWarehouse(), $product, null, -1, MovementType::Correction, 'sold out');

    expect(catCardsOf('featured')[0]['in_stock'])->toBeFalse();
});

it('CAT-17: the home page drops a product as soon as it is deactivated or deleted, and shows new prices', function (): void {
    $a = Product::factory()->create(['is_featured' => true, 'price_cents' => 10_000]);
    $b = Product::factory()->create(['is_featured' => true]);
    $c = Product::factory()->create(['is_featured' => true]);
    catCardsOf('featured');

    $a->update(['price_cents' => 12_345]);
    $b->update(['status' => ProductStatus::Inactive]);
    $c->delete();

    $cards = collect(catCardsOf('featured'));
    expect($cards->pluck('id')->all())->toBe([$a->id])
        ->and($cards->first()['price_cents'])->toBe(12_345);
});

// --- Status gates (expected green) ------------------------------------------

it('CAT-18: an inactive/draft product is unreachable by URL, API, cart, POS and checkout', function (): void {
    $product = Product::factory()->create();
    giveStock($product, 5);
    $cart = Cart::create(['user_id' => ($u = customer())->id]);
    app(AddToCart::class)->handle($cart, $product, null, 1);

    $product->update(['status' => ProductStatus::Draft]);

    $this->get(route('products.show', $product))->assertNotFound();
    $this->getJson('/api/v1/products/'.$product->slug)->assertNotFound();
    expect(fn () => app(AddToCart::class)->handle(Cart::create([]), $product, null, 1))->toThrow(ValidationException::class);
    expect(fn () => catCheckout($cart->refresh(), $u))->toThrow(ValidationException::class);

    $shift = CashierShift::create([
        'user_id' => staff('cashier')->id, 'warehouse_id' => defaultWarehouse()->id,
        'opening_float_cents' => 0, 'opened_at' => now(), 'status' => 'open',
    ]);
    expect(fn () => app(CompleteSale::class)->handle($shift, [['product_id' => $product->id, 'quantity' => 1]]))
        ->toThrow(ValidationException::class);
});

it('CAT-19: reviews need a completed purchase and are limited to one per customer', function (): void {
    $product = Product::factory()->create();
    giveStock($product, 5);
    $u = customer();

    expect(fn () => app(SubmitReview::class)->handle($u, $product, 5, 't', 'b'))->toThrow(ValidationException::class);

    $cart = Cart::create(['user_id' => $u->id]);
    app(AddToCart::class)->handle($cart, $product, null, 1);
    $order = catCheckout($cart, $u);
    app(UpdateOrderStatus::class)->handle($order, OrderStatus::Completed, 'done', staff('admin')->id);

    app(SubmitReview::class)->handle($u, $product, 5, 't', 'b');
    expect(fn () => app(SubmitReview::class)->handle($u, $product, 1, 't', 'b'))->toThrow(ValidationException::class);
});

it('CAT-20: bulk actions are refused for a role without products.update/delete', function (): void {
    $product = Product::factory()->create();

    $this->actingAs(staff('cashier'))
        ->post(route('admin.products.bulk'), ['ids' => [$product->id], 'action' => 'delete'])
        ->assertForbidden();
    $this->actingAs(staff('cashier'))
        ->post(route('admin.products.bulk'), ['ids' => [$product->id], 'action' => 'deactivate'])
        ->assertForbidden();

    expect(Product::find($product->id)?->status)->toBe(ProductStatus::Active);
});
