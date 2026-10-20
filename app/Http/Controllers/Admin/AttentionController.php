<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminAttention;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The sidebar's count badges. The admin layout stays mounted across Inertia
 * visits, so it reads these once and then at most once a minute — cheaper than
 * a shared prop recomputed on every request.
 */
final class AttentionController extends Controller
{
    public function __invoke(Request $request, AdminAttention $attention): JsonResponse
    {
        return response()->json(['items' => $attention->for($request->user())]);
    }
}
