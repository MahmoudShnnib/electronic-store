<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_customers_are_sent_to_their_orders()
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('customer');

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('account.orders.index'));
    }

    public function test_staff_are_sent_to_the_admin_dashboard()
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('admin.dashboard'));
    }
}
