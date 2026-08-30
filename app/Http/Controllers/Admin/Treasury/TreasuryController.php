<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Treasury;

use App\Http\Controllers\Controller;
use App\Services\Admin\TreasuryQueryService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

/** The store treasury: cash and bank balances with the ledger behind them. */
final class TreasuryController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:treasury.view'];
    }

    public function index(Request $request, TreasuryQueryService $treasury): Response
    {
        return Inertia::render('admin/treasury/Index', $treasury->indexProps($request));
    }
}
