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

    // Flat delivery fee in cents, applied unless a coupon grants free shipping
    // or the subtotal passes the free-shipping threshold.
    'shipping_fee_cents' => env('STORE_SHIPPING_FEE_CENTS', 1500),
    'free_shipping_threshold_cents' => env('STORE_FREE_SHIPPING_THRESHOLD_CENTS', 50000),

    // Fallback VAT-style rate (percent) when a product has no explicit tax rate.
    'default_tax_rate' => env('STORE_DEFAULT_TAX_RATE', 0),

    // Days after delivery during which a customer may request a return.
    'return_window_days' => env('STORE_RETURN_WINDOW_DAYS', 14),

    // Maximum quantity of a single item per order.
    'max_quantity_per_item' => env('STORE_MAX_QTY_PER_ITEM', 10),

    'locales' => ['en', 'ar'],
];
