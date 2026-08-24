<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Roles
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $staff = Role::firstOrCreate(['name' => 'staff']);
        $visitor = Role::firstOrCreate(['name' => 'visitor']);

        // Permissions
        $permissions = [
            // Users
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',

            // Species
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
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
            ]);
        }

        // Admin has all permissions
        $admin->syncPermissions($permissions);

        // Staff permissions
        $staff->syncPermissions([
            'users.view',

            'species.view',

            'cards.view',

            'tickets.view',
            'tickets.validate',

            'donations.view',

            'diplomas.view',
            'diplomas.edit',

            'map.view',

            'events.view',

            'points.view',
        ]);

        // Visitors do not require administrative permissions.
    }
}