<?php

/*
 * Audit 2026-10-01 — money graph (Order ↔ Payment ↔ Refund ↔ Treasury ↔ Coupon
 * ↔ Shift ↔ Reports). Each test asserts the CORRECT behaviour: a failing test
 * is a confirmed bug, a passing one is a checked-and-OK path.
 */

use App\Actions\Cart\AddToCart;
use App\Actions\Cart\ApplyCoupon;
use App\Actions\Checkout\PlaceOrder;
use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\ResolveOrderIssue;
use App\Actions\Orders\UpdateOrderStatus;
use App\Actions\Payments\ConfirmPayment;
use App\Actions\Pos\CompleteSale;
use App\Actions\Pos\OpenShift;
use App\Actions\Refunds\CreateRefund;
use App\DTOs\CheckoutData;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RefundMethod;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\TreasuryTransaction;
use App\Models\User;
use App\Services\Admin\TreasuryQueryService;
use App\Services\Reports\ReportDateRange;
use App\Services\Reports\ReportService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => Notification::fake());

function mgOrder(?User $user, array $lines, PaymentMethod $method = PaymentMethod::CashOnDelivery, ?string $coupon = null): Order
{
    $cart = Cart::create(['user_id' => $user?->id]);
    foreach ($lines as [$product, $qty]) {
        app(AddToCart::class)->handle($cart, $product, null, $qty);
    }
    if ($coupon !== null) {
        app(ApplyCoupon::class)->handle($cart->refresh(), $coupon, $user?->id);
    }
    $addr = ['name' => 'x', 'phone' => '091', 'country' => 'LY', 'city' => 'Tripoli', 'area' => 'A'];

    return app(PlaceOrder::class)->handle($cart->refresh(), new CheckoutData(
        customer: ['name' => 'x', 'email' => $user?->email, 'phone' => '091'],
        shippingAddress: $addr,
        billingAddress: $addr,
        paymentMethod: $method,
    ), $user);
}

function mgProduct(int $priceCents, int $stock = 20, int $cost = 0): Product
{
    $p = Product::factory()->create(['price_cents' => $priceCents, 'sale_price_cents' => null, 'cost_price_cents' => $cost]);
    giveStock($p, $stock);

    return $p;
}

/** Net money the ledger holds for an order (in − out, both accounts). */
function mgLedgerForOrder(Order $order): int
{
    return (int) TreasuryTransaction::query()->where('order_id', $order->id)->get()
        ->sum(fn (TreasuryTransaction $t) => $t->signedAmountCents());
}

function mgNetCollected(Order $order): int
{
    return (int) $order->payments()->where('status', 'confirmed')->sum('amount_cents')
        - (int) $order->refunds()->where('status', 'completed')->sum('amount_cents');
}

function mgWholeRange(): array
{
    return [CarbonImmutable::now()->subYear(), CarbonImmutable::now()->addDay()];
}

// ---------------------------------------------------------------- money math

it('MON-1: Money::fromDecimal rounds half-up on the decimal the admin typed (1.005 → 101, 0.285 → 29)', function (): void {
    expect(Money::fromDecimal('1.005'))->toBe(101)
        ->and(Money::fromDecimal('0.285'))->toBe(29);
});

it('MON-2 (check): multi-line percentage coupon — line discounts sum to the order discount and total = subtotal − discount', function (): void {
    $a = mgProduct(3_333);
    $b = mgProduct(7_777);
    $c = mgProduct(1_001);
    Coupon::factory()->percentage(33.33)->create(['code' => 'ODD']);

    $order = mgOrder(customer(), [[$a, 3], [$b, 1], [$c, 7]], coupon: 'ODD');

    expect((int) $order->items()->sum('discount_cents'))->toBe($order->discount_cents)
        ->and((int) $order->items()->sum('line_total_cents'))->toBe($order->total_cents)
        ->and($order->total_cents)->toBe($order->subtotal_cents - $order->discount_cents);
});

