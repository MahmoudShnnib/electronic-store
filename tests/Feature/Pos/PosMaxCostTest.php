<?php

/*
 * Owner rule (2026-10-10): at the register, admins see next to each item the highest
 * price paid for the units still in that register's warehouse; cashiers never get it.
 */

use App\Actions\Inventory\AdjustStock;
use App\Actions\Pos\OpenShift;
use App\Enums\MovementType;
use App\Models\Product;
use App\Models\Warehouse;
use App\Support\Money;
use Inertia\Testing\AssertableInertia;

it('shows admins the highest cost of the units in the register warehouse, and cashiers nothing', function (): void {
    $product = Product::factory()->create(['cost_price_cents' => 40_000]);
    giveStock($product, 3); // at the cost price, 400
    app(AdjustStock::class)->handle(defaultWarehouse(), $product, null, 2, MovementType::ManualIncrease, 'Dearer delivery', unitCostCents: 55_000);
    // Dearer still, but in another warehouse: not what this register sells from.
    giveStock($product, 1, warehouse: Warehouse::factory()->create(['is_active' => true]));
    app(AdjustStock::class)->handle(Warehouse::query()->latest('id')->first(), $product, null, 1, MovementType::ManualIncrease, 'Elsewhere', unitCostCents: 90_000);

    $register = function (string $role) {
        $user = staff($role);
        app(OpenShift::class)->handle($user, defaultWarehouse(), 0);

        return $this->actingAs($user)->get(route('admin.pos.register'));
    };

    foreach (['admin', 'super_admin'] as $role) {
        $register($role)->assertInertia(fn (AssertableInertia $page) => $page
            ->where('products.0.max_cost_formatted', Money::format(55_000)));
    }
    foreach (['cashier', 'order_manager'] as $role) {
        $register($role)->assertInertia(fn (AssertableInertia $page) => $page
            ->where('products.0.available', 5)
            ->missing('products.0.max_cost_formatted'));
    }
});
