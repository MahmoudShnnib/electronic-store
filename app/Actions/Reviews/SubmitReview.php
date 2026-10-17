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
    public const ELIGIBLE = 'eligible';

    public const GUEST = 'guest';

    public const ALREADY_REVIEWED = 'already_reviewed';

    public const NOT_PURCHASED = 'not_purchased';

    public function handle(User $user, Product $product, int $rating, ?string $title, ?string $body): Review
    {
        match ($this->eligibility($user, $product)) {
            self::ALREADY_REVIEWED => throw ValidationException::withMessages(['rating' => __('You have already reviewed this product.')]),
            self::NOT_PURCHASED => throw ValidationException::withMessages([
                'rating' => __('You can only review products from orders that were completed.'),
            ]),
            default => null,
        };

        return Review::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'order_item_id' => $this->purchase($user, $product)->id,
            'rating' => $rating,
            'title' => $title,
            'body' => $body,
            'status' => ReviewStatus::Pending,
        ]);
    }

    /**
     * Whether this customer may review the product, and if not why — the
     * product page uses it to show the form or say what is missing.
     *
     * @return self::ELIGIBLE|self::GUEST|self::ALREADY_REVIEWED|self::NOT_PURCHASED
     */
    public function eligibility(?User $user, Product $product): string
    {
        return match (true) {
            $user === null => self::GUEST,
            $product->reviews()->where('user_id', $user->id)->exists() => self::ALREADY_REVIEWED,
            $this->purchase($user, $product) === null => self::NOT_PURCHASED,
            default => self::ELIGIBLE,
        };
    }

    /** The latest line of a completed order of theirs containing the product. */
    private function purchase(User $user, Product $product): ?OrderItem
    {
        return OrderItem::query()
            ->where('product_id', $product->id)
            ->whereHas('order', fn ($q) => $q
                ->where('user_id', $user->id)
                ->where('status', 'completed'))
            ->latest('id')
            ->first();
    }
}
