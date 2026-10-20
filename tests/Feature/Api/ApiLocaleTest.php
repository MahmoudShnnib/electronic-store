<?php

use App\Models\Product;

// Laravel's test client sends `Accept-Language: en-us` unless told otherwise
// (a Symfony default), so each test states the language it means, the way the
// storefront always does.

it('answers in Arabic by default and in the requested language', function (): void {
    $this->withHeader('Accept-Language', '')
        ->postJson('/api/v1/cart/coupon', ['code' => 'NOPE'])
        ->assertUnprocessable()
        ->assertHeader('Content-Language', 'ar')
        ->assertJsonPath('errors.coupon.0', __('This coupon code is not valid.', locale: 'ar'));

    $this->withHeader('Accept-Language', 'en-US,en;q=0.9')
        ->postJson('/api/v1/cart/coupon', ['code' => 'NOPE'])
        ->assertHeader('Content-Language', 'en')
        ->assertJsonPath('errors.coupon.0', 'This coupon code is not valid.');

    // ?locale beats the header.
    $this->withHeader('Accept-Language', 'en')
        ->postJson('/api/v1/cart/coupon?locale=ar', ['code' => 'NOPE'])
        ->assertHeader('Content-Language', 'ar');
});

it('ignores a language the store does not offer', function (): void {
    $this->withHeader('Accept-Language', 'fr')->getJson('/api/v1/storefront')->assertHeader('Content-Language', 'ar');
    $this->withHeader('Accept-Language', '')->getJson('/api/v1/storefront?locale=xx')->assertHeader('Content-Language', 'ar');
});

it('translates failed sign-ins and validation', function (): void {
    $this->withHeader('Accept-Language', 'ar');

    $this->postJson('/api/v1/auth/login', ['email' => 'nobody@example.com', 'password' => 'wrong-pass', 'device_name' => 'x'])
        ->assertJsonPath('errors.email.0', 'البريد الإلكتروني أو كلمة المرور غير صحيحة.');

    $this->postJson('/api/v1/auth/register', [])
        ->assertJsonPath('errors.email.0', 'حقل البريد الإلكتروني مطلوب.');
});

it('localises cart line names', function (): void {
    $product = Product::factory()->create(['name' => 'Phone', 'name_ar' => 'هاتف']);
    giveStock($product, 3);

    $this->withHeader('Accept-Language', 'ar-LY,ar;q=0.9')
        ->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 1])
        ->assertJsonPath('data.items.0.name', 'هاتف');
});

it('answers in JSON even without an Accept header', function (): void {
    $this->get('/api/v1/orders')->assertUnauthorized()->assertJsonStructure(['message']);

    $this->post('/api/v1/auth/login', [])->assertUnprocessable()->assertJsonStructure(['message', 'errors' => ['email']]);
});
