<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cash-on-delivery dispatch: the shop's own drivers, which driver an order
 * went out with (orders.shipped_at, unused since the status pipeline was
 * collapsed, now marks the hand-over), and the confirmation calls made
 * before it goes out (orders.confirmed_at marks the confirmed one).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('phone', 32)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('driver_id')->nullable()->after('cashier_shift_id')->constrained()->nullOnDelete();
            $table->index(['driver_id', 'status']);
        });

        Schema::create('order_call_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('outcome', 20);
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_call_attempts');
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('driver_id');
        });
        Schema::dropIfExists('drivers');
    }
};
