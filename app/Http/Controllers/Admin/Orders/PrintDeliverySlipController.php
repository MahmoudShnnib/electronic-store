<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Orders;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Orders\PrintDeliverySlipRequest;
use App\Models\Order;
use App\Services\Pos\Printers\PrinterUnavailable;
use App\Services\Pos\ReceiptPrinter;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use RuntimeException;

/**
 * Sends one rasterised delivery slip to the counter printer — the same head the
 * register prints receipts on, so a parcel label needs no second device.
 *
 * Answers JSON rather than a redirect: the order page posts this in the
 * background and falls back to the browser dialog on anything but a 200.
 */
final class PrintDeliverySlipController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:orders.view'];
    }

    public function __invoke(PrintDeliverySlipRequest $request, Order $order, ReceiptPrinter $printer): JsonResponse
    {
        if (! ReceiptPrinter::isConfigured()) {
            return response()->json([
                'printed' => false,
                'reason' => 'not_configured',
            ], 409);
        }

        try {
            // Never the drawer: a parcel label is packing paperwork, and no cash
            // changes hands at the desk that prints it.
            $printer->print(image: (string) $request->file('image')->get());
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
