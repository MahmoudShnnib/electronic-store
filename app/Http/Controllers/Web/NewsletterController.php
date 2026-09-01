<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Stores footer newsletter sign-ups. */
final class NewsletterController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:filter', 'max:255'],
        ]);

        NewsletterSubscriber::query()->firstOrCreate(
            ['email' => mb_strtolower($validated['email'])],
            ['locale' => app()->getLocale()],
        );

        return back()->with('success', __('You are subscribed — deals land in your inbox first.'));
    }
}
