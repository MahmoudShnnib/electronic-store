<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The API's language for one request: `?locale=` first, then Accept-Language,
 * then the store's default (Arabic). The API has no session, so the SPA and
 * the mobile app say which language they show — and cart lines, order
 * statuses and every validation or error message come back in it.
 */
final class SetApiLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('store.locales', ['en']);
        $locale = $request->query('locale');

        if (! is_string($locale) || ! in_array($locale, $supported, true)) {
            $locale = $this->fromAcceptLanguage($request, $supported);
        }

        if (! in_array($locale, $supported, true)) {
            $locale = config('store.default_locale');
        }

        app()->setLocale($locale);

        $response = $next($request);
        $response->headers->set('Content-Language', $locale);

        return $response;
    }

    /**
     * The first language in Accept-Language (already in preference order) that
     * the store offers, matched on its primary tag (`ar-LY` → `ar`). Not
     * Symfony's getPreferredLanguage(): with no match it answers the first
     * supported locale, which would turn a French browser English.
     *
     * @param  list<string>  $supported
     */
    private function fromAcceptLanguage(Request $request, array $supported): ?string
    {
        foreach ($request->getLanguages() as $language) {
            $primary = strtolower(explode('_', str_replace('-', '_', $language))[0]);

            if (in_array($primary, $supported, true)) {
                return $primary;
            }
        }

        return null;
    }
}
