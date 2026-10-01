<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Order confirmation. Sent in the customer's language (User::preferredLocale,
 * or the language a guest checked out in) and lists what was bought.
 */
final class OrderPlacedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Order $order) {}

    public function via(object $notifiable): array
    {
        // A guest has no account to keep an in-app notification on.
        return $notifiable instanceof AnonymousNotifiable ? ['mail'] : ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $currency = $this->order->currency;
        $mail = (new MailMessage)
            ->subject(__('Order :number received', ['number' => $this->order->number]))
            ->greeting(__('Thank you for your order!'))
            ->line(__("We've received order :number.", ['number' => $this->order->number]));

        foreach ($this->order->items()->get() as $item) {
            $name = $item->variant_name !== null ? "{$item->product_name} — {$item->variant_name}" : $item->product_name;
            $mail->line("{$item->quantity} × {$name} — ".Money::format($item->line_total_cents, $currency, isolate: false));
        }

        $mail->line(__('Total: :total', ['total' => Money::format($this->order->total_cents, $currency, isolate: false)]))
            ->line(__('Payment method: :method', ['method' => $this->order->payment_method->label()]));

        // Guests have no account page to open.
        if (! $notifiable instanceof AnonymousNotifiable) {
            $mail->action(__('Track your order'), url("/account/orders/{$this->order->id}"));
        }

        return $mail->line(__('We will notify you as your order progresses.'));
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
