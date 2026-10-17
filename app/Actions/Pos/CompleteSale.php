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
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
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
        private readonly FindCustomerByPhone $findCustomer,
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
        ?string $manualDiscountReason = null,
        ?int $manualDiscountMaxPercent = null,
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

        if (! $shift->warehouse->is_active) {
            throw ValidationException::withMessages([
                'shift' => __('This branch has been deactivated. Close the shift; it can no longer sell.'),
            ]);
        }

        // One transaction: a sale that fails to complete must not leave a
        // half-made order holding stock.
        return DB::transaction(fn (): Order => $this->ringUp($shift, $lines, $paymentMethod, $customer, $couponCode, $manualDiscountCents, $note, $manualDiscountReason, $manualDiscountMaxPercent));
    }

    /**
     * @param  list<array{product_id: int, product_variant_id?: ?int, quantity: int}>  $lines
     * @param  array{name?: ?string, phone?: ?string, email?: ?string}  $customer
     */
    private function ringUp(
        CashierShift $shift,
        array $lines,
        PaymentMethod $paymentMethod,
        array $customer,
        ?string $couponCode,
        int $manualDiscountCents,
        ?string $note,
        ?string $manualDiscountReason,
        ?int $manualDiscountMaxPercent,
    ): Order {
        $order = $this->createManualOrder->handle(
            items: $lines,
            data: $this->checkoutData($customer, $paymentMethod, $shift, $note),
            userId: $shift->user_id,
            placedVia: 'pos',
            couponCode: $couponCode,
            warehouse: $shift->warehouse,
            manualDiscountCents: $manualDiscountCents,
        );

        // The cap is a share of the subtotal, which only exists once the order
        // is priced. Failing here rolls the whole sale back.
        if ($manualDiscountMaxPercent !== null && $manualDiscountCents * 100 > $order->subtotal_cents * $manualDiscountMaxPercent) {
            throw ValidationException::withMessages([
                'discount' => __('A cashier can take off at most :percent% by hand. Ask an admin for more.', ['percent' => $manualDiscountMaxPercent]),
            ]);
        }

        // A phone that matches a customer account puts the sale in their history;
        // anyone else stays a cash customer. Nobody is created here.
        $customerAccount = $this->findCustomer->handle($customer['phone'] ?? null);

        // Stamped before completing so the payment ConfirmPayment raises is
        // already attributable to this drawer if anything downstream reads it.
        // The manual discount is kept apart from the coupon so reports can
        // tell them apart and say who gave it and why.
        $order->update([
            'number' => $this->nextReceiptNumber($shift->warehouse),
            'user_id' => $customerAccount?->id,
            'customer' => $customerAccount === null ? $order->customer : [
                ...$order->customer,
                'name' => trim((string) ($customer['name'] ?? '')) ?: $customerAccount->name,
                'email' => $order->customer['email'] ?? $customerAccount->email,
            ],
            'cashier_shift_id' => $shift->id,
            'manual_discount_cents' => $manualDiscountCents,
            'manual_discount_reason' => $manualDiscountCents > 0 ? (trim((string) $manualDiscountReason) ?: null) : null,
        ]);

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
     * The branch's next receipt number: POS-MAIN-000127, one gapless run per
     * branch. The counter is bumped under the warehouse row's lock inside the
     * sale's transaction, so two tills never share a number and a sale that
     * fails gives its number back.
     */
    private function nextReceiptNumber(Warehouse $warehouse): string
    {
        $locked = Warehouse::query()->lockForUpdate()->findOrFail($warehouse->id);
        $locked->increment('pos_receipt_seq');

        return sprintf('POS-%s-%06d', strtoupper((string) $locked->code), $locked->pos_receipt_seq);
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
