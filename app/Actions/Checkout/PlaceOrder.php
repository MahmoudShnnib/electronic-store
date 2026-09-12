<?php

declare(strict_types=1);

namespace App\Actions\Checkout;

use App\Actions\Cart\CalculateCartTotals;
use App\Actions\Inventory\InsufficientStockException;
use App\Actions\Inventory\RecordMovement;
use App\DTOs\CartLine;
use App\DTOs\CartTotals;
use App\DTOs\CheckoutData;
use App\Enums\MovementType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\Order;
use App\Models\StockLevel;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\OrderPlacedNotification;
use App\Support\ReferenceNumber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns a cart into an order atomically:
 *
 *   transaction {
 *     lock stock rows (deterministic order to avoid deadlocks)
 *     validate availability   → reserve (quantity -= q, reserved += q)
 *     create order + item snapshots + ledger movements + status history
 *     redeem coupon
 *     clear cart
 *   }
 *
 * Prices and discounts are always recomputed server-side via
 * CalculateCartTotals. Nothing from the client is trusted.
 */
final class PlaceOrder
{
    public function __construct(
        private readonly CalculateCartTotals $calculateTotals,
        private readonly RecordMovement $recordMovement,
    ) {}

    /**
     * @param  ?Warehouse  $warehouse  Forces every line to ship from this warehouse
     *                                 instead of letting warehouseFor() choose. The
     *                                 register sells what is physically on its own
     *                                 shelf; null keeps the storefront's behaviour.
     * @param  int  $manualDiscountCents  An order-level discount keyed in by hand at
     *                                    the counter, on top of any coupon. Applied to
     *                                    the total only — line prices stay as sold.
     */
    public function handle(
        Cart $cart,
        CheckoutData $data,
        ?User $user,
        string $placedVia = 'web',
        ?Warehouse $warehouse = null,
        int $manualDiscountCents = 0,
    ): Order {
        // Idempotency: replaying the same checkout returns the original order.
        if ($data->idempotencyKey !== null) {
            $existing = Order::query()
                ->where('idempotency_key', $data->idempotencyKey)
                ->when($user !== null, fn ($q) => $q->where('user_id', $user->id))
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        $totals = $this->calculateTotals->handle($cart, $user?->id);

        if ($totals->isEmpty()) {
            throw ValidationException::withMessages(['cart' => __('Your cart is empty.')]);
        }

        if ($totals->couponError !== null) {
            throw ValidationException::withMessages(['coupon' => $totals->couponError]);
        }

        foreach ($totals->lines as $line) {
            if (! $line->product->status->isPurchasable()) {
                throw ValidationException::withMessages([
                    'cart' => __('":name" is no longer available. Please remove it from your cart.', ['name' => $line->product->name]),
                ]);
            }
        }

        $manualDiscountCents = $this->validateManualDiscount($manualDiscountCents, $totals);

        try {
            $order = DB::transaction(function () use ($cart, $data, $user, $totals, $placedVia, $warehouse, $manualDiscountCents): Order {
                $order = $this->createOrderRow($data, $user, $totals, $placedVia, $manualDiscountCents);

                // Deterministic lock order prevents deadlocks between
                // concurrent checkouts sharing products.
                $lines = collect($totals->lines)
                    ->sortBy(fn (CartLine $l) => [$l->product->id, $l->variant?->id ?? 0]);

                foreach ($lines as $line) {
                    // The storefront advertises stock summed across warehouses,
                    // so fulfilment must look beyond the default one too. A caller
                    // that names a warehouse (the register) overrides that.
                    $lineWarehouse = $warehouse ?? $this->warehouseFor($line);

                    // Throws InsufficientStockException when quantity would go
                    // negative — which aborts and rolls back the whole order.
                    $this->recordMovement->handle(
                        type: MovementType::CustomerOrder,
                        warehouse: $lineWarehouse,
                        product: $line->product,
                        variant: $line->variant,
                        quantityChange: -$line->quantity,
                        reservedChange: $line->quantity,
                        reference: $order,
                        userId: $user?->id,
                    );

                    $order->items()->create([
                        'product_id' => $line->product->id,
                        'product_variant_id' => $line->variant?->id,
                        'warehouse_id' => $lineWarehouse->id,
                        'product_name' => $line->product->name,
                        'variant_name' => $line->variant?->name,
                        'sku' => $line->sku(),
                        'unit_price_cents' => $line->unitPriceCents,
                        'quantity' => $line->quantity,
                        'discount_cents' => $line->lineDiscountCents,
                        'line_total_cents' => $line->lineTotalCents,
                    ]);
                }

                $order->statusHistories()->create([
                    'from_status' => null,
                    'to_status' => OrderStatus::InProgress,
                    'comment' => 'Order placed.',
                    'user_id' => $user?->id,
                ]);

                if ($totals->coupon !== null && $totals->discountCents > 0) {
                    $totals->coupon->redemptions()->create([
                        'user_id' => $user?->id,
                        'order_id' => $order->id,
                        'amount_cents' => $totals->discountCents,
                    ]);
                    $totals->coupon->increment('used_count');
                }

                $cart->items()->delete();
                $cart->update(['coupon_id' => null]);

                return $order;
            });
        } catch (InsufficientStockException $e) {
            throw ValidationException::withMessages(['cart' => $e->getMessage()]);
        }

        if ($user !== null) {
            $user->notify(new OrderPlacedNotification($order));
        }

        return $order;
    }

    /**
     * The warehouse a line ships from: the default warehouse when it can cover
     * the whole line, otherwise the active warehouse holding the most stock of
     * that product/variant. When nothing suffices, the default is returned so
     * RecordMovement raises the usual insufficient-stock error.
     *
     * One line always ships from one warehouse — a single line larger than any
     * individual warehouse's stock still fails even if warehouses combined
     * could cover it.
     */
    private function warehouseFor(CartLine $line): Warehouse
    {
        $default = Warehouse::default();

        $stocked = StockLevel::query()
            ->where('product_id', $line->product->id)
            ->where('product_variant_id', $line->variant?->id)
            ->where('quantity', '>=', $line->quantity)
            ->whereHas('warehouse', fn ($q) => $q->where('is_active', true))
            ->orderByDesc('quantity')
            ->with('warehouse')
            ->get();

        if ($stocked->contains('warehouse_id', $default->id)) {
            return $default;
        }

        return $stocked->first()?->warehouse ?? $default;
    }

    /**
     * A manual discount can only ever reduce what is already owed: it is capped
     * at the computed total, so no combination of coupon and keyed-in discount
     * can produce a negative order.
     */
    private function validateManualDiscount(int $manualDiscountCents, CartTotals $totals): int
    {
        if ($manualDiscountCents < 0) {
            throw ValidationException::withMessages(['discount' => __('A discount cannot be negative.')]);
        }

        if ($manualDiscountCents > $totals->totalCents) {
            throw ValidationException::withMessages(['discount' => __('The discount cannot exceed the order total.')]);
        }

        return $manualDiscountCents;
    }

    private function createOrderRow(CheckoutData $data, ?User $user, CartTotals $totals, string $placedVia, int $manualDiscountCents = 0): Order
    {
        $attributes = [
            'user_id' => $user?->id,
            'customer' => $data->customer,
            'billing_address' => $data->billingAddress,
            'shipping_address' => $data->shippingAddress,
            'status' => OrderStatus::InProgress,
            'payment_status' => PaymentStatus::Unpaid,
            'payment_method' => $data->paymentMethod,
            'currency' => (string) config('store.currency'),
            'subtotal_cents' => $totals->subtotalCents,
            // Both discounts land in one column: the coupon code alongside it
            // says how much of it was the coupon.
            'discount_cents' => $totals->discountCents + $manualDiscountCents,
            'coupon_code' => $totals->discountCents > 0 ? $totals->coupon?->code : null,
            'total_cents' => $totals->totalCents - $manualDiscountCents,
            'customer_note' => $data->customerNote,
            'idempotency_key' => $data->idempotencyKey,
            'placed_via' => $placedVia,
        ];

        // The random order-number suffix can collide; retry with a new number.
        for ($attempt = 0; ; $attempt++) {
            try {
                return Order::create([...$attributes, 'number' => ReferenceNumber::generate('ORD')]);
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= 3 || ! str_contains($e->getMessage(), 'orders_number_unique')) {
                    throw $e;
                }
            }
        }
    }
}
