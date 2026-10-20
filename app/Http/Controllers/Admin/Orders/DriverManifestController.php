<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Order;
use App\Models\Setting;
use App\Services\Admin\OrderQueryService;
use App\Support\Dates;
use App\Support\Money;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A driver's run sheet: every order out with them, where it goes, and the cash
 * to collect — signed at the door, and counted against the total when the
 * driver comes back.
 */
final class DriverManifestController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:orders.view'];
    }

    public function __invoke(Driver $driver): Response
    {
        $orders = Order::query()
            ->where('driver_id', $driver->id)
            ->where('status', OrderStatus::InProgress)
            ->whereNotNull('shipped_at')
            ->orderBy('shipped_at')
            ->get();

        $collect = fn (Order $o): int => $o->payment_status === PaymentStatus::Unpaid ? (int) $o->total_cents : 0;

        return Inertia::render('admin/drivers/Manifest', [
            'driver' => $driver->only(['id', 'name', 'phone']),
            'store' => Setting::get('store_name') ?: config('app.name'),
            'printed_at' => Dates::dateTime(now()),
            'orders' => $orders->map(fn (Order $o) => [
                'id' => $o->id,
                'number' => $o->number,
                'name' => $o->shipping_address['name'] ?? $o->customer['name'] ?? '—',
                'phone' => $o->shipping_address['phone'] ?? $o->customer['phone'] ?? null,
                'address' => trim(implode(' — ', array_filter([
                    OrderQueryService::cityLabel($o->shipping_address['city'] ?? null),
                    $o->shipping_address['area'] ?? null,
                ]))),
                'collect_formatted' => $collect($o) > 0 ? Money::format($collect($o), $o->currency) : null,
                'shipped_at' => Dates::dateTime($o->shipped_at),
            ])->values(),
            'total_formatted' => Money::format($orders->sum($collect)),
        ]);
    }
}
