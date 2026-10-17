<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Reviews\SubmitReview;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ReviewResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * A product's approved reviews, newest first, with the rating summary and —
 * when the request carries a customer's token — whether they may review it.
 */
final class ProductReviewController extends Controller
{
    public function index(Request $request, Product $product, SubmitReview $reviews): AnonymousResourceCollection
    {
        abort_unless($product->status->isVisibleInStore(), 404);

        $page = $product->approvedReviews()
            ->with('user:id,name')
            ->latest()
            ->orderByDesc('id')
            ->paginate(min(50, max(1, (int) $request->query('per_page', '10'))));

        $distribution = $product->approvedReviews()
            ->selectRaw('rating, COUNT(*) AS aggregate')
            ->groupBy('rating')
            ->pluck('aggregate', 'rating');

        $count = (int) $distribution->sum();
        $eligibility = $reviews->eligibility($request->user(), $product);

        return ReviewResource::collection($page)->additional([
            'summary' => [
                'avg' => $count > 0 ? round((float) $product->approvedReviews()->avg('rating'), 1) : null,
                'count' => $count,
                // Every star from 5 to 1, zeros included, so a bar chart needs no gaps filled.
                'distribution' => (object) collect([5, 4, 3, 2, 1])
                    ->mapWithKeys(fn (int $stars): array => [$stars => (int) ($distribution[$stars] ?? 0)])
                    ->all(),
            ],
            'can_review' => $eligibility === SubmitReview::ELIGIBLE,
            'review_eligibility' => $eligibility,
        ]);
    }
}
