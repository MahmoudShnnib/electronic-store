<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesApiLocale;
use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Newsletter sign-up from the SPA's newsletter section (the API twin of web
 * /newsletter). Signing up twice is not an error: the reply is the same and
 * the first sign-up's language is kept.
 */
final class NewsletterController extends Controller
{
    use ResolvesApiLocale;

    public function store(Request $request): JsonResponse
    {
        $locale = $this->applyApiLocale($request);

        $validated = $request->validate([
            'email' => ['required', 'email:filter', 'max:255'],
        ]);

        $subscriber = NewsletterSubscriber::query()->firstOrCreate(
            ['email' => mb_strtolower($validated['email'])],
            ['locale' => $locale],
        );

        return response()->json(
            ['message' => __('You are subscribed — deals land in your inbox first.')],
            $subscriber->wasRecentlyCreated ? 201 : 200,
            options: JSON_UNESCAPED_UNICODE,
        );
    }
}
