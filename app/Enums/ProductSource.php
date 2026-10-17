<?php

declare(strict_types=1);

namespace App\Enums;

/** Where a product carousel section takes its products from. */
enum ProductSource: string
{
    case Featured = 'featured';
    case NewArrivals = 'new_arrivals';
    case OnSale = 'on_sale';
    case BestSellers = 'best_sellers';
    case Category = 'category';
    case Manual = 'manual';
}
