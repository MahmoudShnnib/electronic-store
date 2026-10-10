<?php

declare(strict_types=1);

/*
 * Migration 2026_10_11_110000 on rows written before movements carried a cost:
 * old write-offs get the cost stock was valued at then, and the old "correction"
 * rows of a product gaining variants stop counting as written off.
 */

use App\Models\InventoryMovement;
use App\Services\Reports\ReportService;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/../FinanceAudit/FinanceAuditSetup.php';

beforeEach(fn () => finSetUp($this));

it('costs old write-offs and reclassifies old variant moves', function (): void {
    finAdjust($this, $this->phone, 2, 'damaged', 'Dropped')->assertSessionHasNoErrors();
    finAdjust($this, $this->cable, -3, 'correction', 'Count came up short')->assertSessionHasNoErrors();
    // As written before this change: no cost, and the variant move typed as a correction.
    DB::table('inventory_movements')->update(['cost_cents' => null]);
    $move = InventoryMovement::query()->where('type', 'correction')->sole();
    $move->replicate()->fill(['reason' => 'Moved to variants when variants were added', 'quantity_change' => -1])->saveQuietly();

    (require database_path('migrations/2026_10_11_110000_classify_variant_moves_and_cost_old_write_offs.php'))->up();

    $writeOffs = app(ReportService::class)->writeOffs(now()->subDay()->toImmutable(), now()->addDay()->toImmutable());
    expect(DB::table('inventory_movements')->where('type', 'variant_split')->count())->toBe(1)
        ->and(collect($writeOffs['rows'])->pluck('cost_formatted', 'type')->all())->toBe([
            'correction' => finFmt(3 * 2_000),
            'damaged' => finFmt(2 * 70_000),
        ])
        ->and($writeOffs['total_formatted'])->toBe(finFmt(3 * 2_000 + 2 * 70_000));
});
