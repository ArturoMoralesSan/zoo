<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Link;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Dashboard
        |--------------------------------------------------------------------------
        */

        $dashboard = Menu::create([
            'name' => 'Dashboard',
            'icon' => 'layout-dashboard',
            'order' => 1,
            'route' => 'dashboard',
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
            'species.index',
            'view.species'
        );

        $this->createLink(
            $zoo,
            'Cards',
            'layers',
            2,
            'cards.index',
            'view.cards'
        );

        $this->createLink(
            $zoo,
            'Tickets',
            'ticket',
            3,
            'tickets.index',
            'view.tickets'
        );

        $this->createLink(
            $zoo,
            'Donations',
            'heart-handshake',
            4,
            'donations.index',
            'view.donations'
        );

        $this->createLink(
            $zoo,
            'Diplomas',
            'award',
            5,
            'diplomas.index',
            'view.diplomas'
        );


        /*
        |--------------------------------------------------------------------------
        | Users
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
            'users.index',
            'view.users'
        );

        $this->createLink(
            $users,
            'Roles',
            'shield',
            2,
            'roles.index',
            'view.roles'
        );

        $this->createLink(
            $users,
            'Permissions',
            'key-round',
            3,
            'permissions.index',
            'view.permissions'
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
            'settings.index',
            'view.settings'
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
