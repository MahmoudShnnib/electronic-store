<?php

use App\Actions\Cart\AddToCart;
use App\Actions\Checkout\PlaceOrder;
use App\Actions\Orders\UpdateOrderStatus;
use App\DTOs\CheckoutData;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReviewStatus;
use App\Models\Cart;
use App\Models\Product;
use App\Models\Review;
use Inertia\Testing\AssertableInertia as Assert;

it('renders the home page', function (): void {
    Product::factory()->featured()->count(2)->create();

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('storefront/Home')
            ->has('featured.data', 2));
});

it('renders the catalog with search and filters', function (): void {
    Product::factory()->create(['name' => 'Gaming Laptop Alpha']);
    Product::factory()->create(['name' => 'Office Mouse']);

    $this->get('/products?q=laptop')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('storefront/Catalog')
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Gaming Laptop Alpha'));
});

it('hides draft and inactive products from the storefront', function (): void {
    $draft = Product::factory()->draft()->create();

    $this->get("/products/{$draft->slug}")->assertNotFound();

    $this->get('/products')
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 0));
});

it('shows a product with specs and variants', function (): void {
    $product = Product::factory()->create();

    $this->get("/products/{$product->slug}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('storefront/Product')
            ->where('product.slug', $product->slug)
            ->has('specifications')
            ->has('related'));
});

it('adds to cart as a guest and merges after login', function (): void {
    $product = Product::factory()->create();
    giveStock($product, 5);

    $response = $this->post('/cart/items', ['product_id' => $product->id, 'quantity' => 2]);
    $response->assertRedirect();

    $token = collect($response->headers->getCookies())
        ->first(fn ($c) => $c->getName() === 'guest_cart_token')
        ?->getValue();

    expect($token)->not->toBeEmpty();

    $user = customer(['password' => 'secret-password-1']);

    app(\App\Actions\Cart\MergeGuestCart::class)->handle($token, $user);

    expect($user->carts()->sole()->items()->sole()->quantity)->toBe(2);
});

it('validates stock when adding to cart', function (): void {
    $product = Product::factory()->create();
    giveStock($product, 1);

    $this->post('/cart/items', ['product_id' => $product->id, 'quantity' => 3])
        ->assertSessionHasErrors('quantity');
});

it('allows reviews only for verified purchasers and moderates them', function (): void {
    $product = Product::factory()->create();
    giveStock($product, 5);
    $buyer = customer();
    $lurker = customer();

    // Lurker never bought it.
    $this->actingAs($lurker)
        ->post("/products/{$product->slug}/reviews", ['rating' => 5])
        ->assertSessionHasErrors();

    // Buyer with a delivered order can review.
    $cart = Cart::create(['user_id' => $buyer->id]);
    app(AddToCart::class)->handle($cart, $product, null, 1);
    $order = app(PlaceOrder::class)->handle($cart, new CheckoutData(
        customer: ['name' => 'B', 'email' => $buyer->email, 'phone' => '091'],
        shippingAddress: ['name' => 'B', 'phone' => '091', 'country' => 'LY', 'city' => 'T', 'street' => 'S'],
        billingAddress: ['name' => 'B', 'phone' => '091', 'country' => 'LY', 'city' => 'T', 'street' => 'S'],
        paymentMethod: PaymentMethod::CashOnDelivery,
    ), $buyer);

    $action = app(UpdateOrderStatus::class);
    foreach ([OrderStatus::Confirmed, OrderStatus::Processing, OrderStatus::ReadyForShipping, OrderStatus::Shipped, OrderStatus::Delivered] as $status) {
        $order = $action->handle($order, $status);
    }

    $this->actingAs($buyer)
        ->post("/products/{$product->slug}/reviews", ['rating' => 4, 'title' => 'Solid', 'body' => 'Works great'])
        ->assertRedirect();

    $review = Review::query()->sole();
    expect($review->status)->toBe(ReviewStatus::Pending)
        ->and($review->isVerifiedPurchase())->toBeTrue();

    // Pending review is not visible on the product page.
    $this->get("/products/{$product->slug}")
        ->assertInertia(fn (Assert $page) => $page->where('ratingSummary.count', 0));

    staff('super_admin');
    $this->actingAs(\App\Models\User::role('super_admin')->first())
        ->post(route('admin.reviews.moderate', $review), ['status' => 'approved'])
        ->assertRedirect();

    $this->get("/products/{$product->slug}")
        ->assertInertia(fn (Assert $page) => $page->where('ratingSummary.count', 1));
});

it('switches locale and persists it', function (): void {
    $this->post('/locale', ['locale' => 'ar'])->assertRedirect();

    $this->get('/')
        ->assertInertia(fn (Assert $page) => $page->where('locale', 'ar'));
});
