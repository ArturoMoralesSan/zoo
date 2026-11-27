<?php

namespace Tests\Feature;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\RefreshesInMemoryDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshesInMemoryDatabase;

    public function test_home_sends_guests_to_the_login(): void
    {
        $this->get(route('home'))->assertRedirect(route('login'));
    }

    public function test_home_sends_users_to_the_panel(): void
    {
        Permission::findOrCreate('dashboard.view');

        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertRedirect(route('admin.dashboard'));
    }
}
