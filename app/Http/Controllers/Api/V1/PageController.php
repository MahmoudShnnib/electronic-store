<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ResolvesApiLocale;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\Storefront\PagePayloads;
use App\Support\Content\ContentCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** A published page with its active sections resolved, by system key or slug. */
final class PageController extends Controller
{
    use ResolvesApiLocale;

    public function show(Request $request, string $key, PagePayloads $payloads): JsonResponse
    {
        $locale = $this->applyApiLocale($request);

        $body = ContentCache::remember("page.{$key}.{$locale}", function () use ($key, $payloads): ?string {
            $page = Page::query()->published()->addressedBy($key)->first();

            return $page === null
                ? null
                : json_encode(['data' => $payloads->page($page)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        });

        abort_if($body === null, 404);

        return JsonResponse::fromJsonString($body)->header('Content-Language', $locale);
    }
}
