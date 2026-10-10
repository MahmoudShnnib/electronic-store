<?php

use App\Actions\Cart\AddToCart;
use App\Actions\Checkout\PlaceOrder;
use App\Actions\Orders\ResolveOrderIssue;
use App\Actions\Orders\UpdateOrderStatus;
use App\Actions\Payments\ConfirmPayment;
use App\DTOs\CheckoutData;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundMethod;
use App\Enums\TreasuryAccount;
use App\Enums\TreasuryDirection;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\TreasuryTransaction;
use App\Models\User;
use App\Services\Admin\TreasuryQueryService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

function orderFor(User $user, Product $product, int $quantity = 2, PaymentMethod $method = PaymentMethod::CashOnDelivery): Order
{
    $cart = Cart::create(['user_id' => $user->id]);
    app(AddToCart::class)->handle($cart, $product, null, $quantity);

    return app(PlaceOrder::class)->handle($cart, new CheckoutData(
        customer: ['name' => $user->name, 'email' => $user->email, 'phone' => '091'],
        shippingAddress: ['name' => $user->name, 'phone' => '091', 'country' => 'LY', 'city' => 'Tripoli'],
        billingAddress: ['name' => $user->name, 'phone' => '091', 'country' => 'LY', 'city' => 'Tripoli'],
        paymentMethod: $method,
    ), $user);
}

/** Moves a freshly placed order to the given status. */
function advanceTo(Order $order, OrderStatus $target): Order
{
    return app(UpdateOrderStatus::class)->handle($order, $target);
}

beforeEach(function (): void {
    Notification::fake();
});

// -- Completing an order banks the money -------------------------------------

it('collects the outstanding balance into the cash treasury when an order completes', function (): void {
    $product = Product::factory()->create(['price_cents' => 100_00]);
    giveStock($product, 10);
    $order = orderFor(customer(), $product);

    expect(TreasuryTransaction::query()->count())->toBe(0);

    $order = advanceTo($order, OrderStatus::Completed)->refresh();

    $movement = TreasuryTransaction::query()->sole();

    expect($order->payment_status)->toBe(PaymentStatus::Paid)
        ->and($movement->direction)->toBe(TreasuryDirection::In)
        // Cash on delivery lands in the drawer, not the bank.
        ->and($movement->account)->toBe(TreasuryAccount::Cash)
        ->and($movement->amount_cents)->toBe(200_00)
        ->and($movement->order_id)->toBe($order->id)
        ->and(app(TreasuryQueryService::class)->balances())->toMatchArray([
            'cash' => 200_00,
            'bank' => 0,
            'total' => 200_00,
        ]);
});

it('routes a bank transfer order to the bank account', function (): void {
    $product = Product::factory()->create(['price_cents' => 50_00]);
    giveStock($product, 10);
    $order = orderFor(customer(), $product, 1, PaymentMethod::BankTransfer);

    // A transfer is banked when staff confirm it arrived, not by completing the order.
    expect(fn () => advanceTo($order, OrderStatus::Completed))->toThrow(ValidationException::class);
    app(ConfirmPayment::class)->handle($order->refresh(), 50_00, 'TRX-1');
    advanceTo($order->refresh(), OrderStatus::Completed);

    expect(TreasuryTransaction::query()->sole()->account)->toBe(TreasuryAccount::Bank)
        ->and(app(TreasuryQueryService::class)->balances())->toMatchArray(['cash' => 0, 'bank' => 50_00]);
});

it('does not double-collect when the order was already paid', function (): void {
    $product = Product::factory()->create(['price_cents' => 100_00]);
    giveStock($product, 10);
    $order = orderFor(customer(), $product, 1);

    app(ConfirmPayment::class)->handle($order, 100_00, 'REF-1');
    advanceTo($order, OrderStatus::Completed);

    expect(TreasuryTransaction::query()->count())->toBe(1)
        ->and(app(TreasuryQueryService::class)->balances()['total'])->toBe(100_00);
});

// -- Refunds take money back out ---------------------------------------------

