<?php

declare(strict_types=1);

namespace App\Actions\Pos;

use App\Actions\Orders\CreateManualOrder;
use App\Actions\Orders\UpdateOrderStatus;
use App\DTOs\CheckoutData;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\CashierShift;
use App\Models\Order;
use Illuminate\Validation\ValidationException;

/**
 * Rings up a counter sale.
 *
 * Nothing about pricing, stock or the treasury is reimplemented here. The sale
 * is a manual order that is immediately completed, which is exactly what a
 * counter sale is: the goods leave with the customer and the money is in the
 * drawer before they walk away. UpdateOrderStatus(Completed) is what releases
 * the reservation and banks the payment — this action's only real jobs are
 * pinning the sale to the cashier's warehouse and to their open drawer.
 */
final class CompleteSale
{
    public function __construct(
        private readonly CreateManualOrder $createManualOrder,
        private readonly UpdateOrderStatus $updateOrderStatus,
    ) {}

    /**
     * @param  list<array{product_id: int, product_variant_id?: ?int, quantity: int}>  $lines
     * @param  array{name?: ?string, phone?: ?string, email?: ?string}  $customer
     */
    public function handle(
        CashierShift $shift,
        array $lines,
        PaymentMethod $paymentMethod = PaymentMethod::Cash,
        array $customer = [],
        ?string $couponCode = null,
        int $manualDiscountCents = 0,
        ?string $note = null,
    ): Order {
        if (! $shift->isOpen()) {
            throw ValidationException::withMessages([
                'shift' => __('Your shift is closed. Open a new one before selling.'),
            ]);
        }

        if ($lines === []) {
            throw ValidationException::withMessages([
                'items' => __('Add at least one item before taking payment.'),
            ]);
        }

        $shift->loadMissing('warehouse');

        $order = $this->createManualOrder->handle(
            items: $lines,
            data: $this->checkoutData($customer, $paymentMethod, $shift, $note),
            userId: $shift->user_id,
            placedVia: 'pos',
            couponCode: $couponCode,
            warehouse: $shift->warehouse,
            manualDiscountCents: $manualDiscountCents,
        );

        // Stamped before completing so the payment ConfirmPayment raises is
        // already attributable to this drawer if anything downstream reads it.
        $order->update(['cashier_shift_id' => $shift->id]);

        $order = $this->updateOrderStatus->handle(
            $order,
            OrderStatus::Completed,
            __('Sold at the register.'),
            $shift->user_id,
        );

        // UpdateOrderStatus settles the payment through a separately-locked
        // reload, so the instance it hands back still carries the pre-payment
        // status. Re-read before the receipt is built from it.
        return $order->refresh();
    }

    /**
     * A walk-in has no account and usually gives no details. The order still
     * needs a shape, so the register fills in what it knows: the customer is
     * anonymous and the "address" is the branch they bought from — which is
     * true, and keeps the invoice and reports readable.
     */
    private function checkoutData(array $customer, PaymentMethod $paymentMethod, CashierShift $shift, ?string $note): CheckoutData
    {
        $name = trim((string) ($customer['name'] ?? '')) ?: __('Walk-in customer');

        $details = [
            'name' => $name,
            'email' => $customer['email'] ?? null,
            'phone' => $customer['phone'] ?? null,
        ];

        $address = [
            'name' => $name,
            'phone' => $details['phone'],
            'country' => (string) config('store.country', 'LY'),
            'city' => $shift->warehouse->name,
            'area' => __('Over the counter'),
        ];

        return new CheckoutData(
            customer: $details,
            shippingAddress: $address,
            billingAddress: $address,
            paymentMethod: $paymentMethod,
            customerNote: $note,
        );
    }
}
