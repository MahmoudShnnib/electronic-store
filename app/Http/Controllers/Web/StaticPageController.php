<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Inertia\Inertia;
use Inertia\Response;

final class StaticPageController extends Controller
{
    private const PAGES = [
        'about' => 'About us',
        'contact' => 'Contact us',
        'privacy-policy' => 'Privacy policy',
        'terms' => 'Terms and conditions',
        'return-policy' => 'Return policy',
    ];

    public function show(string $page): Response
    {
        abort_unless(array_key_exists($page, self::PAGES), 404);

        return Inertia::render('storefront/StaticPage', [
            'page' => $page,
            'title' => self::PAGES[$page],
            // Editable via admin Settings; sensible defaults ship in the seeder.
            'content' => Setting::get("page.{$page}"),
            'contactInfo' => $page === 'contact' ? Setting::get('contact_info') : null,
        ]);
    }
}
