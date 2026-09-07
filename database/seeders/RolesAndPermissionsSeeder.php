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

            /*
            |--------------------------------------------------------------------------
            | Dashboard
            |--------------------------------------------------------------------------
            */

            'dashboard.view',

            /*
            |--------------------------------------------------------------------------
            | Usuarios
            |--------------------------------------------------------------------------
            */

            'users.view',
            'users.create',
            'users.edit',
            'users.delete',

            /*
            |--------------------------------------------------------------------------
            | Visitantes
            |--------------------------------------------------------------------------
            */

            'visitors.view',
            'visitors.create',
            'visitors.edit',
            'visitors.delete',

            /*
            |--------------------------------------------------------------------------
            | Roles
            |--------------------------------------------------------------------------
            */

            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.delete',

            /*
            |--------------------------------------------------------------------------
            | Permisos
            |--------------------------------------------------------------------------
            */

            'permissions.view',
            'permissions.create',
            'permissions.edit',
            'permissions.delete',

            /*
            |--------------------------------------------------------------------------
            | Categorías de especies
            |--------------------------------------------------------------------------
            */

            'species-categories.view',
            'species-categories.create',
            'species-categories.edit',
            'species-categories.delete',

            /*
            |--------------------------------------------------------------------------
            | Especies
            |--------------------------------------------------------------------------
            */

            'species.view',
            'species.create',
            'species.edit',
            'species.delete',

            /*
            |--------------------------------------------------------------------------
            | Imágenes de especies
            |--------------------------------------------------------------------------
            */

            'species-images.view',
            'species-images.create',
            'species-images.edit',
            'species-images.delete',

            /*
            |--------------------------------------------------------------------------
            | Modelos 3D
            |--------------------------------------------------------------------------
            */

            'species-models.view',
            'species-models.create',
            'species-models.edit',
            'species-models.delete',

            /*
            |--------------------------------------------------------------------------
            | Ubicaciones de especies
            |--------------------------------------------------------------------------
            */

            'species-locations.view',
            'species-locations.create',
            'species-locations.edit',
            'species-locations.delete',

            /*
            |--------------------------------------------------------------------------
            | Etiquetas
            |--------------------------------------------------------------------------
            */

            'species-tags.view',
            'species-tags.create',
            'species-tags.edit',
            'species-tags.delete',

            /*
            |--------------------------------------------------------------------------
            | Mapa
            |--------------------------------------------------------------------------
            */

            'map.view',
            'map.create',
            'map.edit',
            'map.delete',

            /*
            |--------------------------------------------------------------------------
            | Configuración del mapa
            |--------------------------------------------------------------------------
            */

            'map-config.view',
            'map-config.create',
            'map-config.edit',
            'map-config.delete',

            /*
            |--------------------------------------------------------------------------
            | Marcadores del mapa
            |--------------------------------------------------------------------------
            */

            'map-markers.view',
            'map-markers.create',
            'map-markers.edit',
            'map-markers.delete',

            /*
            |--------------------------------------------------------------------------
            | Zonas del zoológico
            |--------------------------------------------------------------------------
            */

            'zoo-zones.view',
            'zoo-zones.create',
            'zoo-zones.edit',
            'zoo-zones.delete',

            /*
            |--------------------------------------------------------------------------
            | Tipos de boleto
            |--------------------------------------------------------------------------
            */

            'ticket-types.view',
            'ticket-types.create',
            'ticket-types.edit',
            'ticket-types.delete',

            /*
            |--------------------------------------------------------------------------
            | Órdenes de boletos
            |--------------------------------------------------------------------------
            */

            'ticket-orders.view',
            'ticket-orders.create',
            'ticket-orders.edit',
            'ticket-orders.delete',

            /*
            |--------------------------------------------------------------------------
            | Boletos
            |--------------------------------------------------------------------------
            */

            'tickets.view',
            'tickets.create',
            'tickets.edit',
            'tickets.delete',

            /*
            |--------------------------------------------------------------------------
            | Validación de boletos
            |--------------------------------------------------------------------------
            */

            'ticket-validations.view',
            'ticket-validations.create',
            'ticket-validations.edit',
            'ticket-validations.delete',

            'tickets.validate',
            'tickets.scan',

            /*
            |--------------------------------------------------------------------------
            | Pagos
            |--------------------------------------------------------------------------
            */

            'payments.view',
            'payments.create',
            'payments.edit',
            'payments.delete',

            'payments.process',
            'payments.refund',

            /*
            |--------------------------------------------------------------------------
            | Capturas AR
            |--------------------------------------------------------------------------
            */

            'species-captures.view',
            'species-captures.create',
            'species-captures.edit',
            'species-captures.delete',

            'species-captures.capture',

            /*
            |--------------------------------------------------------------------------
            | Niveles
            |--------------------------------------------------------------------------
            */

            'levels.view',
            'levels.create',
            'levels.edit',
            'levels.delete',

            /*
            |--------------------------------------------------------------------------
            | Movimientos de puntos
            |--------------------------------------------------------------------------
            */

            'point-movements.view',
            'point-movements.create',
            'point-movements.edit',
            'point-movements.delete',

            'point-movements.adjust',

            /*
            |--------------------------------------------------------------------------
            | Recompensas
            |--------------------------------------------------------------------------
            */

            'rewards.view',
            'rewards.create',
            'rewards.edit',
            'rewards.delete',

            /*
            |--------------------------------------------------------------------------
            | Canjes de recompensas
            |--------------------------------------------------------------------------
            */

            'reward-redemptions.view',
            'reward-redemptions.create',
            'reward-redemptions.edit',
            'reward-redemptions.delete',

            'reward-redemptions.redeem',

            /*
            |--------------------------------------------------------------------------
            | Colecciones
            |--------------------------------------------------------------------------
            */

            'collections.view',
            'collections.create',
            'collections.edit',
            'collections.delete',

            /*
            |--------------------------------------------------------------------------
            | Socios / Partners
            |--------------------------------------------------------------------------
            */

            'partners.view',
            'partners.create',
            'partners.edit',
            'partners.delete',

            /*
            |--------------------------------------------------------------------------
            | Sucursales de partners
            |--------------------------------------------------------------------------
            */

            'partner-branches.view',
            'partner-branches.create',
            'partner-branches.edit',
            'partner-branches.delete',

            /*
            |--------------------------------------------------------------------------
            | Eventos
            |--------------------------------------------------------------------------
            */

            'events.view',
            'events.create',
            'events.edit',
            'events.delete',

            'events.publish',
            'events.cancel',

            /*
            |--------------------------------------------------------------------------
            | Solicitudes de diplomas
            |--------------------------------------------------------------------------
            */

            'diploma-requests.view',
            'diploma-requests.create',
            'diploma-requests.edit',
            'diploma-requests.delete',

            'diploma-requests.approve',
            'diploma-requests.reject',

            /*
            |--------------------------------------------------------------------------
            | Diplomas
            |--------------------------------------------------------------------------
            */

            'diplomas.view',
            'diplomas.create',
            'diplomas.edit',
            'diplomas.delete',

            /*
            |--------------------------------------------------------------------------
            | Notificaciones
            |--------------------------------------------------------------------------
            */

            'notifications.view',
            'notifications.create',
            'notifications.edit',
            'notifications.delete',

            /*
            |--------------------------------------------------------------------------
            | Reportes
            |--------------------------------------------------------------------------
            */

            'reports.view',
            'reports.tickets',
            'reports.users',
            'reports.points',
            'reports.captures',

            /*
            |--------------------------------------------------------------------------
            | Configuración
            |--------------------------------------------------------------------------
            */

            'settings.view',
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
        | Admin sí utiliza el sistema de permisos y tiene acceso
        | a todos los módulos administrativos.
        |
        */

        $admin->syncPermissions($permissions);

        /*
        |--------------------------------------------------------------------------
        | Staff
        |--------------------------------------------------------------------------
        |
        | Puede operar las funciones principales del zoológico,
        | pero no administrar usuarios, roles ni permisos.
        |
        */

        $staff->syncPermissions([

            /*
            |--------------------------------------------------------------------------
            | Dashboard
            |--------------------------------------------------------------------------
            */

            'dashboard.view',

            /*
            |--------------------------------------------------------------------------
            | Zoológico
            |--------------------------------------------------------------------------
            */

            'species-categories.view',
            'species-categories.create',
            'species-categories.edit',

            'species.view',
            'species.create',
            'species.edit',

            'species-images.view',
            'species-images.create',
            'species-images.edit',

            'species-models.view',
            'species-models.create',
            'species-models.edit',

            'species-locations.view',
            'species-locations.create',
            'species-locations.edit',

            'species-tags.view',
            'species-tags.create',
            'species-tags.edit',

            /*
            |--------------------------------------------------------------------------
            | Mapa
            |--------------------------------------------------------------------------
            */

            'map.view',

            'map-config.view',

            'map-markers.view',
            'map-markers.create',
            'map-markers.edit',

            'zoo-zones.view',
            'zoo-zones.create',
            'zoo-zones.edit',

            /*
            |--------------------------------------------------------------------------
            | Boletos
            |--------------------------------------------------------------------------
            */

            'ticket-types.view',

            'ticket-orders.view',

            'tickets.view',
            'tickets.create',

            'ticket-validations.view',

            'tickets.validate',
            'tickets.scan',

            /*
            |--------------------------------------------------------------------------
            | Pagos
            |--------------------------------------------------------------------------
            */

            'payments.view',
            'payments.create',
            'payments.process',

            /*
            |--------------------------------------------------------------------------
            | Capturas AR
            |--------------------------------------------------------------------------
            */

            'species-captures.view',
            'species-captures.capture',

            /*
            |--------------------------------------------------------------------------
            | Niveles
            |--------------------------------------------------------------------------
            */

            'levels.view',

            /*
            |--------------------------------------------------------------------------
            | Puntos
            |--------------------------------------------------------------------------
            */

            'point-movements.view',

            /*
            |--------------------------------------------------------------------------
            | Recompensas
            |--------------------------------------------------------------------------
            */

            'rewards.view',

            'reward-redemptions.view',
            'reward-redemptions.redeem',

            /*
            |--------------------------------------------------------------------------
            | Colecciones
            |--------------------------------------------------------------------------
            */

            'collections.view',

            /*
            |--------------------------------------------------------------------------
            | Partners
            |--------------------------------------------------------------------------
            */

            'partners.view',
            'partner-branches.view',

            /*
            |--------------------------------------------------------------------------
            | Eventos
            |--------------------------------------------------------------------------
            */

            'events.view',

            /*
            |--------------------------------------------------------------------------
            | Diplomas
            |--------------------------------------------------------------------------
            */

            'diploma-requests.view',
            'diploma-requests.create',

            /*
            |--------------------------------------------------------------------------
            | Notificaciones
            |--------------------------------------------------------------------------
            */

            'notifications.view',

            /*
            |--------------------------------------------------------------------------
            | Reportes
            |--------------------------------------------------------------------------
            */

            'reports.view',
            'reports.tickets',
            'reports.points',
            'reports.captures',
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
