<?php

use App\Http\Controllers\Api\V1;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| REST API v1 — consumed by the future mobile application.
| Auth: Sanctum personal access tokens (Authorization: Bearer TOKEN).
| Guests identify their cart via the X-Cart-Token header.
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->name('api.v1.')->middleware('throttle:api')->group(function (): void {
    // Auth
    Route::post('auth/register', [V1\AuthController::class, 'register'])->middleware('throttle:auth')->name('auth.register');
    Route::post('auth/login', [V1\AuthController::class, 'login'])->middleware('throttle:auth')->name('auth.login');

    // Public catalog
    Route::get('products', [V1\ProductController::class, 'index'])->name('products.index');
    Route::get('products/{product:slug}', [V1\ProductController::class, 'show'])->name('products.show');
    Route::get('categories', [V1\CatalogController::class, 'categories'])->name('categories.index');
    Route::get('brands', [V1\CatalogController::class, 'brands'])->name('brands.index');

    // Cart (guest via X-Cart-Token, or authenticated)
    Route::get('cart', [V1\CartController::class, 'show'])->name('cart.show');
    Route::post('cart/items', [V1\CartController::class, 'addItem'])->name('cart.items.store');
    Route::patch('cart/items/{item}', [V1\CartController::class, 'updateItem'])->name('cart.items.update');
    Route::delete('cart/items/{item}', [V1\CartController::class, 'removeItem'])->name('cart.items.destroy');
    Route::post('cart/coupon', [V1\CartController::class, 'applyCoupon'])->name('cart.coupon.apply');
    Route::delete('cart/coupon', [V1\CartController::class, 'removeCoupon'])->name('cart.coupon.remove');

    // Checkout — allowed for guests and users alike; heavily rate limited +
    // idempotent via the Idempotency-Key header.
    Route::post('checkout', [V1\CheckoutController::class, 'store'])->middleware('throttle:checkout')->name('checkout');

    // Authenticated
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('auth/logout', [V1\AuthController::class, 'logout'])->name('auth.logout');
        Route::post('auth/logout-all', [V1\AuthController::class, 'logoutAll'])->name('auth.logout-all');
        Route::get('auth/user', [V1\AuthController::class, 'user'])->name('auth.user');
        Route::patch('auth/user', [V1\AuthController::class, 'updateProfile'])->name('auth.user.update');

        Route::get('orders', [V1\OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [V1\OrderController::class, 'show'])->name('orders.show');
        Route::post('orders/{order}/cancel', [V1\OrderController::class, 'cancel'])->name('orders.cancel');

        Route::get('addresses', [V1\AddressController::class, 'index'])->name('addresses.index');
        Route::post('addresses', [V1\AddressController::class, 'store'])->name('addresses.store');
        Route::patch('addresses/{address}', [V1\AddressController::class, 'update'])->name('addresses.update');
        Route::delete('addresses/{address}', [V1\AddressController::class, 'destroy'])->name('addresses.destroy');

        Route::get('wishlist', [V1\WishlistController::class, 'index'])->name('wishlist.index');
        Route::post('wishlist/{product}', [V1\WishlistController::class, 'store'])->name('wishlist.store');
        Route::delete('wishlist/{product}', [V1\WishlistController::class, 'destroy'])->name('wishlist.destroy');

        Route::post('reviews', [V1\ReviewController::class, 'store'])->name('reviews.store');

        Route::get('returns', [V1\ReturnController::class, 'index'])->name('returns.index');
        Route::post('returns', [V1\ReturnController::class, 'store'])->name('returns.store');
    });
});
