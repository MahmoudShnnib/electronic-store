<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class OrderPlacedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Order $order)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject("Order {$this->order->number} received")
            ->greeting("Thank you for your order!")
            ->line("We've received order {$this->order->number} totalling ".Money::format($this->order->total_cents, $this->order->currency).'.')
            ->line('Payment method: '.$this->order->payment_method->label())
            ->action('Track your order', url("/account/orders/{$this->order->id}"))
            ->line('We will notify you as your order progresses.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'order_placed',
            'order_id' => $this->order->id,
            'order_number' => $this->order->number,
            'total_cents' => $this->order->total_cents,
        ];
    }
}