it('MON-3: a POS manual discount is reflected in the lines, so product/category/brand revenue sums to order revenue', function (): void {
    $p = mgProduct(10_000);
    $shift = app(OpenShift::class)->handle(staff('cashier'), defaultWarehouse(), 0);
    $order = app(CompleteSale::class)->handle($shift, [['product_id' => $p->id, 'quantity' => 2]], manualDiscountCents: 5_000);

    expect($order->total_cents)->toBe(15_000)
        ->and((int) $order->items()->sum('line_total_cents'))->toBe($order->total_cents);
});

// ---------------------------------------------------------- payments/refunds

it('MON-4: completing an order whose deposit was refunded collects the full total, not just total − original payments', function (): void {
    $p = mgProduct(10_000);
    $order = mgOrder(customer(), [[$p, 1]]);
    app(ConfirmPayment::class)->handle($order, 5_000);             // deposit 50
    app(CreateRefund::class)->handle($order->refresh(), 5_000, RefundMethod::Cash); // deposit returned

    try {
        app(UpdateOrderStatus::class)->handle($order->refresh(), OrderStatus::Completed);
    } catch (ValidationException) {
        expect(true)->toBeTrue(); // refusing is also correct

        return;
    }

    // Goods left: the shop must hold the whole 100.
    expect(mgNetCollected($order->refresh()))->toBe(10_000)
        ->and(mgLedgerForOrder($order))->toBe(10_000);
});

it('MON-5: completing an unpaid BANK TRANSFER order does not invent a bank deposit nobody verified', function (): void {
    $p = mgProduct(10_000);
    $order = mgOrder(customer(), [[$p, 1]], PaymentMethod::BankTransfer);

    // Refused until staff confirm the transfer arrived.
    expect(fn () => app(UpdateOrderStatus::class)->handle($order, OrderStatus::Completed))->toThrow(ValidationException::class);

    expect(app(TreasuryQueryService::class)->balances()['bank'])->toBe(0)
        ->and($order->refresh()->payment_status)->not->toBe(PaymentStatus::Paid)
        ->and($order->status)->toBe(OrderStatus::InProgress);
});

it('MON-6: cancelling a paid order does not silently keep the customer\'s money', function (): void {
    $p = mgProduct(10_000);
    $order = mgOrder(customer(), [[$p, 1]], PaymentMethod::BankTransfer);
    app(ConfirmPayment::class)->handle($order, 10_000, 'TRX-1');

    try {
        app(CancelOrder::class)->handle($order->refresh(), 'customer changed mind');
    } catch (ValidationException) {
        expect(true)->toBeTrue(); // refusing until refunded is correct

        return;
    }

    expect(mgLedgerForOrder($order))->toBe(0);
});

it('MON-7: a store-credit refund is refused (there is no credit balance to hold it)', function (): void {
    $user = customer();
    $p = mgProduct(10_000);
    $order = mgOrder($user, [[$p, 1]]);
    app(UpdateOrderStatus::class)->handle($order, OrderStatus::Completed);

    expect(fn () => app(CreateRefund::class)->handle($order->refresh(), 10_000, RefundMethod::StoreCredit))
        ->toThrow(ValidationException::class)
        ->and($order->refresh()->refunds()->count())->toBe(0);
});

it('MON-7b: refunding the same order again and again never gives back more than was paid', function (): void {
    $p = mgProduct(10_000);
    $order = mgOrder(customer(), [[$p, 1]]);
    app(UpdateOrderStatus::class)->handle($order, OrderStatus::Completed); // 100 collected

    app(CreateRefund::class)->handle($order->refresh(), 6_000, RefundMethod::Cash);
    expect(fn () => app(CreateRefund::class)->handle($order->refresh(), 6_000, RefundMethod::Cash))->toThrow(ValidationException::class);
    expect(fn () => app(ResolveOrderIssue::class)->handle($order->refresh(), 'again', 6_000, RefundMethod::Cash))->toThrow(ValidationException::class);
    app(CreateRefund::class)->handle($order->refresh(), 4_000, RefundMethod::Cash);
    expect(fn () => app(CreateRefund::class)->handle($order->refresh(), 1, RefundMethod::Cash))->toThrow(ValidationException::class);

    expect(mgNetCollected($order->refresh()))->toBe(0)->and(mgLedgerForOrder($order))->toBe(0);
});

