<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The blocks a storefront page is built from. Each type's stored `data` and
 * what the API adds to it are listed in docs/adr/0001-headless-storefront-split.md;
 * the SPA renders them through its section registry.
 */
enum PageSectionType: string
{
    case Hero = 'hero';
    case PromoTiles = 'promo_tiles';
    case CategoryRail = 'category_rail';
    case TrustStrip = 'trust_strip';
    case ProductCarousel = 'product_carousel';
    case CategorySpotlight = 'category_spotlight';
    case BrandStrip = 'brand_strip';
    case ImageBanner = 'image_banner';
    case RichText = 'rich_text';
    case Newsletter = 'newsletter';
}
