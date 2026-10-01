<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class OrderStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $status = $this->order->status->label();

        return (new MailMessage)
            ->subject(__('Order :number is now :status', ['number' => $this->order->number, 'status' => $status]))
            ->line(__('Your order :number status changed to: :status.', ['number' => $this->order->number, 'status' => $status]))
            ->action(__('View order'), url("/account/orders/{$this->order->id}"))
            ->line(__('Thank you for shopping with us.'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'order_status_changed',
            'order_id' => $this->order->id,
            'order_number' => $this->order->number,
            'status' => $this->order->status->value,
        ];
    }
}
