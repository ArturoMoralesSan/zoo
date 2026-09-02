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
        | Permissions
        |--------------------------------------------------------------------------
        */

        $permissions = [

            // Dashboard
            'view.dashboard',

            // Users
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',

            // Roles
            'roles.view',

            // Permissions
            'permissions.view',

            // Species / Zoo
            'species.view',
            'species.create',
            'species.edit',
            'species.delete',

            // Cards
            'cards.view',
            'cards.create',
            'cards.edit',
            'cards.delete',

            // Tickets
            'tickets.view',
            'tickets.create',
            'tickets.edit',
            'tickets.validate',

            // Donations
            'donations.view',
            'donations.create',

            // Diplomas
            'diplomas.view',
            'diplomas.create',
            'diplomas.edit',

            // Map
            'map.view',
            'map.create',
            'map.edit',

            // Events
            'events.view',
            'events.create',
            'events.edit',

            // Points
            'points.view',
            'points.edit',

            // Settings
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
        | ADMIN
        |--------------------------------------------------------------------------
        |
        | Admin tiene todos los permisos.
        |
        */

        $admin->syncPermissions($permissions);

        /*
        |--------------------------------------------------------------------------
        | STAFF
        |--------------------------------------------------------------------------
        |
        | Staff puede:
        |
        | - Ver Dashboard
        | - Ver Usuarios
        | - Administrar el Zoo / Species
        |
        */

        $staff->syncPermissions([
            'view.dashboard',

            // Users
            'users.view',

            // Zoo / Species
            'species.view',
            'species.create',
            'species.edit',
            'species.delete',
        ]);

        /*
        |--------------------------------------------------------------------------
        | VISITOR
        |--------------------------------------------------------------------------
        |
        | Visitor no tiene permisos administrativos.
        |
        */

        $visitor->syncPermissions([]);
    }
}