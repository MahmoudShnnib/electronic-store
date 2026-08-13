<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Returns;

use App\Actions\Returns\ReviewReturn;
use App\Enums\ReturnStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Returns\ReviewReturnRequest;
use App\Models\ProductReturn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;

/** Approves or rejects a requested return, moving it through its workflow. */
final class ReviewReturnController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ['can:returns.manage'];
    }

    public function __invoke(ReviewReturnRequest $request, ProductReturn $productReturn, ReviewReturn $reviewReturn): RedirectResponse
    {
        $validated = $request->validated();

        $reviewReturn->handle(
            $productReturn,
            ReturnStatus::from($validated['status']),
            $validated['admin_note'] ?? null,
            $request->user()->id,
        );

        return back()->with('success', 'Return updated.');
    }
}
