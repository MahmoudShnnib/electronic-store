<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Pos;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Admin\PosQueryService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Inertia\Inertia;
use Inertia\Response;

/** The 80mm thermal slip for a completed sale: viewed by default, printed on request. */
final class ReceiptController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:pos.use'];
    }

    public function __construct(private readonly PosQueryService $query) {}

    public function __invoke(Request $request, Order $order): Response
    {
        return Inertia::render('admin/pos/Receipt', [
            ...$this->query->receiptProps($order),
            // Opening a receipt is looking at it. Printing is a separate act and
            // has to be asked for — landing here used to fire the printer, which
            // meant a cashier checking what they sold handed out a second slip.
            'autoPrint' => $request->boolean('print'),
        ]);
    }
}
