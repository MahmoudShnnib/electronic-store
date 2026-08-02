<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Reviews\ModerateReviewRequest;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

final class ReviewController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:reviews.moderate'];
    }

    public function index(Request $request): Response
    {
        $reviews = Review::query()
            ->with(['user:id,name,email', 'product:id,name,slug'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', (string) $request->query('status')))
            ->when($request->filled('rating'), fn ($q) => $q->where('rating', (int) $request->query('rating')))
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Review $r) => [
                'id' => $r->id,
                'product' => $r->product?->only(['id', 'name', 'slug']),
                'customer' => $r->user?->only(['id', 'name', 'email']),
                'rating' => $r->rating,
                'title' => $r->title,
                'body' => $r->body,
                'status' => $r->status->value,
                'verified' => $r->isVerifiedPurchase(),
                'created_at' => $r->created_at->toDateTimeString(),
            ]);

        return Inertia::render('admin/reviews/Index', [
            'reviews' => $reviews,
            'statuses' => array_map(fn (ReviewStatus $s) => ['value' => $s->value, 'label' => $s->label()], ReviewStatus::cases()),
            'filters' => (object) $request->only(['status', 'rating']),
        ]);
    }

    public function moderate(ModerateReviewRequest $request, Review $review): RedirectResponse
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
