<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Four states, no more: an order is being worked on, it is done, it was called
 * off, or the money went back. The old ten-step shipping pipeline
 * (pending/confirmed/processing/ready_for_shipping/shipped/delivered/returned)
 * was collapsed into these — see the 2026-08-16 migration for the mapping.
 */
enum OrderStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::InProgress => [self::Completed, self::Cancelled],
            self::Completed => [self::Refunded],
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

    /** Statuses where stock is still reserved — the goods have not left yet. */
    public function holdsReservation(): bool
    {
        return $this === self::InProgress;
    }

    public function label(): string
    {
        return __('enums.order_status.'.$this->value);
    }
}
