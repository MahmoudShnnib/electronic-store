<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Processing = 'processing';
    case ReadyForShipping = 'ready_for_shipping';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Returned = 'returned';
    case Refunded = 'refunded';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Processing, self::Cancelled],
            self::Processing => [self::ReadyForShipping, self::Cancelled],
            self::ReadyForShipping => [self::Shipped, self::Cancelled],
            self::Shipped => [self::Delivered],
            self::Delivered => [self::Completed, self::Returned],
            self::Completed => [self::Returned],
            self::Returned => [self::Refunded],
            self::Cancelled, self::Refunded => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isCancellable(): bool
    {
        return $this->canTransitionTo(self::Cancelled);
    }

    /** Statuses where stock is still reserved (not yet shipped, not released). */
    public function holdsReservation(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed, self::Processing, self::ReadyForShipping], true);
    }

    public function label(): string
    {
        return __('enums.order_status.'.$this->value);
    }
}
