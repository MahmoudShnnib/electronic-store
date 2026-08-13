<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Orders;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Admin\OrderQueryService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only order screens: list and detail. Status changes, notes, payments
 * and the invoice each have their own single-action controller here.
 */
final class OrderController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:orders.view'];
    }

    public function __construct(private readonly OrderQueryService $query) {}

    public function index(Request $request): Response
    {
        return Inertia::render('admin/orders/Index', $this->query->indexProps($request));
    }

    public function show(Order $order): Response
    {
        return Inertia::render('admin/orders/Show', $this->query->showProps($order));
    }
}
