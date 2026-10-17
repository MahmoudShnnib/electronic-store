<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Pos;

use App\Actions\Pos\FindCustomerByPhone;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;

/** "Is this a customer we know?" — answered as the cashier types a phone number. */
final class PosCustomerLookupController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:pos.use'];
    }

    public function __invoke(Request $request, FindCustomerByPhone $find): JsonResponse
    {
        $customer = $find->handle((string) $request->query('phone'));

        return response()->json(['customer' => $customer?->only(['id', 'name'])]);
    }
}
