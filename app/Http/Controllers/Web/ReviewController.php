<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Reviews\SubmitReview;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reviews\SubmitReviewRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;

final class ReviewController extends Controller
{
    public function store(SubmitReviewRequest $request, Product $product, SubmitReview $submitReview): RedirectResponse
    {
        $validated = $request->validated();

        $submitReview->handle(
            $request->user(),
            $product,
            (int) $validated['rating'],
            $validated['title'] ?? null,
            $validated['body'] ?? null,
        );

        return back()->with('success', __('Thank you! Your review is pending moderation.'));
    }
}
