<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Reviews;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Reviews\ModerateReviewRequest;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;

/** Approves or rejects a customer review. */
final class ModerateReviewController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:reviews.moderate'];
    }

    public function __invoke(ModerateReviewRequest $request, Review $review): RedirectResponse
    {
        $validated = $request->validated();

        $review->update([
            'status' => ReviewStatus::from($validated['status']),
            'moderated_by' => $request->user()->id,
            'moderated_at' => now(),
        ]);

        return back()->with('success', 'Review '.$validated['status'].'.');
    }
}
