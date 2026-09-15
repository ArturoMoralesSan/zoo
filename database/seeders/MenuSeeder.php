<?php

namespace Database\Seeders;

use App\Models\Link;
use App\Models\Menu;
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

        Menu::updateOrCreate(
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
            'species_categories.view'
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
            'species_tags.view'
        );

        $this->link(
            $zoo,
            'Zonas',
            'Map',
            4,
            'admin.zoo-zones.index',
            'zoo-zones.view'
        );

        $this->link(
            $zoo,
            'Marcadores del mapa',
            'MapPin',
            5,
            'admin.map-markers.index',
            'map-markers.view'
        );

        $this->link(
            $zoo,
            'Caminos del mapa',
            'Route',
            6,
            'admin.map-paths.index',
            'map_paths.view'
        );

        $this->link(
            $zoo,
            'Mapa',
            'Map',
            7,
            'map.index',
            'map_paths.view'
        );

        /*
        |--------------------------------------------------------------------------
        | Taquilla
        |--------------------------------------------------------------------------
        */

        $ticketOffice = Menu::updateOrCreate(
            ['name' => 'Taquilla'],
            [
                'icon' => 'Ticket',
                'order' => 3,
                'route' => null,
                'is_submenu' => true,
            ]
        );

        $this->link(
            $ticketOffice,
            'Tipos de boleto',
            'TicketCheck',
            1,
            'admin.ticket-types.index',
            'ticket-types.view'
        );

        $this->link(
            $ticketOffice,
            'Métodos de pago',
            'CreditCard',
            2,
            'admin.payment-methods.index',
            'payment-methods.view'
        );

        $this->link(
            $ticketOffice,
            'Órdenes de boletos',
            'Receipt',
            3,
            'admin.ticket-orders.index',
            'ticket-orders.view'
        );

        $this->link(
            $ticketOffice,
            'Donaciones',
            'Heart',
            4,
            'admin.donations.index',
            'donations.view'
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
                'order' => 4,
                'route' => null,
                'is_submenu' => true,
            ]
        );

        $this->link(
            $gamification,
            'Niveles',
            'Trophy',
            1,
            'admin.levels.index',
            'levels.view'
        );

        $this->link(
            $gamification,
            'Reglas de puntos',
            'CirclePlus',
            2,
            'admin.point-rules.index',
            'point-rules.view'
        );

        $this->link(
            $gamification,
            'Historial de puntos',
            'History',
            3,
            'admin.point-movements.index',
            'point-movements.view'
        );

        $this->link(
            $gamification,
            'Recompensas',
            'Gift',
            4,
            'admin.rewards.index',
            'rewards.view'
        );

        $this->link(
            $gamification,
            'Canje de recompensas',
            'BadgeCheck',
            5,
            'admin.reward-redemptions.index',
            'reward-redemptions.view'
        );

        /*
        |--------------------------------------------------------------------------
        | Eventos
        |--------------------------------------------------------------------------
        */

        $events = Menu::updateOrCreate(
            ['name' => 'Eventos'],
            [
                'icon' => 'CalendarDays',
                'order' => 5,
                'route' => null,
                'is_submenu' => true,
            ]
        );

        $this->link(
            $events,
            'Eventos',
            'CalendarDays',
            1,
            'admin.events.index',
            'events.view'
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
                'order' => 6,
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

    /*
    |--------------------------------------------------------------------------
    | Crear o actualizar un enlace de menú
    |--------------------------------------------------------------------------
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
        )
            ->where(
                'guard_name',
                'web'
            )
            ->first();

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
