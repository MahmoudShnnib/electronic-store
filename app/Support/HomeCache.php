<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * The homepage caches its product sections and category rail for ten minutes.
 * Without explicit invalidation an admin who uploads a category image or
 * features a product would not see it on the storefront for up to ten minutes
 * and would reasonably assume the upload failed.
 */
final class HomeCache
{
    /** @var list<string> */
    private const KEYS = [
        'home.sections',
        'home.category_cards',
        'home.category_sections',
    ];

    public static function flush(): void
    {
        foreach (self::KEYS as $key) {
            Cache::forget($key);
        }
    }
}
