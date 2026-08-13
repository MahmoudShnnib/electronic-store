<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Orders;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Admin\OrderQueryService;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

/** Renders the printable invoice for an order. */
final class OrderInvoiceController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:orders.view'];
    }

    public function __construct(private readonly OrderQueryService $query) {}

    public function __invoke(Order $order): Response
    {
        return Inertia::render('admin/orders/Invoice', $this->query->invoiceProps($order));
    }
}
