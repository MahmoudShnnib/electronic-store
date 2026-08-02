<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Reviews\SubmitReview;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reviews\SubmitReviewRequest;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

final class ReviewController extends Controller
{
    /**
     * Submit a review for a purchased product (enters moderation).
     */
    public function store(SubmitReviewRequest $request, SubmitReview $submitReview): JsonResponse
    {
        $validated = $request->validated();

        $review = $submitReview->handle(
            $request->user(),
            Product::query()->findOrFail($validated['product_id']),
            (int) $validated['rating'],
            $validated['title'] ?? null,
            $validated['body'] ?? null,
        );

        return response()->json([
            'message' => 'Review submitted and pending moderation.',
            'data' => ['id' => $review->id, 'status' => $review->status->value],
        ], 201);
    }
}