it('MON-8 (check): refunds cannot exceed what was paid and payments cannot exceed the total', function (): void {
    $p = mgProduct(10_000);
    $order = mgOrder(customer(), [[$p, 1]]);
    expect(fn () => app(ConfirmPayment::class)->handle($order, 10_001))->toThrow(ValidationException::class);
    app(ConfirmPayment::class)->handle($order, 4_000);
    expect(fn () => app(CreateRefund::class)->handle($order->refresh(), 4_001, RefundMethod::Cash))->toThrow(ValidationException::class);
    expect(fn () => app(CreateRefund::class)->handle($order->refresh(), -1, RefundMethod::Cash))->toThrow(ValidationException::class);
});

// ------------------------------------------------------------------ treasury

it('MON-9: a manual treasury withdrawal cannot take the cash drawer below zero', function (): void {
    $this->actingAs(staff('super_admin'))
        ->post(route('admin.treasury.store'), ['direction' => 'out', 'account' => 'cash', 'amount' => '500', 'note' => 'MON-9']);

    expect(app(TreasuryQueryService::class)->balances()['cash'])->toBeGreaterThanOrEqual(0);
});

it('MON-10 (check): every payment and refund posts exactly once to the right account; balance = signed sum', function (): void {
    $p = mgProduct(10_000);
    $cod = mgOrder(customer(), [[$p, 1]]);
    $bank = mgOrder(customer(), [[$p, 1]], PaymentMethod::BankTransfer);
    app(ConfirmPayment::class)->handle($cod, 10_000);
    app(ConfirmPayment::class)->handle($bank, 10_000, 'T');
    app(CreateRefund::class)->handle($cod->refresh(), 2_000, RefundMethod::Cash);
    app(CreateRefund::class)->handle($bank->refresh(), 3_000, RefundMethod::BankTransfer);

    $b = app(TreasuryQueryService::class)->balances();
    expect($b['cash'])->toBe(8_000)->and($b['bank'])->toBe(7_000)
        ->and(TreasuryTransaction::count())->toBe(4);
});

// ------------------------------------------------------------- manual orders

it('MON-11: a manual order records the staff member who keyed it in', function (): void {
    $p = mgProduct(10_000);
    $staff = staff('order_manager');

    $this->actingAs($staff)->post(route('admin.orders.store'), [
        'customer' => ['name' => 'Ali', 'phone' => '0911111111'],
        'shipping_address' => ['city' => 'Tripoli', 'area' => 'Center'],
        'payment_method' => 'cod',
        'items' => [['product_id' => $p->id, 'quantity' => 1]],
    ])->assertSessionHasNoErrors();

    $order = Order::latest('id')->firstOrFail();
    expect($order->statusHistories()->first()->user_id)->toBe($staff->id);
});

it('MON-12: a manual order for an existing customer\'s email is linked to that customer', function (): void {
    $p = mgProduct(10_000);
    $known = customer(['email' => 'known@example.test']);

    $this->actingAs(staff('order_manager'))->post(route('admin.orders.store'), [
        'customer' => ['name' => 'Known', 'phone' => '0912222222', 'email' => 'known@example.test'],
        'shipping_address' => ['city' => 'Tripoli', 'area' => 'Center'],
        'payment_method' => 'cod',
        'items' => [['product_id' => $p->id, 'quantity' => 1]],
    ])->assertSessionHasNoErrors();

    expect(Order::latest('id')->firstOrFail()->user_id)->toBe($known->id);
});

// ------------------------------------------------------------------- coupons

it('MON-13 (check): cancel releases coupon usage; used_count stays equal to redemption rows', function (): void {
    $p = mgProduct(10_000);
    $coupon = Coupon::factory()->create(['code' => 'ONE', 'value_cents' => 1_000, 'usage_limit' => 1]);
    $o1 = mgOrder(customer(), [[$p, 1]], coupon: 'ONE');
    expect($coupon->refresh()->used_count)->toBe(1)->and($coupon->redemptions()->count())->toBe(1);
    app(CancelOrder::class)->handle($o1);
    expect($coupon->refresh()->used_count)->toBe(0)->and($coupon->redemptions()->count())->toBe(0);
    mgOrder(customer(), [[$p, 1]], coupon: 'ONE');
    expect($coupon->refresh()->used_count)->toBe(1);
});

