<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Pos;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Pos\PrintReceiptRequest;
use App\Models\Order;
use App\Services\Pos\Printers\PrinterUnavailable;
use App\Services\Pos\ReceiptPrinter;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use RuntimeException;

/**
 * Sends one rasterised slip to the counter printer.
 *
 * Answers JSON rather than a redirect because the register calls it in the
 * background: whatever comes back, the sale is already rung up and the cashier
 * is not to be interrupted. A failure here only tells the page to fall back to
 * the browser's print dialog.
 */
final class PrintReceiptController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:pos.use'];
    }

    public function __invoke(PrintReceiptRequest $request, Order $order, ReceiptPrinter $printer): JsonResponse
    {
        if (! ReceiptPrinter::isConfigured()) {
            return response()->json([
                'printed' => false,
                'reason' => 'not_configured',
            ], 409);
        }

        try {
            $printer->print(
                image: (string) $request->file('image')->get(),
                // Read from the order, never from the request: the drawer is
                // cash handling, and nothing a browser posts should be able to
                // pop it on a card or transfer sale.
                openDrawer: $order->payment_method === PaymentMethod::Cash,
            );
        } catch (PrinterUnavailable $e) {
            report($e);

            return response()->json([
                'printed' => false,
                'reason' => 'unavailable',
                'message' => $e->getMessage(),
            ], 502);
        } catch (RuntimeException $e) {
            report($e);

            return response()->json([
                'printed' => false,
                'reason' => 'unreadable',
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json(['printed' => true]);
    }
}
