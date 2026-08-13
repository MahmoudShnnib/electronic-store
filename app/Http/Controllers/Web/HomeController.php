<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Storefront\HomePageService;
use Inertia\Inertia;
use Inertia\Response;

/** Renders the storefront home page. */
final class HomeController extends Controller
{
    public function __invoke(HomePageService $page): Response
    {
        return Inertia::render('storefront/Home', $page->props());
    }
}
