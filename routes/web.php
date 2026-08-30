<?php

use App\Http\Controllers\Web;
use Illuminate\Support\Facades\Route;

// -- Storefront ---------------------------------------------------------------

Route::get('/', Web\HomeController::class)->name('home');

Route::get('/products', [Web\CatalogController::class, 'index'])->name('catalog.index');
Route::get('/category/{category:slug}', [Web\CatalogController::class, 'category'])->name('catalog.category');
Route::get('/brand/{brand:slug}', [Web\CatalogController::class, 'brand'])->name('catalog.brand');
Route::get('/products/{product:slug}', [Web\ProductController::class, 'show'])->name('products.show');

Route::get('/search/suggest', [Web\SearchController::class, 'suggest'])
    ->middleware('throttle:search')
    ->name('search.suggest');

Route::get('/cart', [Web\CartController::class, 'show'])->name('cart.show');
Route::post('/cart/items', [Web\CartController::class, 'add'])->name('cart.add');
Route::patch('/cart/items/{item}', [Web\CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/items/{item}', [Web\CartController::class, 'remove'])->name('cart.remove');
Route::post('/cart/coupon', [Web\CartCouponController::class, 'store'])->name('cart.coupon.apply');
Route::delete('/cart/coupon', [Web\CartCouponController::class, 'destroy'])->name('cart.coupon.remove');

Route::get('/checkout', [Web\CheckoutController::class, 'show'])->name('checkout.show');
Route::post('/checkout', [Web\CheckoutController::class, 'store'])
    ->middleware('throttle:checkout')
    ->name('checkout.store');
Route::get('/checkout/confirmation/{number}', [Web\CheckoutController::class, 'confirmation'])
    ->name('checkout.confirmation');

Route::post('/locale', [Web\LocaleController::class, 'update'])->name('locale.update');

// Post-login landing (referenced by the auth scaffolding): staff go to the
// admin dashboard, customers to their orders.
Route::get('/dashboard', fn () => auth()->user()->isStaff()
    ? redirect()->route('admin.dashboard')
    : redirect()->route('account.orders.index'))
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('/pages/{page}', [Web\StaticPageController::class, 'show'])->name('pages.show');

// -- Authenticated storefront -------------------------------------------------

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/wishlist', [Web\WishlistController::class, 'index'])->name('wishlist.index');
    Route::post('/wishlist/{product}', Web\ToggleWishlistController::class)->name('wishlist.toggle');

    Route::post('/products/{product:slug}/reviews', [Web\ReviewController::class, 'store'])->name('reviews.store');

    Route::prefix('account')->name('account.')->group(function (): void {
        Route::get('/orders', [Web\Account\OrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [Web\Account\OrderController::class, 'show'])->name('orders.show');
        Route::post('/orders/{order}/cancel', Web\Account\CancelOrderController::class)->name('orders.cancel');

        Route::get('/addresses', [Web\Account\AddressController::class, 'index'])->name('addresses.index');
        Route::post('/addresses', [Web\Account\AddressController::class, 'store'])->name('addresses.store');
        Route::patch('/addresses/{address}', [Web\Account\AddressController::class, 'update'])->name('addresses.update');
        Route::delete('/addresses/{address}', [Web\Account\AddressController::class, 'destroy'])->name('addresses.destroy');

    });
});

require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
