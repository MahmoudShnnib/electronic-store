<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Concerns;

use Illuminate\Http\Request;

/**
 * The API group has no locale middleware (adding one is a change in a
 * confirm-first zone — see docs/adr/0001). Content endpoints localise their
 * product cards from `?locale=` or Accept-Language instead.
 */
trait ResolvesApiLocale
{
    /** @var list<string> */
    private array $apiLocales = ['ar', 'en'];

    private function applyApiLocale(Request $request): string
    {
        $locale = (string) $request->query('locale', '');

        if (! in_array($locale, $this->apiLocales, true)) {
            $locale = $request->getPreferredLanguage($this->apiLocales) ?? config('app.locale');
        }

        app()->setLocale($locale);

        return $locale;
    }
}
