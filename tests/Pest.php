<?php

use App\Actions\Inventory\AdjustStock;
use App\Enums\MovementType;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function (): void {
        $this->seed(RolesAndPermissionsSeeder::class);
    })
    ->in('Feature');

// Race tests fork real processes, which only see committed rows: no wrapping transaction.
pest()->extend(TestCase::class)
    ->use(DatabaseTruncation::class)
    ->beforeEach(function (): void {
        $this->seed(RolesAndPermissionsSeeder::class);
    })
    ->in('Concurrency');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function customer(array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $user->assignRole('customer');

    return $user;
}

function staff(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function defaultWarehouse(): Warehouse
{
    return Warehouse::query()->firstOrCreate(
        ['code' => 'MAIN'],
        ['name' => 'Main Warehouse', 'is_default' => true, 'is_active' => true],
    );
}

/** Put stock on the shelf through the ledger (never silently). */
function giveStock(Product $product, int $quantity, ?ProductVariant $variant = null, ?Warehouse $warehouse = null): void
{
    app(AdjustStock::class)->handle(
        $warehouse ?? defaultWarehouse(),
        $product,
        $variant,
        $quantity,
        MovementType::Initial,
        'Test stock',
    );
}
