<?php

declare(strict_types=1);

namespace App\Support\Content;

use App\Enums\PageSectionType;
use App\Enums\ProductSource;
use App\Models\Page;

/**
 * The system pages every install has. The home page's sections reproduce the
 * Inertia storefront's hardcoded layout (Home.vue), so the SPA renders the
 * same page before anyone opens the page builder.
 *
 * The static pages mirror the Inertia shop's /pages/{page} URLs. Each starts
 * with one empty rich-text section, which the content API fills from the
 * dashboard Settings text (`page.{key}`) until someone writes the page in the
 * page builder — so both shops show the same text during the move.
 *
 * Idempotent: an existing page is left exactly as the admin made it.
 */
final class DefaultPages
{
    /** @var array<string, array{0: string, 1: string}> key => [title, title_ar] */
    public const STATIC_PAGES = [
        'about' => ['About us', 'من نحن'],
        'contact' => ['Contact us', 'اتصل بنا'],
        'privacy-policy' => ['Privacy policy', 'سياسة الخصوصية'],
        'terms' => ['Terms and conditions', 'الشروط والأحكام'],
        'return-policy' => ['Return policy', 'سياسة الاسترجاع'],
    ];

    public static function install(): void
    {
        self::create('home', 'Home', 'الرئيسية', self::homeSections());

        foreach (self::STATIC_PAGES as $key => [$title, $titleAr]) {
            self::create($key, $title, $titleAr, [[PageSectionType::RichText, ['body' => null, 'body_ar' => null]]]);
        }
    }

    /**
     * @param  list<array{0: PageSectionType, 1: array<string, mixed>}>  $sections
     */
    private static function create(string $key, string $title, string $titleAr, array $sections): void
    {
        $page = Page::query()->firstOrCreate(
            ['key' => $key],
            ['title' => $title, 'title_ar' => $titleAr, 'is_published' => true, 'is_system' => true],
        );

        if (! $page->wasRecentlyCreated) {
            return;
        }

        foreach ($sections as $position => [$type, $data]) {
            $page->sections()->create([
                'type' => $type,
                'sort_order' => $position,
                'is_active' => true,
                'data' => $data,
            ]);
        }
    }

    /**
     * @return list<array{0: PageSectionType, 1: array<string, mixed>}>
     */
    public static function homeSections(): array
    {
        $carousel = fn (ProductSource $source, string $title, string $titleAr, string $icon): array => [
            PageSectionType::ProductCarousel,
            ['source' => $source->value, 'title' => $title, 'title_ar' => $titleAr, 'icon' => $icon, 'limit' => 6],
        ];

        return [
            [PageSectionType::Hero, ['slides' => []]],
            [PageSectionType::PromoTiles, ['tiles' => []]],
            [PageSectionType::CategoryRail, ['title' => 'Shop by category', 'title_ar' => 'تسوق حسب الفئة']],
            [PageSectionType::TrustStrip, ['items' => [
                ['icon' => 'truck', 'title' => 'Delivery across Libya', 'title_ar' => 'توصيل لكل ليبيا', 'text' => null, 'text_ar' => null],
                ['icon' => 'wallet', 'title' => 'Cash on delivery', 'title_ar' => 'الدفع عند الاستلام', 'text' => null, 'text_ar' => null],
                ['icon' => 'shield', 'title' => 'Genuine products', 'title_ar' => 'منتجات أصلية', 'text' => null, 'text_ar' => null],
                ['icon' => 'headset', 'title' => 'Real support', 'title_ar' => 'دعم حقيقي', 'text' => null, 'text_ar' => null],
            ]]],
            $carousel(ProductSource::Featured, 'Featured', 'منتجات مميزة', 'star'),
            $carousel(ProductSource::OnSale, 'On sale', 'عروض وتخفيضات', 'tag'),
            [PageSectionType::CategorySpotlight, []],
            $carousel(ProductSource::NewArrivals, 'New arrivals', 'وصل حديثاً', 'sparkles'),
            $carousel(ProductSource::BestSellers, 'Best sellers', 'الأكثر مبيعاً', 'flame'),
            [PageSectionType::BrandStrip, ['title' => 'Brands', 'title_ar' => 'العلامات التجارية']],
            [PageSectionType::Newsletter, [
                'title' => 'Get the deals first', 'title_ar' => 'كن أول من يعرف بالعروض',
                'text' => null, 'text_ar' => null,
            ]],
        ];
    }
}
