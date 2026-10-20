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
use App\Models\Coupon;
use App\Models\Order;
use App\Models\StockLevel;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\OrderPlacedNotification;
use App\Support\CostLayers;
use App\Support\CustomerIdentity;
use App\Support\ReferenceNumber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
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
     *                                    the counter, on top of any coupon. Spread over
     *                                    the line totals like a coupon (unit prices stay
     *                                    as sold), so per-product revenue adds up.
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
        // The key is scoped to whoever sent it (account, or the guest's cart), so
        // somebody else's key can never fetch their order.
        $idempotencyKey = $data->idempotencyKey === null
            ? null
            : hash('sha256', ($user !== null ? 'user:'.$user->id : 'cart:'.$cart->id).'|'.$data->idempotencyKey);

        if ($idempotencyKey !== null && ($existing = Order::query()->where('idempotency_key', $idempotencyKey)->first()) !== null) {
            return $existing;
        }

        $totals = $this->calculateTotals->handle($cart, $user?->id);

        if ($totals->isEmpty()) {
            throw ValidationException::withMessages(['cart' => __('Your cart is empty.')]);
        }

        if ($totals->couponError !== null) {
            throw ValidationException::withMessages(['coupon' => $totals->couponError]);
        }

        foreach ($totals->lines as $line) {
            // A variant disabled or removed after it went into the cart (a removed
            // one loads as null) is no longer for sale either.
            $variantGone = $line->variant !== null ? ! $line->variant->is_active : $line->product->has_variants;

            if (! $line->product->status->isPurchasable() || $variantGone) {
                throw ValidationException::withMessages([
                    'cart' => __('":name" is no longer available. Please remove it from your cart.', ['name' => $line->product->name]),
                ]);
            }
        }

        $manualDiscountCents = $this->validateManualDiscount($manualDiscountCents, $totals);

        $phoneKey = CustomerIdentity::phoneKey($data->customer['phone'] ?? $data->shippingAddress['phone'] ?? null);
        $emailKey = CustomerIdentity::emailKey($data->customer['email'] ?? null);

        try {
            $order = DB::transaction(function () use ($cart, $data, $user, $totals, $placedVia, $warehouse, $manualDiscountCents, $idempotencyKey, $phoneKey, $emailKey): Order {
                // A double-click sends the same cart twice: the second request waits
                // here, then finds the cart already emptied by the first.
                Cart::query()->whereKey($cart->id)->lockForUpdate()->first();

                if (! $cart->items()->exists()) {
                    throw ValidationException::withMessages(['cart' => __('This order was already placed.')]);
                }

                // Re-check the coupon's limits under a row lock: two checkouts racing
                // for the last use of a coupon must not both get it.
                if ($totals->coupon !== null && $totals->discountCents > 0) {
                    $coupon = Coupon::query()->lockForUpdate()->findOrFail($totals->coupon->id);

                    if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
                        throw ValidationException::withMessages(['coupon' => __('This coupon has reached its usage limit.')]);
                    }

                    // Guests too: the same phone or email is the same customer.
                    if ($coupon->per_customer_limit !== null
                        && $coupon->usesByCustomer($user?->id, $phoneKey, $emailKey) >= $coupon->per_customer_limit) {
                        throw ValidationException::withMessages(['coupon' => __('You have already used this coupon.')]);
                    }
                }

                $order = $this->createOrderRow($data, $user, $totals, $placedVia, $manualDiscountCents, $idempotencyKey);

                // Deterministic lock order prevents deadlocks between
                // concurrent checkouts sharing products.
                $lines = collect($totals->lines)
                    ->sortBy(fn (CartLine $l) => [$l->product->id, $l->variant?->id ?? 0]);
                $manualShares = $this->spread($manualDiscountCents, array_map(fn (CartLine $l) => $l->lineTotalCents, $totals->lines));

                foreach ($lines as $index => $line) {
                    // The storefront advertises stock summed across warehouses,
                    // so fulfilment must look beyond the default one too. A caller
                    // that names a warehouse (the register) overrides that.
                    $lineWarehouse = $warehouse ?? $this->warehouseFor($line);

                    // Throws InsufficientStockException when quantity would go
                    // negative — which aborts and rolls back the whole order.
                    $movement = $this->recordMovement->handle(
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
                        // What the units taken cost, oldest stock first (FIFO). The slices are
                        // kept so a cancel or return puts the units back at those costs;
                        // unit_cost_cents is a display average — profit uses cost_cents.
                        'unit_cost_cents' => intdiv(CostLayers::total($movement->costLayers), $line->quantity),
                        'cost_cents' => CostLayers::total($movement->costLayers),
                        'cost_layers' => $movement->costLayers,
                        'quantity' => $line->quantity,
                        'discount_cents' => $line->lineDiscountCents + $manualShares[$index],
                        'line_total_cents' => $line->lineTotalCents - $manualShares[$index],
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
                        'customer_phone' => $phoneKey,
                        'customer_email' => $emailKey,
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
        } catch (UniqueConstraintViolationException $e) {
            // The same submit arrived twice at once: the other request won, hand back its order.
            $existing = $idempotencyKey !== null ? Order::query()->where('idempotency_key', $idempotencyKey)->first() : null;

            if ($existing === null) {
                throw $e;
            }

            return $existing;
        }

        if ($user !== null) {
            $user->notify(new OrderPlacedNotification($order));
        } elseif (filled($data->customer['email'] ?? null) && $placedVia !== 'pos') {
            // Guests get their confirmation too, in the language they checked out in.
            Notification::route('mail', $data->customer['email'])
                ->notify((new OrderPlacedNotification($order))->locale(app()->getLocale()));
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

    /**
     * Splits an amount across lines in proportion to their totals; the last
     * line takes the rounding remainder so the shares add up exactly.
     *
     * @param  array<int, int>  $weights
     * @return array<int, int>
     */
    private function spread(int $amount, array $weights): array
    {
        $sum = array_sum($weights);
        $shares = array_map(fn () => 0, $weights);

        if ($amount <= 0 || $sum <= 0) {
            return $shares;
        }

        $left = $amount;
        $last = array_key_last($weights);

        foreach ($weights as $i => $weight) {
            $shares[$i] = $i === $last ? $left : intdiv($weight * $amount, $sum);
            $left -= $shares[$i];
        }

        return $shares;
    }

    private function createOrderRow(CheckoutData $data, ?User $user, CartTotals $totals, string $placedVia, int $manualDiscountCents = 0, ?string $idempotencyKey = null): Order
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
            'idempotency_key' => $idempotencyKey,
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
