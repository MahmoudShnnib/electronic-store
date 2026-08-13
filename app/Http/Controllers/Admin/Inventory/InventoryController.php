<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Inventory;

use App\Http\Controllers\Controller;
use App\Services\Admin\InventoryQueryService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only inventory screens: stock levels and the movement ledger.
 * Adjustments and transfers each have their own single-action controller here.
 */
final class InventoryController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:inventory.view'];
    }

    public function __construct(private readonly InventoryQueryService $query) {}

    public function index(Request $request): Response
    {
        return Inertia::render('admin/inventory/Index', $this->query->indexProps($request));
    }

    public function movements(Request $request): Response
    {
        return Inertia::render('admin/inventory/Movements', $this->query->movementsProps($request));
    }
}
