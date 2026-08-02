<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('serves the admin dashboard in Arabic for a staff user with the ar locale', function (): void {
    $admin = User::factory()->create(['locale' => 'ar']);
    $admin->syncRoles(['super_admin']);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('locale', 'ar'));
});

it('translates enum labels sent to the admin UI', function (): void {
    $admin = User::factory()->create(['locale' => 'ar']);
    $admin->syncRoles(['super_admin']);

    $this->actingAs($admin)
        ->get(route('admin.orders.index'))
        ->assertOk()
        ->assertInertia(function ($page) {
            $labels = collect($page->toArray()['props']['statuses'])->pluck('label');

            expect($labels)->toContain('قيد الانتظار')
                ->and($labels)->not->toContain('Pending');
        });
});

it('keeps English for a staff user with the en locale', function (): void {
    $admin = User::factory()->create(['locale' => 'en']);
    $admin->syncRoles(['super_admin']);

    $this->actingAs($admin)
        ->get(route('admin.orders.index'))
        ->assertOk()
        ->assertInertia(function ($page) {
            $labels = collect($page->toArray()['props']['statuses'])->pluck('label');

            expect($labels)->toContain('Pending');
        });
});

it('switches locale and persists it on the user', function (): void {
    $admin = User::factory()->create(['locale' => 'en']);
    $admin->syncRoles(['super_admin']);

    $this->actingAs($admin)->post('/locale', ['locale' => 'ar'])->assertRedirect();

    expect($admin->fresh()->locale)->toBe('ar');
});
