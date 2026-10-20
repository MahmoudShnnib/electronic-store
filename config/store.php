<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storefront defaults
    |--------------------------------------------------------------------------
    | Runtime-editable values live in the `settings` table (see App\Models\Setting);
    | these are the fallbacks used when no setting row exists.
    */

    'currency' => env('STORE_CURRENCY', 'LYD'),

    // Shown after the amount: `1,234.000 LYD`. Keyed by locale, falling back to
    // the ISO code. ICU is not used for placement — it puts the code first in
    // English, which is not how prices are read here.
    'currency_symbols' => [
        'ar' => 'د.ل',
    ],

    // Local time for "which day" questions (report ranges). The app itself runs in UTC.
    'timezone' => env('STORE_TIMEZONE', 'Africa/Tripoli'),

    // The store ships to one country; it is never a checkout input.
    'country' => env('STORE_COUNTRY', 'LY'),

    // The most a cashier may take off a register sale by hand, as a percentage
    // of its subtotal. Admins and super admins are not capped.
    'pos_manual_discount_max_percent' => (int) env('STORE_POS_MANUAL_DISCOUNT_MAX_PERCENT', 15),

    // Payment methods offered at checkout. Bank transfer stays implemented but
    // hidden until it is added here.
    'payment_methods' => ['cod'],

    // Delivery cities offered at checkout. The English name is the canonical
    // value stored on orders; the Arabic name is only for display.
    'cities' => [
        ['en' => 'Tripoli', 'ar' => 'طرابلس'],
        ['en' => 'Benghazi', 'ar' => 'بنغازي'],
        ['en' => 'Misrata', 'ar' => 'مصراتة'],
        ['en' => 'Zawiya', 'ar' => 'الزاوية'],
        ['en' => 'Zliten', 'ar' => 'زليتن'],
        ['en' => 'Khoms', 'ar' => 'الخمس'],
        ['en' => 'Bayda', 'ar' => 'البيضاء'],
        ['en' => 'Ajdabiya', 'ar' => 'اجدابيا'],
        ['en' => 'Tobruk', 'ar' => 'طبرق'],
        ['en' => 'Sabha', 'ar' => 'سبها'],
        ['en' => 'Derna', 'ar' => 'درنة'],
        ['en' => 'Sirte', 'ar' => 'سرت'],
        ['en' => 'Gharyan', 'ar' => 'غريان'],
        ['en' => 'Sabratha', 'ar' => 'صبراتة'],
        ['en' => 'Zuwara', 'ar' => 'زوارة'],
        ['en' => 'Bani Walid', 'ar' => 'بني وليد'],
        ['en' => 'Tarhuna', 'ar' => 'ترهونة'],
        ['en' => 'Marj', 'ar' => 'المرج'],
        ['en' => 'Yefren', 'ar' => 'يفرن'],
        ['en' => 'Nalut', 'ar' => 'نالوت'],
        ['en' => 'Ubari', 'ar' => 'أوباري'],
        ['en' => 'Murzuq', 'ar' => 'مرزق'],
        ['en' => 'Kufra', 'ar' => 'الكفرة'],
        ['en' => 'Ghat', 'ar' => 'غات'],
        ['en' => 'Hun', 'ar' => 'هون'],
    ],

    // Maximum quantity of a single item per order.
    'max_quantity_per_item' => env('STORE_MAX_QTY_PER_ITEM', 10),

    'locales' => ['en', 'ar'],

    // Website/app cash-on-delivery orders nobody confirmed by phone within this
    // many hours are cancelled and their stock released (0 turns it off).
    'unconfirmed_cancel_hours' => (int) env('STORE_UNCONFIRMED_CANCEL_HOURS', 72),

    // The storefront's language when a client doesn't say (API requests with no
    // ?locale or Accept-Language). The shop is Arabic-first.
    'default_locale' => env('STORE_DEFAULT_LOCALE', 'ar'),

    // The storefront SPA's own host (e.g. shop.example.ly). When set, Laravel
    // serves the built SPA there with per-URL meta tags, a sitemap and robots
    // (Web\StorefrontShellController); unset, nothing changes.
    'storefront_host' => env('STOREFRONT_HOST'),

    // The SPA build to serve on that host (the folder holding index.html).
    'storefront_dist' => env('STOREFRONT_DIST', base_path('../electronic-store-web/dist')),

    // Description for pages that have none of their own (home, listings).
    'seo_description' => env('STORE_SEO_DESCRIPTION', 'أحدث الأجهزة الإلكترونية والإكسسوارات، مع التوصيل لكل ليبيا والدفع عند الاستلام.'),

    // Where the storefront SPA lives, for links in emails (password reset).
    // Defaults to the first origin CORS allows (STOREFRONT_URL).
    'storefront_url' => env('STOREFRONT_PUBLIC_URL', trim(explode(',', (string) env('STOREFRONT_URL', 'http://localhost:5173'))[0])),
];
