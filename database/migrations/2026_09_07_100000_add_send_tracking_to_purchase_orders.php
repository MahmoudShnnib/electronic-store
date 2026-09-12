<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Sent to supplier" used to be a status and nothing more — no mail left the
 * building, and the record could not say when it was sent or to which address.
 * These two columns make the claim auditable: sent_at is when the mail was
 * queued, sent_to is the address it was queued to, captured at send time so a
 * later edit to the supplier's contact details cannot rewrite history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->timestamp('sent_at')->nullable()->after('status');
            $table->string('sent_to')->nullable()->after('sent_at');
        });

        // POs already marked sent really were handed to the supplier somehow;
        // stamping their last update keeps "sent with no date" from looking
        // like a bug in the new column.
        DB::table('purchase_orders')
            ->whereIn('status', ['sent', 'partially_received', 'received'])
            ->whereNull('sent_at')
            ->update(['sent_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->dropColumn(['sent_at', 'sent_to']);
        });
    }
};
