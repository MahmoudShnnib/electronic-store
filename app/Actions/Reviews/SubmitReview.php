<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Enums\ReviewStatus;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * One review per user per product; only verified purchasers may review.
 * Reviews enter moderation before appearing on the storefront.
 */
final class SubmitReview
{
    public function handle(User $user, Product $product, int $rating, ?string $title, ?string $body): Review
    {
        if ($product->reviews()->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages(['rating' => __('You have already reviewed this product.')]);
        }

        $orderItem = OrderItem::query()
            ->where('product_id', $product->id)
            ->whereHas('order', fn ($q) => $q
                ->where('user_id', $user->id)
                ->where('status', 'completed'))
            ->latest('id')
            ->first();

        if ($orderItem === null) {
            throw ValidationException::withMessages([
                'rating' => __('You can only review products from orders that were completed.'),
            ]);
        }

        return Review::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'order_item_id' => $orderItem->id,
            'rating' => $rating,
            'title' => $title,
            'body' => $body,
            'status' => ReviewStatus::Pending,
        ]);
    }
}
