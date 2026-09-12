<?php

use App\Http\Controllers\Admin;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:access-admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/', Admin\DashboardController::class)->name('dashboard');

        // Catalog
        Route::resource('products', Admin\Products\ProductController::class)->except(['show']);
        Route::post('products/{product}/restore', Admin\Products\RestoreProductController::class)
            ->withTrashed()->name('products.restore');
        Route::post('products/bulk', Admin\Products\BulkProductActionController::class)->name('products.bulk');
        Route::post('products/{product}/images', [Admin\Products\ProductImageController::class, 'store'])->name('products.images.store');
        Route::delete('products/{product}/images/{image}', [Admin\Products\ProductImageController::class, 'destroy'])->name('products.images.destroy');

        Route::resource('categories', Admin\CategoryController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('brands', Admin\BrandController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('specifications', Admin\Specifications\SpecificationController::class)->name('specifications.index');
        Route::post('specifications', [Admin\Specifications\SpecificationAttributeController::class, 'store'])->name('specifications.store');
        Route::match(['put', 'patch'], 'specifications/{attribute}', [Admin\Specifications\SpecificationAttributeController::class, 'update'])->name('specifications.update');
        Route::delete('specifications/{attribute}', [Admin\Specifications\SpecificationAttributeController::class, 'destroy'])->name('specifications.destroy');
        Route::post('specification-groups', [Admin\Specifications\SpecificationGroupController::class, 'store'])->name('specification-groups.store');
        Route::patch('specification-groups/{group}', [Admin\Specifications\SpecificationGroupController::class, 'update'])->name('specification-groups.update');
        Route::delete('specification-groups/{group}', [Admin\Specifications\SpecificationGroupController::class, 'destroy'])->name('specification-groups.destroy');

        // Inventory
        Route::resource('warehouses', Admin\WarehouseController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('inventory', [Admin\Inventory\InventoryController::class, 'index'])->name('inventory.index');
        Route::get('inventory/movements', [Admin\Inventory\InventoryController::class, 'movements'])->name('inventory.movements');
        Route::post('inventory/adjust', Admin\Inventory\AdjustStockController::class)->name('inventory.adjust');
        Route::post('inventory/transfer', Admin\Inventory\TransferStockController::class)->name('inventory.transfer');

        // Purchasing
        Route::resource('suppliers', Admin\SupplierController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('purchase-orders', Admin\PurchaseOrders\PurchaseOrderController::class)->except(['edit', 'update'])
            ->parameters(['purchase-orders' => 'purchaseOrder']);
        Route::get('purchase-orders/{purchaseOrder}/edit', [Admin\PurchaseOrders\PurchaseOrderController::class, 'edit'])->name('purchase-orders.edit');
        Route::patch('purchase-orders/{purchaseOrder}', [Admin\PurchaseOrders\PurchaseOrderController::class, 'update'])->name('purchase-orders.update');
        Route::post('purchase-orders/{purchaseOrder}/send', Admin\PurchaseOrders\SendPurchaseOrderController::class)->name('purchase-orders.send');
        Route::get('purchase-orders/{purchaseOrder}/print', Admin\PurchaseOrders\PrintPurchaseOrderController::class)->name('purchase-orders.print');
        Route::post('purchase-orders/{purchaseOrder}/receive', Admin\PurchaseOrders\ReceivePurchaseOrderController::class)->name('purchase-orders.receive');
        Route::post('purchase-orders/{purchaseOrder}/cancel', Admin\PurchaseOrders\CancelPurchaseOrderController::class)->name('purchase-orders.cancel');

        // Orders
        Route::get('orders', [Admin\Orders\OrderController::class, 'index'])->name('orders.index');
        // Before the {order} route, or "create" is read as an order id.
        Route::get('orders/create', [Admin\Orders\ManualOrderController::class, 'create'])->name('orders.create');
        Route::post('orders', [Admin\Orders\ManualOrderController::class, 'store'])->name('orders.store');
        Route::get('orders/{order}', [Admin\Orders\OrderController::class, 'show'])->name('orders.show');
        Route::post('orders/{order}/status', Admin\Orders\UpdateOrderStatusController::class)->name('orders.status');
        Route::post('orders/{order}/payments', Admin\Orders\ConfirmOrderPaymentController::class)->name('orders.payments.store');
        Route::get('orders/{order}/invoice', Admin\Orders\OrderInvoiceController::class)->name('orders.invoice');
        Route::get('orders/{order}/slip', Admin\Orders\OrderDeliverySlipController::class)->name('orders.slip');
        Route::post('orders/{order}/slip/print', Admin\Orders\PrintDeliverySlipController::class)->name('orders.slip.print');

        // Point of sale
        Route::get('pos', Admin\Pos\RegisterController::class)->name('pos.register');
        Route::post('pos/shifts', [Admin\Pos\ShiftController::class, 'store'])->name('pos.shifts.store');
        Route::get('pos/shifts/close', [Admin\Pos\ShiftController::class, 'open'])->name('pos.shifts.close');
        Route::post('pos/shifts/close', [Admin\Pos\ShiftController::class, 'close'])->name('pos.shifts.closed');
        Route::get('pos/shifts', [Admin\Pos\ShiftController::class, 'index'])->name('pos.shifts.index');
        Route::post('pos/sales', Admin\Pos\CompleteSaleController::class)->name('pos.sales.store');
        Route::get('pos/sales/{order}/receipt', Admin\Pos\ReceiptController::class)->name('pos.receipt');
        Route::post('pos/sales/{order}/receipt/print', Admin\Pos\PrintReceiptController::class)->name('pos.receipt.print');

        // Refunds
        Route::get('refunds', [Admin\Refunds\RefundController::class, 'index'])->name('refunds.index');
        Route::post('orders/{order}/refunds', Admin\Refunds\StoreRefundController::class)->name('refunds.store');
        Route::post('orders/{order}/issue', Admin\Orders\ResolveOrderIssueController::class)->name('orders.issue');

        // Treasury
        Route::get('treasury', [Admin\Treasury\TreasuryController::class, 'index'])->name('treasury.index');
        Route::post('treasury', Admin\Treasury\StoreTreasuryTransactionController::class)->name('treasury.store');

        // Customers & engagement
        Route::get('customers', [Admin\Customers\CustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/{customer}', [Admin\Customers\CustomerController::class, 'show'])->name('customers.show');
        Route::post('customers/{customer}/toggle-active', Admin\Customers\ToggleCustomerActiveController::class)->name('customers.toggle-active');

        Route::get('reviews', [Admin\Reviews\ReviewController::class, 'index'])->name('reviews.index');
        Route::post('reviews/{review}/moderate', Admin\Reviews\ModerateReviewController::class)->name('reviews.moderate');

        Route::resource('coupons', Admin\CouponController::class)->only(['index', 'store', 'update', 'destroy']);

        // Reports
        Route::get('reports', Admin\Reports\SalesReportController::class)->name('reports.index');
        Route::get('reports/sales', Admin\Reports\SalesReportController::class)->name('reports.sales');
        Route::get('reports/invoices', Admin\Reports\InvoiceReportController::class)->name('reports.invoices');
        Route::get('reports/inventory', Admin\Reports\InventoryReportController::class)->name('reports.inventory');
        Route::get('reports/export/{report}', Admin\Reports\ExportReportController::class)->name('reports.export');

        // Platform
        Route::resource('users', Admin\UserController::class)->only(['index', 'store', 'update']);
        Route::get('roles', [Admin\RoleController::class, 'index'])->name('roles.index');
        Route::post('roles', [Admin\RoleController::class, 'store'])->name('roles.store');
        Route::patch('roles/{role}', [Admin\RoleController::class, 'update'])->name('roles.update');
        Route::delete('roles/{role}', [Admin\RoleController::class, 'destroy'])->name('roles.destroy');

        Route::get('audit-logs', [Admin\AuditLogController::class, 'index'])->name('audit-logs.index');

        Route::get('settings', [Admin\SettingController::class, 'index'])->name('settings.index');
        Route::post('settings', [Admin\SettingController::class, 'update'])->name('settings.update');
        Route::post('settings/receipt-logo', [Admin\Settings\ReceiptLogoController::class, 'store'])->name('settings.receipt-logo.store');
        Route::delete('settings/receipt-logo', [Admin\Settings\ReceiptLogoController::class, 'destroy'])->name('settings.receipt-logo.destroy');
    });
