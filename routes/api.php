<?php

use App\Http\Controllers\Api\V1;
use App\Http\Middleware\AuthenticateIfTokenPresent;
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
    Route::post('auth/register', V1\Auth\RegisterController::class)->middleware('throttle:auth')->name('auth.register');
    Route::post('auth/login', V1\Auth\LoginController::class)->middleware('throttle:auth')->name('auth.login');

    // Public catalog
    Route::get('products', [V1\ProductController::class, 'index'])->name('products.index');
    Route::get('products/{product:slug}', [V1\ProductController::class, 'show'])->name('products.show');
    Route::get('categories', [V1\CategoryController::class, 'index'])->name('categories.index');
    Route::get('brands', [V1\BrandController::class, 'index'])->name('brands.index');

    // Cart and checkout: guest via X-Cart-Token, or a customer via Bearer token.
    Route::middleware(AuthenticateIfTokenPresent::class)->group(function (): void {
        Route::get('cart', [V1\CartController::class, 'show'])->name('cart.show');
        Route::post('cart/items', [V1\CartController::class, 'addItem'])->name('cart.items.store');
        Route::patch('cart/items/{item}', [V1\CartController::class, 'updateItem'])->name('cart.items.update');
        Route::delete('cart/items/{item}', [V1\CartController::class, 'removeItem'])->name('cart.items.destroy');
        Route::post('cart/coupon', [V1\CartCouponController::class, 'store'])->name('cart.coupon.apply');
        Route::delete('cart/coupon', [V1\CartCouponController::class, 'destroy'])->name('cart.coupon.remove');

        // Checkout — allowed for guests and users alike; heavily rate limited +
        // idempotent via the Idempotency-Key header.
        Route::post('checkout', [V1\CheckoutController::class, 'store'])->middleware('throttle:checkout')->name('checkout');
    });

    // Authenticated
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('auth/logout', V1\Auth\LogoutController::class)->name('auth.logout');
        Route::post('auth/logout-all', V1\Auth\LogoutAllController::class)->name('auth.logout-all');
        Route::get('auth/user', V1\Auth\MeController::class)->name('auth.user');
        Route::patch('auth/user', V1\Auth\UpdateProfileController::class)->name('auth.user.update');

        Route::get('orders', [V1\OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [V1\OrderController::class, 'show'])->name('orders.show');
        Route::post('orders/{order}/cancel', V1\CancelOrderController::class)->name('orders.cancel');

        Route::get('addresses', [V1\AddressController::class, 'index'])->name('addresses.index');
        Route::post('addresses', [V1\AddressController::class, 'store'])->name('addresses.store');
        Route::patch('addresses/{address}', [V1\AddressController::class, 'update'])->name('addresses.update');
        Route::delete('addresses/{address}', [V1\AddressController::class, 'destroy'])->name('addresses.destroy');

        Route::get('wishlist', [V1\WishlistController::class, 'index'])->name('wishlist.index');
        Route::post('wishlist/{product}', [V1\WishlistController::class, 'store'])->name('wishlist.store');
        Route::delete('wishlist/{product}', [V1\WishlistController::class, 'destroy'])->name('wishlist.destroy');

        Route::post('reviews', [V1\ReviewController::class, 'store'])->name('reviews.store');

    });
});
