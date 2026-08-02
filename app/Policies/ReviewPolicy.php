<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\ReviewStatus;
use App\Models\Review;
use App\Models\User;

final class ReviewPolicy
{
    public function update(User $user, Review $review): bool
    {
        return $review->user_id === $user->id && $review->status === ReviewStatus::Pending;
    }

    public function delete(User $user, Review $review): bool
    {
        return $review->user_id === $user->id || $user->can('reviews.moderate');
    }

    public function moderate(User $user, Review $review): bool
    {
        return $user->can('reviews.moderate');
    }
}
