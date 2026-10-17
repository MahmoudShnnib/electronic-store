<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Pos;

use App\Http\Controllers\Controller;
use App\Services\Admin\PosQueryService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

/** Counter sales: every receipt rung up at a till, apart from the orders that need fulfilling. */
final class PosSalesController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:pos.shifts.view'];
    }

    public function __invoke(Request $request, PosQueryService $query): Response
    {
        return Inertia::render('admin/pos/Sales', $query->salesIndexProps($request));
    }
}
