<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Once per customer" coupons were only enforced for signed-in accounts, so a
 * guest could reuse one on every order. Redemptions now carry the customer's
 * normalised phone and email so guest orders count too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupon_redemptions', function (Blueprint $table): void {
            $table->string('customer_phone', 9)->nullable()->after('user_id')->index();
            $table->string('customer_email')->nullable()->after('customer_phone')->index();
        });

        // Backfill from the orders the redemptions belong to, with the same
        // rules as App\Support\CustomerIdentity (last nine digits; lowercase).
        DB::statement(<<<'SQL'
            UPDATE coupon_redemptions AS r
            SET customer_email = NULLIF(LOWER(TRIM(o.customer->>'email')), ''),
                customer_phone = CASE
                    WHEN LENGTH(REGEXP_REPLACE(COALESCE(o.customer->>'phone', o.shipping_address->>'phone', ''), '\D', '', 'g')) >= 9
                    THEN RIGHT(REGEXP_REPLACE(COALESCE(o.customer->>'phone', o.shipping_address->>'phone', ''), '\D', '', 'g'), 9)
                END
            FROM orders AS o
            WHERE o.id = r.order_id
            SQL);
    }

    public function down(): void
    {
        Schema::table('coupon_redemptions', function (Blueprint $table): void {
            $table->dropColumn(['customer_phone', 'customer_email']);
        });
    }
};
