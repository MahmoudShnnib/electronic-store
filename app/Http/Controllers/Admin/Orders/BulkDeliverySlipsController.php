<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Orders;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Orders\BulkOrdersRequest;
use App\Services\Admin\OrderQueryService;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

/** Delivery slips for several orders on one page — a driver's run, printed at once. */
final class BulkDeliverySlipsController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:orders.view'];
    }

    public function __construct(private readonly OrderQueryService $query) {}

    public function __invoke(BulkOrdersRequest $request): Response
    {
        return Inertia::render('admin/orders/DeliverySlips', $this->query->deliverySlipsProps($request->ids()));
    }
}
