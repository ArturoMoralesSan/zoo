<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Link;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Dashboard
        |--------------------------------------------------------------------------
        */

        Menu::create([
            'name' => 'Dashboard',
            'icon' => 'layout-dashboard',
            'order' => 1,
            'route' => 'admin.dashboard',
            'is_submenu' => false,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Zoo
        |--------------------------------------------------------------------------
        */

        $zoo = Menu::create([
            'name' => 'Zoo',
            'icon' => 'paw-print',
            'order' => 2,
            'route' => null,
            'is_submenu' => true,
        ]);

        $this->createLink(
            $zoo,
            'Species',
            'paw-print',
            1,
            'admin.species.index',
            'species.view'
        );

        $this->createLink(
            $zoo,
            'Cards',
            'layers',
            2,
            'admin.cards.index',
            'cards.view'
        );

        $this->createLink(
            $zoo,
            'Tickets',
            'ticket',
            3,
            'admin.tickets.index',
            'tickets.view'
        );

        $this->createLink(
            $zoo,
            'Donations',
            'heart-handshake',
            4,
            'admin.donations.index',
            'donations.view'
        );

        $this->createLink(
            $zoo,
            'Diplomas',
            'award',
            5,
            'admin.diplomas.index',
            'diplomas.view'
        );

        /*
        |--------------------------------------------------------------------------
        | Users / ACL
        |--------------------------------------------------------------------------
        */

        $users = Menu::create([
            'name' => 'Users',
            'icon' => 'users',
            'order' => 3,
            'route' => null,
            'is_submenu' => true,
        ]);

        $this->createLink(
            $users,
            'Users',
            'user',
            1,
            'admin.users.index',
            'users.view'
        );

        $this->createLink(
            $users,
            'Roles',
            'shield',
            2,
            'admin.roles.index',
            'roles.view'
        );

        $this->createLink(
            $users,
            'Permissions',
            'key-round',
            3,
            'admin.permissions.index',
            'permissions.view'
        );

        /*
        |--------------------------------------------------------------------------
        | Settings
        |--------------------------------------------------------------------------
        */

        $settings = Menu::create([
            'name' => 'Settings',
            'icon' => 'settings',
            'order' => 4,
            'route' => null,
            'is_submenu' => true,
        ]);

        $this->createLink(
            $settings,
            'Settings',
            'settings',
            1,
            'admin.settings.index',
            'settings.view'
        );
    }

    /**
     * Create menu link with permission.
     */
    private function createLink(
        Menu $menu,
        string $name,
        ?string $icon,
        int $order,
        string $route,
        string $permissionName
    ): void {
        $permission = Permission::firstOrCreate([
            'name' => $permissionName,
            'guard_name' => 'web',
        ]);

        Link::create([
            'menu_id' => $menu->id,
            'name' => $name,
            'icon' => $icon,
            'order' => $order,
            'route' => $route,
            'permission_id' => $permission->id,
        ]);
    }
}