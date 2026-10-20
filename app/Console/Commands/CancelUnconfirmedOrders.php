<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Orders\CancelOrder;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use Illuminate\Console\Command;
use Throwable;

/**
 * Cancels cash-on-delivery orders from the website or app that nobody managed
 * to confirm in time (store.unconfirmed_cancel_hours, 72 by default), so a
 * fake or abandoned order stops holding stock. Orders already confirmed, out
 * with a driver, or with money received are never touched. Runs hourly.
 */
final class CancelUnconfirmedOrders extends Command
{
    protected $signature = 'orders:cancel-unconfirmed {--dry-run : List the orders without cancelling them}';

    protected $description = 'Cancel unconfirmed cash-on-delivery orders older than the configured limit and release their stock';

    public function handle(CancelOrder $cancelOrder): int
    {
        $hours = (int) config('store.unconfirmed_cancel_hours');

        if ($hours <= 0) {
            $this->info('Automatic cancellation is off (store.unconfirmed_cancel_hours = 0).');

            return self::SUCCESS;
        }

        $orders = Order::query()
            ->where('status', OrderStatus::InProgress)
            ->where('payment_method', PaymentMethod::CashOnDelivery)
            ->whereIn('placed_via', ['web', 'api'])
            ->whereNull('confirmed_at')
            ->whereNull('shipped_at')
            ->where('created_at', '<=', now()->subHours($hours))
            ->get();

        foreach ($orders as $order) {
            if ($order->netPaidCents() > 0) {
                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("Would cancel {$order->number}");

                continue;
            }

            try {
                $cancelOrder->handle($order, __('Not confirmed within :hours hours — cancelled automatically.', ['hours' => $hours], config('store.default_locale')));
                $this->line("Cancelled {$order->number}");
            } catch (Throwable $e) {
                report($e);
                $this->warn("Could not cancel {$order->number}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
