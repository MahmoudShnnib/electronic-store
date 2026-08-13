<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Returns;

use App\Http\Controllers\Controller;
use App\Models\ProductReturn;
use App\Services\Admin\ReturnQueryService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only RMA screens: list and detail. The review and receive workflow
 * steps each have their own single-action controller here.
 */
final class ReturnController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:returns.manage'];
    }

    public function __construct(private readonly ReturnQueryService $query) {}

    public function index(Request $request): Response
    {
        return Inertia::render('admin/returns/Index', $this->query->indexProps($request));
    }

    public function show(ProductReturn $productReturn): Response
    {
        return Inertia::render('admin/returns/Show', $this->query->showProps($productReturn));
    }
}
