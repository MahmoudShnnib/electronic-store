<?php

use App\Http\Controllers\Web\StorefrontShellController;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

beforeEach(function (): void {
    $dist = storage_path('framework/testing/spa-dist');
    @mkdir($dist, 0777, true);
    file_put_contents("{$dist}/index.html", <<<'HTML'
        <!doctype html>
        <html lang="ar" dir="rtl">
          <head>
            <meta charset="UTF-8" />
            <title>Generic</title>
            <meta name="description" content="Generic description" />
          </head>
          <body><div id="root"></div></body>
        </html>
        HTML);

    config([
        'store.storefront_dist' => $dist,
        'store.storefront_url' => 'https://shop.example.ly',
    ]);
});

/** The shell for one storefront path, as the storefront host would serve it. */
function shell(string $path): Response
{
    return app(StorefrontShellController::class)->show(Request::create($path));
}

it('puts a product\'s own title, description, image and price in the page head', function (): void {
    $product = Product::factory()->create([
        'name' => 'Gaming Mouse', 'name_ar' => 'فأرة ألعاب', 'slug' => 'gaming-mouse',
        'short_description_ar' => 'فأرة سريعة </script><script>alert(1)</script>',
        'price_cents' => 12_500,
        'brand_id' => Brand::factory()->create(['name' => 'Logitech'])->id,
    ]);
    $product->images()->create(['path' => 'products/mouse.jpg', 'is_primary' => true, 'position' => 0]);
    giveStock($product, 3);

    $response = shell('/products/gaming-mouse');
    $html = $response->getContent();

    expect($response->getStatusCode())->toBe(200)
        ->and($html)->toContain('<title>فأرة ألعاب — ')
        ->and($html)->not->toContain('<title>Generic</title>')
        ->and($html)->toContain('<meta property="og:image" content="')
        ->and($html)->toContain('products/mouse.jpg')
        ->and($html)->toContain('<link rel="canonical" href="https://shop.example.ly/products/gaming-mouse" />')
        ->and($html)->toContain('"@type":"Product"')
        ->and($html)->toContain('"price":"125.000"')
        ->and($html)->toContain('"availability":"https://schema.org/InStock"')
        // A description can't break out of the JSON-LD script or the meta tag.
        ->and($html)->not->toContain('<script>alert(1)</script>');
});

it('prices a variant product by its cheapest variant', function (): void {
    $product = Product::factory()->withVariants()->create(['slug' => 'phone', 'price_cents' => 900_000]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'price_cents' => 119_900]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'price_cents' => 132_900]);

    expect(shell('/products/phone')->getContent())->toContain('"price":"1199.000"');
});

it('answers 404 with noindex for a product that does not exist', function (): void {
    $response = shell('/products/nope');

    expect($response->getStatusCode())->toBe(404)
        ->and($response->getContent())->toContain('<meta name="robots" content="noindex" />');
});

it('serves every other path with the store defaults', function (): void {
    $response = shell('/cart');

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toContain('<meta name="description" content="'.e(config('store.seo_description')).'" />');
});

it('lists products in the sitemap and keeps private pages out of search', function (): void {
    Product::factory()->create(['slug' => 'listed-product']);
    Product::factory()->draft()->create(['slug' => 'hidden-draft']);

    $sitemap = app(StorefrontShellController::class)->sitemap()->getContent();
    $robots = app(StorefrontShellController::class)->robots()->getContent();

    expect($sitemap)->toContain('https://shop.example.ly/products/listed-product')
        ->and($sitemap)->not->toContain('hidden-draft')
        ->and($sitemap)->toContain('https://shop.example.ly/p/about')
        ->and($robots)->toContain('Disallow: /checkout')
        ->and($robots)->toContain('Sitemap: https://shop.example.ly/sitemap.xml');
});
