<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateLocaleRequest;
use Illuminate\Http\RedirectResponse;

final class LocaleController extends Controller
{
    public function update(UpdateLocaleRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $request->session()->put('locale', $validated['locale']);

        $request->user()?->update(['locale' => $validated['locale']]);

        return back();
    }
}
