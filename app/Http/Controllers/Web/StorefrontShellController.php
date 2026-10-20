<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Services\Storefront\StorefrontSeo;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Serves the storefront SPA's index.html with the right tags already in the
 * <head>, so WhatsApp / Facebook / Telegram previews and Google see the
 * product's own name, image and price without running JavaScript. The page
 * body is still the SPA, which takes over in the browser.
 *
 * Only on the storefront's own host (store.storefront_host), so it never
 * shadows the Inertia shop while both are live; the built SPA lives at
 * store.storefront_dist. Also answers /sitemap.xml and /robots.txt there.
 */
final class StorefrontShellController extends Controller
{
    public function __construct(private readonly StorefrontSeo $seo) {}

    public function show(Request $request): Response
    {
        $meta = $this->seo->forPath($request->path() === '/' ? '' : $request->path());
        $shell = $this->shell();

        abort_if($shell === null, 503, 'The storefront build is missing (store.storefront_dist).');

        return response($this->inject($shell, $meta), $meta['status'])
            ->header('Content-Type', 'text/html; charset=UTF-8')
            // The HTML is cheap to rebuild and prices change: keep it short-lived.
            ->header('Cache-Control', 'public, max-age=300');
    }

    public function sitemap(): Response
    {
        $base = $this->seo->baseUrl();

        $xml = Cache::remember('storefront.sitemap', now()->addHour(), function () use ($base): string {
            $urls = collect([['loc' => "{$base}/", 'lastmod' => null]])
                ->merge(Product::query()->visibleInStore()->get(['slug', 'updated_at'])->map(fn (Product $p) => ['loc' => "{$base}/products/{$p->slug}", 'lastmod' => $p->updated_at]))
                ->merge(Category::query()->active()->get(['slug', 'updated_at'])->map(fn (Category $c) => ['loc' => "{$base}/category/{$c->slug}", 'lastmod' => $c->updated_at]))
                ->merge(Brand::query()->active()->get(['slug', 'updated_at'])->map(fn (Brand $b) => ['loc' => "{$base}/brand/{$b->slug}", 'lastmod' => $b->updated_at]))
                ->merge(Page::query()->published()->where(fn ($q) => $q->whereNull('key')->orWhere('key', '<>', 'home'))->get(['key', 'slug', 'updated_at'])
                    ->map(fn (Page $p) => ['loc' => "{$base}/p/".($p->key ?? $p->slug), 'lastmod' => $p->updated_at]));

            $entries = $urls->map(fn (array $u) => '  <url><loc>'.e($u['loc']).'</loc>'
                .($u['lastmod'] !== null ? '<lastmod>'.$u['lastmod']->toDateString().'</lastmod>' : '')
                .'</url>')->implode("\n");

            return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n{$entries}\n</urlset>\n";
        });

        return response($xml)->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(): Response
    {
        $base = $this->seo->baseUrl();
        $private = ['/cart', '/checkout', '/account', '/login', '/register', '/forgot-password', '/reset-password', '/wishlist', '/search'];

        return response("User-agent: *\n".collect($private)->map(fn ($p) => "Disallow: {$p}")->implode("\n")."\n\nSitemap: {$base}/sitemap.xml\n")
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    private function shell(): ?string
    {
        $file = rtrim((string) config('store.storefront_dist'), '/').'/index.html';

        return is_file($file) ? (string) file_get_contents($file) : null;
    }

    /**
     * Swaps the build's generic <title> and description for this URL's, and
     * adds canonical, Open Graph, Twitter and JSON-LD before </head>.
     *
     * @param  array<string, mixed>  $meta
     */
    private function inject(string $html, array $meta): string
    {
        $html = preg_replace('~<title>.*?</title>~s', '<title>'.e($meta['title']).'</title>', $html, 1) ?? $html;
        $html = preg_replace('~<meta\s+name="description"[^>]*>~i', '<meta name="description" content="'.e($meta['description']).'" />', $html, 1) ?? $html;

        $tags = [
            '<link rel="canonical" href="'.e($meta['url']).'" />',
            '<meta property="og:site_name" content="'.e($this->seo->storeName()).'" />',
            '<meta property="og:type" content="'.e($meta['type']).'" />',
            '<meta property="og:title" content="'.e($meta['title']).'" />',
            '<meta property="og:description" content="'.e($meta['description']).'" />',
            '<meta property="og:url" content="'.e($meta['url']).'" />',
            '<meta property="og:locale" content="ar_LY" />',
            '<meta name="twitter:card" content="'.($meta['image'] !== null ? 'summary_large_image' : 'summary').'" />',
        ];

        if ($meta['image'] !== null) {
            $tags[] = '<meta property="og:image" content="'.e($meta['image']).'" />';
        }

        if (isset($meta['price'])) {
            $tags[] = '<meta property="product:price:amount" content="'.e($meta['price']).'" />';
            $tags[] = '<meta property="product:price:currency" content="'.e((string) config('store.currency')).'" />';
        }

        if ($meta['status'] === 404) {
            $tags[] = '<meta name="robots" content="noindex" />';
        }

        foreach ($meta['json_ld'] as $data) {
            // JSON_HEX_TAG: a "</script>" inside a product description can't close the block.
            $tags[] = '<script type="application/ld+json">'.json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG).'</script>';
        }

        return str_replace('</head>', '    '.implode("\n    ", $tags)."\n  </head>", $html);
    }
}
