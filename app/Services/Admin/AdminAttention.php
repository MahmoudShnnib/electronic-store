<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\ReviewStatus;
use App\Models\CashierShift;
use App\Models\Order;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Review;
use App\Models\StockLevel;
use App\Models\User;

/**
 * What is waiting on someone. The dashboard's "Needs attention" tiles and the
 * sidebar's count badges both read these numbers, so a badge can never
 * disagree with the tile it echoes.
 *
 * An item is only computed for staff holding the permission its list needs,
 * and its link opens that list already filtered by the same rule the count
 * uses — the figure and the rows it opens on are one query.
 */
final class AdminAttention
{
    /**
     * @return list<array{key: string, count: int, href: string, tone: string}>
     */
    public function for(User $user): array
    {
        $items = [];

        if ($user->can('orders.view')) {
            // Counter sales are not a fulfilment queue — the orders list leaves them out too.
            $open = fn () => Order::query()->where('placed_via', '<>', 'pos')->where('status', OrderStatus::InProgress);

            $items[] = $this->item('orders_to_fulfil', $open()->count(), route('admin.orders.index', ['status' => 'in_progress']), 'warning');
            // Bank transfers only: an unpaid cash-on-delivery order is normal
            // until the driver is back, so counting those buried the transfers
            // that actually wait on someone checking the bank account.
            $items[] = $this->item(
                'awaiting_payment',
                $open()->where('payment_method', PaymentMethod::BankTransfer)->where('payment_status', PaymentStatus::Unpaid)->count(),
                route('admin.orders.index', ['status' => 'in_progress', 'payment_status' => 'unpaid', 'payment_method' => PaymentMethod::BankTransfer->value]),
                'warning',
            );
        }

        if ($user->can('inventory.view')) {
            $items[] = $this->item('low_stock', self::lowStockCount(), route('admin.inventory.index', ['filter' => 'low']), 'warning');
            $items[] = $this->item('out_of_stock', self::outOfStockCount(), route('admin.inventory.index', ['filter' => 'out']), 'danger');
        }

        if ($user->can('reviews.moderate')) {
            $items[] = $this->item(
                'pending_reviews',
                Review::query()->where('status', ReviewStatus::Pending)->count(),
                route('admin.reviews.index', ['status' => 'pending']),
                'info',
            );
        }

        if ($user->can('purchase_orders.view')) {
            $items[] = $this->item(
                'purchase_orders_open',
                PurchaseOrder::query()->whereIn('status', [PurchaseOrderStatus::Sent, PurchaseOrderStatus::PartiallyReceived])->count(),
                route('admin.purchase-orders.index'),
                'info',
            );
        }

        if ($user->can('pos.shifts.view')) {
            $items[] = $this->item('open_shifts', CashierShift::query()->open()->count(), route('admin.pos.shifts.index'), 'info');
        }

        return $items;
    }

    /** Stock rows at or under their threshold but not empty — the inventory list's "low" filter. */
    public static function lowStockCount(): int
    {
        return StockLevel::query()
            ->join('products', 'products.id', '=', 'stock_levels.product_id')
            ->leftJoin('product_variants', 'product_variants.id', '=', 'stock_levels.product_variant_id')
            ->whereRaw('stock_levels.quantity <= COALESCE(product_variants.low_stock_threshold, products.low_stock_threshold)')
            ->where('stock_levels.quantity', '>', 0)
            ->count();
    }

    /**
     * Products on sale that a customer cannot buy anywhere right now — counted
     * per product, from the same sellable stock the storefront reads.
     */
    public static function outOfStockCount(): int
    {
        return Product::query()
            ->whereIn('status', [ProductStatus::Active, ProductStatus::OutOfStock])
            ->whereDoesntHave('stockLevels', fn ($q) => $q->sellable()->where('quantity', '>', 0))
            ->count();
    }

    /**
     * @return array{key: string, count: int, href: string, tone: string}
     */
    private function item(string $key, int $count, string $href, string $tone): array
    {
        return ['key' => $key, 'count' => $count, 'href' => $href, 'tone' => $tone];
    }
}