it('takes the refund back out of the treasury', function (): void {
    $product = Product::factory()->create(['price_cents' => 100_00]);
    giveStock($product, 10);
    $order = advanceTo(orderFor(customer(), $product), OrderStatus::Completed);

    app(ResolveOrderIssue::class)->handle(
        order: $order->refresh(),
        reason: 'Screen arrived cracked',
        refundCents: 200_00,
        // No method given: it resolves to the channel the order was paid on.
        // A refund comes with the goods (whole returns, 2026-10-10).
        restock: true,
    );

    $out = TreasuryTransaction::query()->where('direction', TreasuryDirection::Out)->sole();

    expect($out->account)->toBe(TreasuryAccount::Cash)
        ->and($out->amount_cents)->toBe(200_00)
        ->and($out->note)->toBe('Screen arrived cracked')
        ->and(app(TreasuryQueryService::class)->balances()['total'])->toBe(0);
});

it('refuses a store-credit refund: there is no credit balance to hold it', function (): void {
    $product = Product::factory()->create(['price_cents' => 100_00]);
    giveStock($product, 10);
    $order = advanceTo(orderFor(customer(), $product, 1), OrderStatus::Completed);

    expect(fn () => app(ResolveOrderIssue::class)->handle(
        order: $order->refresh(),
        reason: 'Customer took a voucher instead',
        refundCents: 100_00,
        method: RefundMethod::StoreCredit,
        restock: false,
    ))->toThrow(ValidationException::class);

    expect(TreasuryTransaction::query()->where('direction', TreasuryDirection::Out)->count())->toBe(0)
        ->and(app(TreasuryQueryService::class)->balances()['total'])->toBe(100_00);
});

// -- The unified return / problem action -------------------------------------

it('records the problem without moving money when the amount is zero', function (): void {
    $product = Product::factory()->create(['price_cents' => 100_00]);
    giveStock($product, 10);
    $order = advanceTo(orderFor(customer(), $product, 1), OrderStatus::Completed);

    $order = app(ResolveOrderIssue::class)->handle(
        order: $order->refresh(),
        reason: 'Missing charger in the box',
        refundCents: 0,
        restock: false,
    );

    expect($order->status)->toBe(OrderStatus::Completed)
        ->and($order->refunds()->count())->toBe(0)
        ->and(TreasuryTransaction::query()->where('direction', TreasuryDirection::Out)->count())->toBe(0)
        ->and($order->statusHistories()->latest('id')->first()->comment)->toBe('Missing charger in the box');
});

it('refunds, restocks and marks the order refunded in one step', function (): void {
    $product = Product::factory()->create(['price_cents' => 100_00]);
    giveStock($product, 10);
    $order = advanceTo(orderFor(customer(), $product), OrderStatus::Completed);

    $before = StockLevel::query()->where('product_id', $product->id)->value('quantity');

    $order = app(ResolveOrderIssue::class)->handle(
        order: $order->refresh(),
        reason: 'Wrong model shipped',
        refundCents: 200_00,
        method: RefundMethod::Cash,
        restock: true,
    );

    expect($order->status)->toBe(OrderStatus::Refunded)
        ->and($order->payment_status)->toBe(PaymentStatus::Refunded)
        ->and(StockLevel::query()->where('product_id', $product->id)->value('quantity'))->toBe($before + 2);
});

it('refuses to return an order the customer never received', function (): void {
    $product = Product::factory()->create(['price_cents' => 100_00]);
    giveStock($product, 10);
    $order = orderFor(customer(), $product, 1);

    app(ResolveOrderIssue::class)->handle($order, 'Too early', 0);
})->throws(ValidationException::class);

// -- The admin screen --------------------------------------------------------

it('shows the treasury page with its balances to an order manager', function (): void {
    $product = Product::factory()->create(['price_cents' => 100_00]);
    giveStock($product, 10);
    advanceTo(orderFor(customer(), $product, 1), OrderStatus::Completed);

    $this->actingAs(staff('order_manager'))
        ->get(route('admin.treasury.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/treasury/Index')
            ->where('balances.total_formatted', fn (string $v) => str_contains($v, '100'))
            ->has('transactions.data', 1));
});

it('lets an admin record a manual deposit', function (): void {
    $this->actingAs(staff('admin'))
        ->post(route('admin.treasury.store'), [
            'direction' => 'in',
            'account' => 'bank',
            'amount' => 250,
            'note' => 'Owner top-up',
        ])
        ->assertRedirect();

    expect(app(TreasuryQueryService::class)->balances())->toMatchArray(['cash' => 0, 'bank' => 250_00]);
});

it('keeps the treasury page away from staff without the permission', function (): void {
    $this->actingAs(staff('inventory_manager'))
        ->get(route('admin.treasury.index'))
        ->assertForbidden();
});
