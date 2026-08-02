<?php

declare(strict_types=1);

namespace App\Enums;

enum ReturnStatus: string
{
    case Requested = 'requested';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case ItemReceived = 'item_received';
    case Refunded = 'refunded';
    case Closed = 'closed';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Requested => [self::UnderReview, self::Approved, self::Rejected],
            self::UnderReview => [self::Approved, self::Rejected],
            self::Approved => [self::ItemReceived, self::Closed],
            self::ItemReceived => [self::Refunded, self::Closed],
            self::Refunded => [self::Closed],
            self::Rejected => [self::Closed],
            self::Closed => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function label(): string
    {
        return __('enums.return_status.'.$this->value);
    }
}
