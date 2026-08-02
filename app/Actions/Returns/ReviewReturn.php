<?php

declare(strict_types=1);

namespace App\Actions\Returns;

use App\Enums\ReturnStatus;
use App\Models\ProductReturn;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin transition of a return through its review states
 * (under_review, approved, rejected, closed).
 */
final class ReviewReturn
{
    public function handle(ProductReturn $return, ReturnStatus $to, ?string $adminNote, int $reviewerId): ProductReturn
    {
        return DB::transaction(function () use ($return, $to, $adminNote, $reviewerId): ProductReturn {
            /** @var ProductReturn $return */
            $return = ProductReturn::query()->lockForUpdate()->findOrFail($return->id);

            if (! $return->status->canTransitionTo($to)) {
                throw ValidationException::withMessages([
                    'status' => __('Cannot move return :rma from :from to :to.', ['rma' => $return->rma_number, 'from' => $return->status->label(), 'to' => $to->label()]),
                ]);
            }

            $return->update([
                'status' => $to,
                'admin_note' => $adminNote ?? $return->admin_note,
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
            ]);

            activity()
                ->performedOn($return)
                ->withProperties(['to' => $to->value, 'note' => $adminNote])
                ->event('return_reviewed')
                ->log("Return {$return->rma_number} moved to {$to->label()}");

            return $return;
        });
    }
}
