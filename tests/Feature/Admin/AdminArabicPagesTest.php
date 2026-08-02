<?php

declare(strict_types=1);

use App\Actions\Cart\AddToCart;
use App\Actions\Checkout\PlaceOrder;
use App\DTOs\CheckoutData;
use App\Enums\PaymentMethod;
use App\Models\Brand;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

/**
 * Walks every string in the Inertia props of the two densest admin screens and
 * flags anything still English — server-rendered labels the Vue layer cannot fix.
 */
function englishStrings(array $node, string $path = ''): array
{
    // Values that are legitimately Latin: codes, slugs, emails, money, dates, enum keys.
    $ignore = '/^[\d\s.,:%+\-\/()#]*$/u';
    $found = [];

    foreach ($node as $key => $value) {
        $here = $path === '' ? (string) $key : "{$path}.{$key}";

        if (is_array($value)) {
            $found = [...$found, ...englishStrings($value, $here)];

            continue;
        }

        if (! is_string($value) || $value === '' || preg_match($ignore, $value)) {
            continue;
        }

        // Only *label*-ish keys matter; names, SKUs and emails are user data.
        if (! preg_match('/label$/i', (string) $key)) {
            continue;
        }

        if (preg_match('/[A-Za-z]{3,}/', $value)) {
            $found[$here] = $value;
        }
    }

    return $found;
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create(['locale' => 'ar']);
    $this->admin->syncRoles(['super_admin']);
});

it('sends no English labels to the product edit screen', function (): void {
    $product = Product::factory()->create([
        'brand_id' => Brand::factory()->create()->id,
        'category_id' => Category::factory()->create()->id,
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.products.edit', $product))
        ->assertOk()
        ->assertInertia(function ($page) {
            $leaks = englishStrings($page->toArray()['props']);

            expect($leaks)->toBe([], 'English labels leaked: '.json_encode($leaks, JSON_UNESCAPED_UNICODE));
        });
});

it('sends no English labels to the order detail screen', function (): void {
    $buyer = customer();
    $product = Product::factory()->create();
    giveStock($product, 5);

    $cart = Cart::create(['user_id' => $buyer->id]);
    app(AddToCart::class)->handle($cart, $product, null, 2);

    $order = app(PlaceOrder::class)->handle($cart, new CheckoutData(
        customer: ['name' => $buyer->name, 'email' => $buyer->email, 'phone' => '091'],
        shippingAddress: ['name' => $buyer->name, 'phone' => '091', 'country' => 'LY', 'city' => 'Tripoli', 'street' => 'X'],
        billingAddress: ['name' => $buyer->name, 'phone' => '091', 'country' => 'LY', 'city' => 'Tripoli', 'street' => 'X'],
        paymentMethod: PaymentMethod::CashOnDelivery,
    ), $buyer);

    $this->actingAs($this->admin)
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertInertia(function ($page) {
            $leaks = englishStrings($page->toArray()['props']);

            expect($leaks)->toBe([], 'English labels leaked: '.json_encode($leaks, JSON_UNESCAPED_UNICODE));
        });
});
