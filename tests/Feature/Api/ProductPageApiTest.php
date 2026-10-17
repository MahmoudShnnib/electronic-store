<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReviewStatus;
use App\Models\Category;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\Setting;
use App\Models\User;
use App\Support\ReferenceNumber;
use Laravel\Sanctum\Sanctum;

/** A completed order of the customer's containing the product — what makes a review verified. */
function completedPurchase(User $user, Product $product): OrderItem
{
    $address = ['name' => $user->name, 'phone' => '0910000000', 'country' => 'LY', 'city' => 'Tripoli'];

    $order = Order::create([
        'number' => ReferenceNumber::generate('ORD'),
        'user_id' => $user->id,
        'status' => OrderStatus::Completed,
        'payment_status' => PaymentStatus::Paid,
        'payment_method' => PaymentMethod::CashOnDelivery,
        'customer' => ['name' => $user->name, 'email' => $user->email],
        'shipping_address' => $address,
        'billing_address' => $address,
        'subtotal_cents' => $product->price_cents,
        'total_cents' => $product->price_cents,
        'currency' => 'LYD',
        'placed_via' => 'web',
    ]);

    return OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'sku' => $product->sku,
        'unit_price_cents' => $product->price_cents,
        'unit_cost_cents' => $product->cost_price_cents,
        'cost_cents' => $product->cost_price_cents,
        'cost_layers' => [['cost' => $product->cost_price_cents, 'qty' => 1, 'received_at' => now()->toIso8601String()]],
        'quantity' => 1,
        'line_total_cents' => $product->price_cents,
    ]);
}

it('lists related products from the same category first', function (): void {
    $parent = Category::factory()->create();
    $mice = Category::factory()->create(['parent_id' => $parent->id]);
    $keyboards = Category::factory()->create(['parent_id' => $parent->id]);

    $product = Product::factory()->create(['category_id' => $mice->id]);
    $sibling = Product::factory()->create(['category_id' => $mice->id, 'published_at' => now()->subYear()]);
    $cousin = Product::factory()->create(['category_id' => $keyboards->id]);
    Product::factory()->create(); // unrelated category

    $ids = collect($this->getJson("/api/v1/products/{$product->slug}/related")->assertOk()->json('data'))->pluck('id');

    // The older same-category product still comes before the newer cousin.
    expect($ids->all())->toBe([$sibling->id, $cousin->id]);
});

it('suggests products and categories for a search term', function (): void {
    Product::factory()->create(['name' => 'Gaming Mouse Pro']);
    Category::factory()->create(['name' => 'Gaming Chairs', 'name_ar' => 'كراسي ألعاب']);

    $this->getJson('/api/v1/search/suggest?q=g')->assertOk()->assertJsonCount(0, 'data.products');

    $this->getJson('/api/v1/search/suggest?q=gaming')
        ->assertOk()
        ->assertJsonPath('data.products.0.name', 'Gaming Mouse Pro')
        ->assertJsonFragment(['name' => 'Gaming Chairs']);
});

it('reports stock per variant and the Arabic text on the product detail', function (): void {
    $product = Product::factory()->withVariants()->create([
        'short_description_ar' => 'وصف قصير',
        'description_ar' => 'وصف كامل',
    ]);
    [$black, $silver] = ProductVariant::factory()->count(2)->create(['product_id' => $product->id]);
    giveStock($product, 4, $black);

    $variants = collect($this->getJson("/api/v1/products/{$product->slug}")
        ->assertOk()
        ->assertJsonPath('data.short_description_ar', 'وصف قصير')
        ->assertJsonPath('data.description_ar', 'وصف كامل')
        ->assertJsonPath('data.available', 4)
        ->json('data.variants'))->keyBy('id');

    expect($variants[$black->id]['available'])->toBe(4)
        ->and($variants[$silver->id]['available'])->toBe(0);
});

it('lists approved reviews with a summary and hides pending ones', function (): void {
    $product = Product::factory()->create();
    $author = customer();

    Review::create(['user_id' => $author->id, 'product_id' => $product->id, 'rating' => 5, 'status' => ReviewStatus::Approved]);
    Review::create(['user_id' => customer()->id, 'product_id' => $product->id, 'rating' => 3, 'status' => ReviewStatus::Approved]);
    Review::create(['user_id' => customer()->id, 'product_id' => $product->id, 'rating' => 1, 'status' => ReviewStatus::Pending]);

    $this->getJson("/api/v1/products/{$product->slug}/reviews")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('summary.count', 2)
        ->assertJsonPath('summary.avg', 4)
        ->assertJsonPath('summary.distribution.5', 1)
        ->assertJsonPath('summary.distribution.1', 0)
        ->assertJsonPath('can_review', false)
        ->assertJsonPath('review_eligibility', 'guest');
});

it('tells a signed-in customer whether they may review', function (): void {
    $product = Product::factory()->create();
    $buyer = customer();

    Sanctum::actingAs($buyer);
    $this->getJson("/api/v1/products/{$product->slug}/reviews")->assertJsonPath('review_eligibility', 'not_purchased');

    completedPurchase($buyer, $product);
    $this->getJson("/api/v1/products/{$product->slug}/reviews")->assertJsonPath('can_review', true);

    Review::create(['user_id' => $buyer->id, 'product_id' => $product->id, 'rating' => 4, 'status' => ReviewStatus::Pending]);
    $this->getJson("/api/v1/products/{$product->slug}/reviews")->assertJsonPath('review_eligibility', 'already_reviewed');
});

it('still accepts a review from a verified buyer through the API', function (): void {
    $product = Product::factory()->create();
    $buyer = customer();
    $line = completedPurchase($buyer, $product);

    Sanctum::actingAs($buyer);
    $this->postJson('/api/v1/reviews', ['product_id' => $product->id, 'rating' => 5])->assertCreated();

    expect(Review::query()->sole()->order_item_id)->toBe($line->id);

    $this->postJson('/api/v1/reviews', ['product_id' => $product->id, 'rating' => 4])->assertUnprocessable();
});

it('carries the store name and logo in the boot payload', function (): void {
    Setting::set('store_name', 'Tech Corner');

    $this->getJson('/api/v1/storefront')
        ->assertOk()
        ->assertJsonPath('data.store.name', 'Tech Corner')
        ->assertJsonPath('data.store.logo', asset('logo.svg'));
});

it('subscribes to the newsletter once and replies in the requested language', function (): void {
    $this->postJson('/api/v1/newsletter?locale=ar', ['email' => 'Fan@Example.com'])
        ->assertCreated()
        ->assertJsonPath('message', __('You are subscribed — deals land in your inbox first.', locale: 'ar'));

    $this->postJson('/api/v1/newsletter', ['email' => 'fan@example.com'])->assertOk();
    $this->postJson('/api/v1/newsletter', ['email' => 'not-an-email'])->assertUnprocessable();

    expect(NewsletterSubscriber::query()->sole())
        ->email->toBe('fan@example.com')
        ->locale->toBe('ar');
});
