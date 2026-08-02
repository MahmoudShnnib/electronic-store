<?php

use App\Http\Controllers\Admin;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:access-admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/', Admin\DashboardController::class)->name('dashboard');

        // Catalog
        Route::resource('products', Admin\ProductController::class)->except(['show']);
        Route::post('products/{product}/restore', [Admin\ProductController::class, 'restore'])
            ->withTrashed()->name('products.restore');
        Route::post('products/bulk', [Admin\ProductController::class, 'bulk'])->name('products.bulk');
        Route::post('products/{product}/images', [Admin\ProductController::class, 'storeImages'])->name('products.images.store');
        Route::delete('products/{product}/images/{image}', [Admin\ProductController::class, 'destroyImage'])->name('products.images.destroy');

        Route::resource('categories', Admin\CategoryController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('brands', Admin\BrandController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('specifications', Admin\SpecificationController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['specifications' => 'attribute']);
        Route::post('specification-groups', [Admin\SpecificationController::class, 'storeGroup'])->name('specification-groups.store');
        Route::patch('specification-groups/{group}', [Admin\SpecificationController::class, 'updateGroup'])->name('specification-groups.update');
        Route::delete('specification-groups/{group}', [Admin\SpecificationController::class, 'destroyGroup'])->name('specification-groups.destroy');

        // Inventory
        Route::resource('warehouses', Admin\WarehouseController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('inventory', [Admin\InventoryController::class, 'index'])->name('inventory.index');
        Route::get('inventory/movements', [Admin\InventoryController::class, 'movements'])->name('inventory.movements');
        Route::post('inventory/adjust', [Admin\InventoryController::class, 'adjust'])->name('inventory.adjust');
        Route::post('inventory/transfer', [Admin\InventoryController::class, 'transfer'])->name('inventory.transfer');

        // Purchasing
        Route::resource('suppliers', Admin\SupplierController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('purchase-orders', Admin\PurchaseOrderController::class)->except(['edit', 'update'])
            ->parameters(['purchase-orders' => 'purchaseOrder']);
        Route::patch('purchase-orders/{purchaseOrder}', [Admin\PurchaseOrderController::class, 'update'])->name('purchase-orders.update');
        Route::post('purchase-orders/{purchaseOrder}/send', [Admin\PurchaseOrderController::class, 'send'])->name('purchase-orders.send');
        Route::post('purchase-orders/{purchaseOrder}/receive', [Admin\PurchaseOrderController::class, 'receive'])->name('purchase-orders.receive');
        Route::post('purchase-orders/{purchaseOrder}/cancel', [Admin\PurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel');

        // Orders
        Route::get('orders', [Admin\OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [Admin\OrderController::class, 'show'])->name('orders.show');
        Route::post('orders/{order}/status', [Admin\OrderController::class, 'updateStatus'])->name('orders.status');
        Route::post('orders/{order}/note', [Admin\OrderController::class, 'updateNote'])->name('orders.note');
        Route::post('orders/{order}/payments', [Admin\OrderController::class, 'confirmPayment'])->name('orders.payments.store');
        Route::get('orders/{order}/invoice', [Admin\OrderController::class, 'invoice'])->name('orders.invoice');

        // Returns & refunds
        Route::get('returns', [Admin\ReturnController::class, 'index'])->name('returns.index');
        Route::get('returns/{productReturn}', [Admin\ReturnController::class, 'show'])->name('returns.show');
        Route::post('returns/{productReturn}/review', [Admin\ReturnController::class, 'review'])->name('returns.review');
        Route::post('returns/{productReturn}/receive', [Admin\ReturnController::class, 'receive'])->name('returns.receive');
        Route::get('refunds', [Admin\RefundController::class, 'index'])->name('refunds.index');
        Route::post('orders/{order}/refunds', [Admin\RefundController::class, 'store'])->name('refunds.store');

        // Customers & engagement
        Route::get('customers', [Admin\CustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/{customer}', [Admin\CustomerController::class, 'show'])->name('customers.show');
        Route::post('customers/{customer}/toggle-active', [Admin\CustomerController::class, 'toggleActive'])->name('customers.toggle-active');

        Route::get('reviews', [Admin\ReviewController::class, 'index'])->name('reviews.index');
        Route::post('reviews/{review}/moderate', [Admin\ReviewController::class, 'moderate'])->name('reviews.moderate');

        Route::resource('coupons', Admin\CouponController::class)->only(['index', 'store', 'update', 'destroy']);

        // Reports
        Route::get('reports', [Admin\ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/sales', [Admin\ReportController::class, 'sales'])->name('reports.sales');
        Route::get('reports/inventory', [Admin\ReportController::class, 'inventory'])->name('reports.inventory');
        Route::get('reports/export/{report}', [Admin\ReportController::class, 'export'])->name('reports.export');

        // Platform
        Route::resource('users', Admin\UserController::class)->only(['index', 'store', 'update']);
        Route::get('roles', [Admin\RoleController::class, 'index'])->name('roles.index');
        Route::post('roles', [Admin\RoleController::class, 'store'])->name('roles.store');
        Route::patch('roles/{role}', [Admin\RoleController::class, 'update'])->name('roles.update');
        Route::delete('roles/{role}', [Admin\RoleController::class, 'destroy'])->name('roles.destroy');

        Route::get('audit-logs', [Admin\AuditLogController::class, 'index'])->name('audit-logs.index');

        Route::get('settings', [Admin\SettingController::class, 'index'])->name('settings.index');
        Route::post('settings', [Admin\SettingController::class, 'update'])->name('settings.update');
    });
