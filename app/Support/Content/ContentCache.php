<?php

declare(strict_types=1);

namespace App\Support\Content;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Caches content API payloads under a version token. Any content edit bumps
 * the version, which orphans every cached payload at once (no key list to keep
 * in sync) and tells the SPA its boot payload is stale.
 *
 * Product-driven sections also depend on the catalogue, so HomeCache::flush()
 * bumps this too.
 */
final class ContentCache
{
    private const VERSION_KEY = 'content.version';

    private const TTL_MINUTES = 10;

    public static function version(): string
    {
        return Cache::rememberForever(self::VERSION_KEY, fn (): string => Str::random(12));
    }

    public static function bump(): void
    {
        Cache::forever(self::VERSION_KEY, Str::random(12));
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function remember(string $key, Closure $callback): mixed
    {
        return Cache::remember('content.'.self::version().'.'.$key, now()->addMinutes(self::TTL_MINUTES), $callback);
    }
}
