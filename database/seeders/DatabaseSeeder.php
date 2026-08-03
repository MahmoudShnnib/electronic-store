<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        // Default warehouse so checkout works out of the box.
        Warehouse::query()->firstOrCreate(
            ['code' => 'MAIN'],
            ['name' => 'Main Warehouse', 'is_default' => true, 'is_active' => true],
        );

        // Super admin account (change the password immediately in production).
        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@store.test'],
            [
                'name' => 'Super Admin',
                'password' => 'password',
                'is_active' => true,
            ],
        );
        $admin->markEmailAsVerified();
        $admin->syncRoles(['super_admin']);

        foreach ([
            'store_name' => 'Electronic Store',
            'page.about' => 'We sell quality electronics at fair prices.',
            'page.privacy-policy' => 'Your privacy matters to us.',
            'page.terms' => 'Standard terms and conditions apply.',
            'page.return-policy' => 'Items can be returned within 14 days of delivery.',
            'contact_info' => ['email' => 'support@store.test', 'phone' => '+218-91-0000000'],
            'bank_transfer_details' => 'Bank: Example Bank — IBAN: LY00 0000 0000 0000 — include your order number.',
            'invoice_details' => 'Electronic Store — Tripoli, Libya',
        ] as $key => $value) {
            Setting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
        }

        if (app()->environment('local')) {
            $this->call(DemoCatalogSeeder::class);
            $this->call(ProductImageSeeder::class);
            $this->call(GameSirSeeder::class);
        }
    }
}
