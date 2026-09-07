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
        | Permisos
        |--------------------------------------------------------------------------
        |
        | Los permisos se crean aquí para que los MenuLinks puedan asociarse
        | directamente con ellos.
        |
        */

        $permissions = [
            // Dashboard
            'dashboard.view',

            // Zoológico
            'species-categories.view',
            'species-categories.create',
            'species-categories.edit',
            'species-categories.delete',

            'species.view',
            'species.create',
            'species.edit',
            'species.delete',

            'species-tags.view',
            'species-tags.create',
            'species-tags.edit',
            'species-tags.delete',

            'species-images.view',
            'species-images.create',
            'species-images.edit',
            'species-images.delete',

            'species-models.view',
            'species-models.create',
            'species-models.edit',
            'species-models.delete',

            'species-locations.view',
            'species-locations.create',
            'species-locations.edit',
            'species-locations.delete',

            // Boletos
            'ticket-types.view',
            'ticket-types.create',
            'ticket-types.edit',
            'ticket-types.delete',

            'tickets.view',
            'tickets.create',
            'tickets.edit',
            'tickets.delete',

            // Usuarios / visitantes
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',

            'visitors.view',
            'visitors.create',
            'visitors.edit',
            'visitors.delete',

            // Gamificación
            'points.view',
            'points.create',
            'points.edit',
            'points.delete',

            'collections.view',
            'collections.create',
            'collections.edit',
            'collections.delete',

            'species-captures.view',
            'species-captures.create',
            'species-captures.edit',
            'species-captures.delete',

            // Mapa
            'map.view',
            'map.create',
            'map.edit',
            'map.delete',

            // Diplomas
            'diplomas.view',
            'diplomas.create',
            'diplomas.edit',
            'diplomas.delete',

            // Reportes
            'reports.view',
            'reports.tickets',
            'reports.users',
            'reports.points',
            'reports.captures',

            // Administración
            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.delete',

            'permissions.view',
            'permissions.create',
            'permissions.edit',
            'permissions.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Dashboard
        |--------------------------------------------------------------------------
        */

        $dashboard = Menu::updateOrCreate(
            ['name' => 'Dashboard'],
            [
                'icon' => 'LayoutDashboard',
                'order' => 1,
                'route' => 'admin.dashboard',
                'is_submenu' => false,
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Zoológico
        |--------------------------------------------------------------------------
        */

        $zoo = Menu::updateOrCreate(
            ['name' => 'Zoológico'],
            [
                'icon' => 'PawPrint',
                'order' => 2,
                'route' => null,
                'is_submenu' => true,
            ]
        );

        $this->link(
            $zoo,
            'Categorías',
            'Tags',
            1,
            'admin.species-categories.index',
            'species-categories.view'
        );

        $this->link(
            $zoo,
            'Especies',
            'PawPrint',
            2,
            'admin.species.index',
            'species.view'
        );

        $this->link(
            $zoo,
            'Etiquetas',
            'Tag',
            3,
            'admin.species-tags.index',
            'species-tags.view'
        );

        $this->link(
            $zoo,
            'Imágenes',
            'Image',
            4,
            'admin.speciesImages.index',
            'species-images.view'
        );

        $this->link(
            $zoo,
            'Modelos 3D',
            'Box',
            5,
            'admin.speciesModels.index',
            'species-models.view'
        );

        $this->link(
            $zoo,
            'Ubicaciones',
            'MapPin',
            6,
            'admin.speciesLocations.index',
            'species-locations.view'
        );

        /*
        |--------------------------------------------------------------------------
        | Boletos
        |--------------------------------------------------------------------------
        */

        $tickets = Menu::updateOrCreate(
            ['name' => 'Boletos'],
            [
                'icon' => 'Ticket',
                'order' => 3,
                'route' => null,
                'is_submenu' => true,
            ]
        );

        $this->link(
            $tickets,
            'Tipos de boleto',
            'TicketCheck',
            1,
            'admin.ticket-types.index',
            'ticket-types.view'
        );

        $this->link(
            $tickets,
            'Boletos',
            'Ticket',
            2,
            'admin.tickets.index',
            'tickets.view'
        );

        /*
        |--------------------------------------------------------------------------
        | Usuarios
        |--------------------------------------------------------------------------
        */

        $users = Menu::updateOrCreate(
            ['name' => 'Usuarios'],
            [
                'icon' => 'Users',
                'order' => 4,
                'route' => null,
                'is_submenu' => true,
            ]
        );

        $this->link(
            $users,
            'Usuarios',
            'User',
            1,
            'admin.users.index',
            'users.view'
        );

        $this->link(
            $users,
            'Visitantes',
            'Users',
            2,
            'admin.visitors.index',
            'visitors.view'
        );

        /*
        |--------------------------------------------------------------------------
        | Gamificación
        |--------------------------------------------------------------------------
        */

        $gamification = Menu::updateOrCreate(
            ['name' => 'Gamificación'],
            [
                'icon' => 'Gamepad2',
                'order' => 5,
                'route' => null,
                'is_submenu' => true,
            ]
        );

        $this->link(
            $gamification,
            'Puntos',
            'Star',
            1,
            'admin.points.index',
            'points.view'
        );

        $this->link(
            $gamification,
            'Colecciones',
            'Library',
            2,
            'admin.collections.index',
            'collections.view'
        );

        $this->link(
            $gamification,
            'Capturas AR',
            'ScanLine',
            3,
            'admin.speciesCaptures.index',
            'species-captures.view'
        );

        /*
        |--------------------------------------------------------------------------
        | Mapa
        |--------------------------------------------------------------------------
        */

        $map = Menu::updateOrCreate(
            ['name' => 'Mapa'],
            [
                'icon' => 'Map',
                'order' => 6,
                'route' => null,
                'is_submenu' => true,
            ]
        );

        $this->link(
            $map,
            'Mapa del zoológico',
            'Map',
            1,
            'admin.map.index',
            'map.view'
        );

        /*
        |--------------------------------------------------------------------------
        | Diplomas
        |--------------------------------------------------------------------------
        */

        $diplomas = Menu::updateOrCreate(
            ['name' => 'Diplomas'],
            [
                'icon' => 'Award',
                'order' => 7,
                'route' => null,
                'is_submenu' => true,
            ]
        );

        $this->link(
            $diplomas,
            'Diplomas',
            'Award',
            1,
            'admin.diplomas.index',
            'diplomas.view'
        );

        /*
        |--------------------------------------------------------------------------
        | Reportes
        |--------------------------------------------------------------------------
        */

        $reports = Menu::updateOrCreate(
            ['name' => 'Reportes'],
            [
                'icon' => 'ChartNoAxesCombined',
                'order' => 8,
                'route' => null,
                'is_submenu' => true,
            ]
        );

        $this->link(
            $reports,
            'Boletos',
            'Ticket',
            1,
            'admin.reports.tickets',
            'reports.tickets'
        );

        $this->link(
            $reports,
            'Usuarios',
            'Users',
            2,
            'admin.reports.users',
            'reports.users'
        );

        $this->link(
            $reports,
            'Puntos',
            'Star',
            3,
            'admin.reports.points',
            'reports.points'
        );

        $this->link(
            $reports,
            'Capturas AR',
            'ScanLine',
            4,
            'admin.reports.captures',
            'reports.captures'
        );

        /*
        |--------------------------------------------------------------------------
        | Administración
        |--------------------------------------------------------------------------
        */

        $administration = Menu::updateOrCreate(
            ['name' => 'Administración'],
            [
                'icon' => 'Settings',
                'order' => 9,
                'route' => null,
                'is_submenu' => true,
            ]
        );

        $this->link(
            $administration,
            'Usuarios',
            'Users',
            1,
            'admin.users.index',
            'users.view'
        );

        $this->link(
            $administration,
            'Roles',
            'Shield',
            2,
            'admin.roles.index',
            'roles.view'
        );

        $this->link(
            $administration,
            'Permisos',
            'KeyRound',
            3,
            'admin.permissions.index',
            'permissions.view'
        );
    }

    /**
     * Crear o actualizar un enlace de menú.
     */
    private function link(
        Menu $menu,
        string $name,
        string $icon,
        int $order,
        ?string $route,
        string $permission
    ): void {
        $permissionModel = Permission::where(
            'name',
            $permission
        )->first();

        Link::updateOrCreate(
            [
                'menu_id' => $menu->id,
                'name' => $name,
            ],
            [
                'icon' => $icon,
                'order' => $order,
                'route' => $route,
                'permission_id' => $permissionModel?->id,
            ]
        );
    }
}
