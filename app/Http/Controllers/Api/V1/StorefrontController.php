<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Storefront\PagePayloads;
use App\Support\Content\ContentCache;
use Illuminate\Http\JsonResponse;

/**
 * Everything the storefront SPA needs on boot: currency, contact, navigation,
 * checkout options and the published pages. `version` changes on any content
 * or catalogue edit, so the SPA can tell its persisted copy is stale.
 */
final class StorefrontController extends Controller
{
    public function __invoke(PagePayloads $payloads): JsonResponse
    {
        return response()->json([
            'data' => [
                'version' => ContentCache::version(),
                ...ContentCache::remember('storefront', fn (): array => $payloads->storefront()),
            ],
        ], options: JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
