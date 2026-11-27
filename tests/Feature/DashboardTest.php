<?php

namespace Tests\Feature;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\RefreshesInMemoryDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshesInMemoryDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_users_with_permission_can_visit_the_dashboard(): void
    {
        $this->withoutVite();

        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('dashboard.view'));

        $this->actingAs($user)->get(route('admin.dashboard'))->assertOk();
    }

    public function test_users_without_permission_are_forbidden(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }
}