// ------------------------------------------------------------------- reports

it('MON-14: a fully refunded (still completed) order does not show as profit in the sales report', function (): void {
    $p = mgProduct(10_000, cost: 0);
    $order = mgOrder(customer(), [[$p, 1]]);
    app(UpdateOrderStatus::class)->handle($order, OrderStatus::Completed);
    app(CreateRefund::class)->handle($order->refresh(), 10_000, RefundMethod::Cash); // refund page path

    [$from, $to] = mgWholeRange();
    $s = app(ReportService::class)->salesSummary($from, $to);

    expect($s['refunded_formatted'])->toBe(Money::format(10_000), 'refunded='.$s['refunded_formatted'])
        ->and($s['estimated_profit_formatted'])->toBe(Money::format(0), 'profit='.$s['estimated_profit_formatted'].' revenue='.$s['revenue_formatted']);
});

it('MON-15: a small partial refund via "resolve issue" does not wipe the whole order out of revenue', function (): void {
    $p = mgProduct(100_000);
    $order = mgOrder(customer(), [[$p, 1]]);
    app(UpdateOrderStatus::class)->handle($order, OrderStatus::Completed); // 1000 collected
    app(ResolveOrderIssue::class)->handle($order->refresh(), 'scratched box', 1_000, RefundMethod::Cash, restock: false);

    [$from, $to] = mgWholeRange();
    $revenue = app(ReportService::class)->salesSummary($from, $to)['revenue_formatted'];

    // Either gross (1000, with the 10 refund shown separately) or net (990) — never 0.
    expect($revenue)->toBeIn([Money::format(100_000), Money::format(99_000)]);
});

it('MON-16: invoice report "outstanding" does not bill cancelled orders', function (): void {
    $p = mgProduct(10_000);
    $order = mgOrder(customer(), [[$p, 1]]);
    app(CancelOrder::class)->handle($order);

    [$from, $to] = mgWholeRange();
    $out = app(ReportService::class)->invoiceSummary($from, $to)['outstanding_formatted'];
    expect($out)->toBe(Money::format(0), 'outstanding='.$out);
});

it('MON-17: an order placed at 00:30 Libya time (UTC+2) on Oct 2 is reported on Oct 2, not Oct 1', function (): void {
    $p = mgProduct(10_000);
    $this->travelTo(CarbonImmutable::parse('2026-10-01 22:30:00', 'UTC')); // 2026-10-02 00:30 Africa/Tripoli
    mgOrder(customer(), [[$p, 1]]);
    $this->travelBack();

    [$from, $to] = ReportDateRange::fromRequest(Request::create('/', 'GET', ['from' => '2026-10-02', 'to' => '2026-10-02']));

    expect(app(ReportService::class)->salesSummary($from, $to)['orders'])->toBe(1);
});

it('MON-18 (check): report revenue / discounts / payment split match a known mixed set of orders', function (): void {
    $p = mgProduct(10_000);
    Coupon::factory()->create(['code' => 'TEN', 'value_cents' => 1_000]);
    $paid = mgOrder(customer(), [[$p, 1]], coupon: 'TEN');                 // 90, cod, completed
    app(UpdateOrderStatus::class)->handle($paid, OrderStatus::Completed);
    mgOrder(customer(), [[$p, 2]], PaymentMethod::BankTransfer);            // 200, open
    $cancelled = mgOrder(customer(), [[$p, 1]]);
    app(CancelOrder::class)->handle($cancelled);                             // excluded

    [$from, $to] = mgWholeRange();
    $svc = app(ReportService::class);
    $s = $svc->salesSummary($from, $to);
    expect($s['orders'])->toBe(2)
        ->and($s['revenue_formatted'])->toBe(Money::format(29_000))
        ->and($s['discounts_formatted'])->toBe(Money::format(1_000));
    $split = $svc->salesByPaymentMethod($from, $to)->keyBy('method');
    expect($split['cod']['revenue_formatted'])->toBe(Money::format(9_000))
        ->and($split['bank_transfer']['revenue_formatted'])->toBe(Money::format(20_000));
});
