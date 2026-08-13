<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Coupon codes only need to be unique among live coupons — a deleted or
     * expired campaign should not reserve its code forever. Postgres partial
     * unique index: uniqueness applies to non-deleted rows only.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE coupons DROP CONSTRAINT IF EXISTS coupons_code_unique');
        DB::statement('DROP INDEX IF EXISTS coupons_code_unique');
        DB::statement('CREATE UNIQUE INDEX coupons_code_unique_live ON coupons (code) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS coupons_code_unique_live');
        DB::statement('ALTER TABLE coupons ADD CONSTRAINT coupons_code_unique UNIQUE (code)');
    }
};
