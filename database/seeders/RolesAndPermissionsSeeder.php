<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Full permission catalog. Policies check permissions, never role names,
     * so custom roles can be composed in the admin UI from these.
     *
     * @var array<string, list<string>>
     */
    private const PERMISSIONS = [
        'catalog' => [
            'products.view', 'products.create', 'products.update', 'products.delete',
            'categories.manage', 'brands.manage', 'specifications.manage',
        ],
        'inventory' => [
            'inventory.view', 'inventory.adjust', 'inventory.transfer', 'inventory.count',
            'warehouses.manage',
        ],
        'purchasing' => [
            'suppliers.manage', 'purchase_orders.view', 'purchase_orders.manage', 'purchase_orders.receive',
        ],
        'orders' => [
            'orders.view', 'orders.manage',
        ],
        'refunds' => [
            'refunds.manage',
        ],
        'treasury' => [
            'treasury.view', 'treasury.manage',
        ],
        'customers' => [
            'customers.view', 'customers.manage',
        ],
        'engagement' => [
            'reviews.moderate', 'coupons.manage',
        ],
        'platform' => [
            'reports.view', 'users.manage', 'roles.manage', 'audit.view', 'settings.manage',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $all = collect(self::PERMISSIONS)->flatten();

        $all->each(fn (string $name) => Permission::findOrCreate($name));

        // Super Admin gets everything via Gate::before — no permissions attached.
        Role::findOrCreate('super_admin');

        Role::findOrCreate('admin')->syncPermissions(
            $all->reject(fn (string $p) => in_array($p, ['users.manage', 'roles.manage', 'settings.manage'], true)),
        );

        Role::findOrCreate('inventory_manager')->syncPermissions([
            'products.view', 'products.create', 'products.update', 'products.delete',
            'categories.manage', 'brands.manage', 'specifications.manage',
            'inventory.view', 'inventory.adjust', 'inventory.transfer', 'inventory.count',
            'warehouses.manage',
            'suppliers.manage', 'purchase_orders.view', 'purchase_orders.manage', 'purchase_orders.receive',
            'reports.view',
        ]);

        Role::findOrCreate('order_manager')->syncPermissions([
            'orders.view', 'orders.manage',
            'refunds.manage',
            'treasury.view',
            'customers.view',
            'reports.view',
        ]);

        Role::findOrCreate('customer');
    }
}
