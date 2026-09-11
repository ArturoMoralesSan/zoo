<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Roles
        |--------------------------------------------------------------------------
        */

        $superadmin = Role::firstOrCreate([
            'name' => 'SuperAdmin',
            'guard_name' => 'web',
        ]);

        $admin = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        $staff = Role::firstOrCreate([
            'name' => 'staff',
            'guard_name' => 'web',
        ]);

        $visitor = Role::firstOrCreate([
            'name' => 'visitor',
            'guard_name' => 'web',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Permisos
        |--------------------------------------------------------------------------
        */

        $permissions = [

            // Dashboard
            'dashboard.view',

            // Usuarios
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',

            // Roles
            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.delete',

            // Permisos
            'permissions.view',
            'permissions.create',
            'permissions.edit',
            'permissions.delete',

            // Levels
            'levels.view',
            'levels.create',
            'levels.edit',
            'levels.delete',

            // Categorías de especies
            'species_categories.view',
            'species_categories.create',
            'species_categories.edit',
            'species_categories.delete',

            // Especies
            'species.view',
            'species.create',
            'species.edit',
            'species.delete',

            // Etiquetas de especies
            'species_tags.view',
            'species_tags.create',
            'species_tags.edit',
            'species_tags.delete',

            // Zonas del zoológico
            'zoo-zones.view',
            'zoo-zones.create',
            'zoo-zones.edit',
            'zoo-zones.delete',

            // Marcadores del mapa
            'map-markers.view',
            'map-markers.create',
            'map-markers.edit',
            'map-markers.delete',

            // Caminos del mapa
            'map_paths.view',
            'map_paths.create',
            'map_paths.edit',
            'map_paths.delete',

            // Tipos de boleto
            'ticket-types.view',
            'ticket-types.create',
            'ticket-types.edit',
            'ticket-types.delete',

            // Métodos de pago
            'payment-methods.view',
            'payment-methods.create',
            'payment-methods.edit',
            'payment-methods.delete',

            // Órdenes de boletos
            'ticket-orders.view',
            'ticket-orders.create',
            'ticket-orders.edit',
            'ticket-orders.delete',

            // Eventos
            'events.view',
            'events.create',
            'events.edit',
            'events.delete',

            // Reglas de puntos
            'point-rules.view',
            'point-rules.create',
            'point-rules.edit',
            'point-rules.delete',

            // Historial de puntos
            'point-movements.view',

            // Recompensas
            'rewards.view',
            'rewards.create',
            'rewards.edit',
            'rewards.delete',

            // Canje de recompensas
            'reward-redemptions.view',
            'reward-redemptions.create',

            // Donaciones
            'donations.view',
            'donations.create',
        ];

        /*
        |--------------------------------------------------------------------------
        | Crear permisos
        |--------------------------------------------------------------------------
        */

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | SuperAdmin
        |--------------------------------------------------------------------------
        |
        | SuperAdmin NO necesita permisos.
        |
        | Su acceso total se obtiene mediante Gate::before().
        |
        */

        $superadmin->syncPermissions([]);

        /*
        |--------------------------------------------------------------------------
        | Admin
        |--------------------------------------------------------------------------
        |
        | Admin tiene acceso completo a los módulos administrativos.
        |
        */

        $admin->syncPermissions($permissions);

        /*
        |--------------------------------------------------------------------------
        | Staff
        |--------------------------------------------------------------------------
        |
        | Staff puede operar las funciones principales del zoológico,
        | pero no administrar usuarios, roles ni permisos.
        |
        */

        $staff->syncPermissions([

            // Dashboard
            'dashboard.view',

            // Especies
            'species.view',

            // Categorías
            'species_categories.view',

            // Tags
            'species_tags.view',

            // Zonas
            'zoo-zones.view',

            // Mapa
            'map-markers.view',
            'map_paths.view',

            // Boletos
            'ticket-types.view',
            'ticket-orders.view',
            'ticket-orders.create',

            // Métodos de pago
            'payment-methods.view',

            // Eventos
            'events.view',

            // Gamificación
            'levels.view',
            'point-rules.view',
            'point-movements.view',

            // Recompensas
            'rewards.view',

            // Canje de recompensas
            'reward-redemptions.view',
            'reward-redemptions.create',

            // Donaciones
            'donations.view',
            'donations.create',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Visitor
        |--------------------------------------------------------------------------
        |
        | El visitante no tiene permisos administrativos.
        | Sus funciones pertenecen a la aplicación pública.
        |
        */

        $visitor->syncPermissions([]);
    }
}
