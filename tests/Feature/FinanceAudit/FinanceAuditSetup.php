<?php

declare(strict_types=1);

/*
 * Shared fixtures for the finance audit scenarios (tests/Feature/FinanceAudit/*Test.php).
 * Not a test file itself: required by each scenario file.
 */

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

require_once __DIR__.'/../../Qa/FinanceSnapshot.php';

/** Shop with three products: PHONE ×10, CABLE ×50, MUG ×1 (the last unit). */
function finSetUp($test): void
{
    $test->travelTo(CarbonImmutable::parse('2026-10-05 12:00:00'));
    Notification::fake();

    $test->admin = staff('super_admin');
    $test->buyer = customer(['email' => 'fin-buyer@example.com']);
    $test->main = defaultWarehouse();

    $make = fn (string $sku, string $name, int $price, int $cost) => Product::factory()->create([
        'sku' => $sku, 'name' => $name, 'price_cents' => $price, 'cost_price_cents' => $cost, 'sale_price_cents' => null,
    ]);
    $test->phone = $make('PHONE', 'Fin Phone', 100_000, 70_000);
    $test->cable = $make('CABLE', 'Fin Cable', 5_000, 2_000);
    $test->mug = $make('MUG', 'Fin Mug', 2_000, 500);

    // Opening stock through the admin stock screen, like staff would.
    foreach ([[$test->phone, 10], [$test->cable, 50], [$test->mug, 1]] as [$product, $qty]) {
        $test->actingAs($test->admin)->post('/admin/inventory/adjust', [
            'warehouse_id' => $test->main->id, 'product_id' => $product->id, 'quantity_change' => $qty,
            'type' => 'initial', 'reason' => 'Opening stock',
        ])->assertSessionHasNoErrors();
    }
}

function finCheckoutPayload(User $user, array $over = []): array
{
    return array_replace_recursive([
        'customer' => ['name' => 'Fin Buyer', 'email' => $user->email, 'phone' => '0912345678'],
        'shipping_address' => ['city' => 'Tripoli', 'area' => 'Hay Al-Andalus'],
        'payment_method' => 'cod',
    ], $over);
}

/**
 * Buys through the storefront routes like a customer: add each line to the cart, check out.
 *
 * @param  list<array{0: Product, 1: int}>  $lines
 */
function finBuy($test, User $user, array $lines, array $over = []): Order
{
    foreach ($lines as [$product, $qty]) {
        $test->actingAs($user)->post('/cart/items', ['product_id' => $product->id, 'quantity' => $qty])
            ->assertSessionHasNoErrors();
    }
    $before = Order::query()->max('id');
    $test->actingAs($user)->post('/checkout', finCheckoutPayload($user, $over))->assertSessionHasNoErrors();
    $order = Order::query()->latest('id')->firstOrFail();
    expect($order->id)->not->toBe($before);

    return $order;
}

/**
 * Places that appear when a new unpaid order is placed (order row, its lines, the admin
 * order page and its invoice-report row). Lines: [sku, unit price, qty, unit cost].
 *
 * @param  list<array{0: string, 1: int, 2: int, 3: int}>  $lines
 */
function finNewOrderPlaces(Order $order, array $lines, string $status = 'in_progress', string $paymentStatus = 'unpaid', int $paid = 0): array
{
    $total = 0;
    $places = [];
    foreach ($lines as [$sku, $price, $qty, $cost]) {
        $k = "line.{$order->number}.$sku";
        $places += ["$k.unit_price" => $price, "$k.qty" => $qty, "$k.discount" => 0, "$k.line_total" => $price * $qty, "$k.unit_cost" => $cost];
        $total += $price * $qty;
    }

    return $places + [
        "order.{$order->number}.status" => $status,
        "order.{$order->number}.payment_status" => $paymentStatus,
        "order.{$order->number}.subtotal" => $total,
        "order.{$order->number}.discount" => 0,
        "order.{$order->number}.total" => $total,
        "adminorder.{$order->number}.paid" => finFmt($paid),
        "adminorder.{$order->number}.refunded" => finFmt(0),
        "adminorder.{$order->number}.outstanding" => finFmt($total - $paid),
        "report.invoice.{$order->number}.paid" => finFmt($paid),
        "report.invoice.{$order->number}.outstanding" => finFmt($total - $paid),
    ];
}

function finComplete($test, Order $order): void
{
    $test->actingAs($test->admin)->post("/admin/orders/{$order->id}/status", ['status' => 'completed'])->assertSessionHasNoErrors();
}

/** The admin return form: refund an amount (LYD) and put the listed units back on the shelf. */
function finReturn($test, Order $order, int $refundCents, array $unitsBySku): void
{
    $items = $order->items()->get()->mapWithKeys(fn ($i) => [$i->id => $unitsBySku[$i->sku] ?? 0])->all();
    $test->actingAs($test->admin)->post("/admin/orders/{$order->id}/issue", [
        'reason' => 'Customer returned goods', 'amount' => $refundCents / 100, 'method' => 'cash', 'restock' => true, 'items' => $items,
    ])->assertSessionHasNoErrors();
}

/** The admin stock dialog. Returns the response so callers can assert errors. */
function finAdjust($test, Product $product, int $qty, string $type, string $reason = 'Audit adjustment', array $extra = [])
{
    return $test->actingAs($test->admin)->post('/admin/inventory/adjust', [
        'warehouse_id' => $test->main->id, 'product_id' => $product->id, 'quantity_change' => $qty,
        'type' => $type, 'reason' => $reason, ...$extra,
    ]);
}
