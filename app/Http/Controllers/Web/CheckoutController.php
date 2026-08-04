<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Cart\CalculateCartTotals;
use App\Actions\Cart\ResolveCart;
use App\Actions\Checkout\PlaceOrder;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\CartPayload;
use App\Models\Order;
use App\Services\Payments\PaymentGatewayManager;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CheckoutController extends Controller
{
    public function __construct(
        private readonly ResolveCart $resolveCart,
        private readonly CalculateCartTotals $calculateTotals,
    ) {}

    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $cart = $this->resolveCart->handle($request);
        $totals = $this->calculateTotals->handle($cart, $user?->id);

        if ($totals->isEmpty()) {
            return redirect()->route('cart.show');
        }

        return Inertia::render('storefront/Checkout', [
            'cart' => CartPayload::from($totals),
            'addresses' => $user?->addresses()->get()->map(fn ($a) => [
                'id' => $a->id,
                'label' => $a->label,
                ...$a->toSnapshot(),
                'is_default_shipping' => $a->is_default_shipping,
                'is_default_billing' => $a->is_default_billing,
            ]) ?? [],
            'customer' => [
                'name' => $user?->name ?? '',
                'email' => $user?->email ?? '',
                'phone' => $user?->phone ?? '',
            ],
            'cities' => config('store.cities', []),
            'paymentMethods' => collect(PaymentMethod::cases())
                ->filter(fn (PaymentMethod $m) => in_array($m->value, config('store.payment_methods', ['cod']), true))
                ->map(fn (PaymentMethod $m) => ['value' => $m->value, 'label' => $m->label()])
                ->values()
                ->all(),
        ]);
    }

    public function store(
        CheckoutRequest $request,
        PlaceOrder $placeOrder,
        PaymentGatewayManager $gateways,
    ): RedirectResponse {
        $cart = $this->resolveCart->handle($request);

        $order = $placeOrder->handle($cart, $request->toCheckoutData(), $request->user(), 'web');

        $gateway = $gateways->driverFor($order->payment_method);
        $instructions = $gateway->initiate($order);

        $request->session()->put('last_order_number', $order->number);

        return redirect()
            ->route('checkout.confirmation', $order->number)
            ->with('payment_instructions', $instructions['instructions'] ?? null);
    }

    public function confirmation(Request $request, string $number): Response
    {
        $order = Order::query()
            ->where('number', $number)
            ->with('items')
            ->firstOrFail();

        // Guests may see the confirmation for the order they just placed
        // (session-bound); logged-in users only their own orders.
        if ($request->user() !== null) {
            abort_unless(
                $order->user_id === $request->user()->id || $request->user()->can('orders.view'),
                403,
            );
        } elseif ($request->session()->get('last_order_number') !== $number) {
            abort(403);
        }

        return Inertia::render('storefront/OrderConfirmation', [
            'order' => [
                'number' => $order->number,
                'total_formatted' => Money::format($order->total_cents, $order->currency),
                'payment_method' => $order->payment_method->label(),
                'status' => $order->status->value,
                'items' => $order->items->map(fn ($i) => [
                    'name' => $i->variant_name !== null ? "{$i->product_name} — {$i->variant_name}" : $i->product_name,
                    'quantity' => $i->quantity,
                    'line_total_formatted' => Money::format($i->line_total_cents, $order->currency),
                ]),
            ],
            'paymentInstructions' => $request->session()->get('payment_instructions'),
        ]);
    }
}
