<?php

declare(strict_types=1);

namespace App\Http\Resources\V1;

use App\Models\Review;
use App\Support\Dates;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An approved review as the product page lists it.
 *
 * @mixin Review
 */
final class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'title' => $this->title,
            'body' => $this->body,
            'author' => $this->user?->name ?? 'Anonymous',
            'verified' => $this->isVerifiedPurchase(),
            'created_at' => $this->created_at?->toIso8601String(),
            'created_at_formatted' => Dates::date($this->created_at),
        ];
    }
}
