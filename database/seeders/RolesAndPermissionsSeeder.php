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
            'roles.create',
            'roles.edit',
            'roles.delete',

            // Permissions
            'permissions.view',
            'permissions.create',
            'permissions.edit',
            'permissions.delete',

            // Species Categories
            'species_categories.view',
            'species_categories.create',
            'species_categories.edit',
            'species_categories.delete',

            // Species
            'species.view',
            'species.create',
            'species.edit',
            'species.delete',

            // Species Images
            'species_images.view',
            'species_images.create',
            'species_images.edit',
            'species_images.delete',

            // Species Models
            'species_models.view',
            'species_models.create',
            'species_models.edit',
            'species_models.delete',

            // Species Locations
            'species_locations.view',
            'species_locations.create',
            'species_locations.edit',
            'species_locations.delete',

            // Species Tags
            'species_tags.view',
            'species_tags.create',
            'species_tags.edit',
            'species_tags.delete',

            // Map Configuration
            'map_config.view',
            'map_config.create',
            'map_config.edit',
            'map_config.delete',

            // Map Markers
            'map_markers.view',
            'map_markers.create',
            'map_markers.edit',
            'map_markers.delete',

            // Zoo Zones
            'zoo_zones.view',
            'zoo_zones.create',
            'zoo_zones.edit',
            'zoo_zones.delete',

            // Ticket Types
            'ticket_types.view',
            'ticket_types.create',
            'ticket_types.edit',
            'ticket_types.delete',

            // Ticket Orders
            'ticket_orders.view',
            'ticket_orders.create',
            'ticket_orders.edit',
            'ticket_orders.delete',

            // Tickets
            'tickets.view',
            'tickets.create',
            'tickets.edit',
            'tickets.delete',

            // Ticket Validations
            'ticket_validations.view',
            'ticket_validations.create',
            'ticket_validations.edit',
            'ticket_validations.delete',
            'tickets.validate',
            'tickets.scan',

            // Payments
            'payments.view',
            'payments.create',
            'payments.edit',
            'payments.delete',
            'payments.process',
            'payments.refund',

            // Cards
            'cards.view',
            'cards.create',
            'cards.edit',
            'cards.delete',

            // Species Captures / AR
            'species_captures.view',
            'species_captures.create',
            'species_captures.edit',
            'species_captures.delete',
            'species_captures.capture',

            // Levels
            'levels.view',
            'levels.create',
            'levels.edit',
            'levels.delete',

            // Point Movements
            'point_movements.view',
            'point_movements.create',
            'point_movements.edit',
            'point_movements.delete',
            'point_movements.adjust',

            // Rewards
            'rewards.view',
            'rewards.create',
            'rewards.edit',
            'rewards.delete',

            // Reward Redemptions
            'reward_redemptions.view',
            'reward_redemptions.create',
            'reward_redemptions.edit',
            'reward_redemptions.delete',
            'reward_redemptions.redeem',

            // Partners
            'partners.view',
            'partners.create',
            'partners.edit',
            'partners.delete',

            // Partner Branches
            'partner_branches.view',
            'partner_branches.create',
            'partner_branches.edit',
            'partner_branches.delete',

            // Events
            'events.view',
            'events.create',
            'events.edit',
            'events.delete',
            'events.publish',
            'events.cancel',

            // Diploma Requests
            'diploma_requests.view',
            'diploma_requests.create',
            'diploma_requests.edit',
            'diploma_requests.delete',
            'diploma_requests.approve',
            'diploma_requests.reject',

            // Notifications
            'notifications.view',
            'notifications.create',
            'notifications.edit',
            'notifications.delete',

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
        | Staff puede operar los módulos principales del zoológico.
        |
        */

        $staff->syncPermissions([

            // Dashboard
            'view.dashboard',

            // Species
            'species_categories.view',
            'species_categories.create',
            'species_categories.edit',

            'species.view',
            'species.create',
            'species.edit',

            // Images
            'species_images.view',
            'species_images.create',
            'species_images.edit',

            // Models
            'species_models.view',
            'species_models.create',
            'species_models.edit',

            // Locations
            'species_locations.view',
            'species_locations.create',
            'species_locations.edit',

            // Tags
            'species_tags.view',
            'species_tags.create',
            'species_tags.edit',

            // Map
            'map_config.view',
            'map_markers.view',
            'map_markers.create',
            'map_markers.edit',
            'zoo_zones.view',
            'zoo_zones.create',
            'zoo_zones.edit',

            // Tickets
            'ticket_types.view',
            'ticket_orders.view',
            'tickets.view',
            'tickets.validate',
            'tickets.scan',
            'ticket_validations.view',

            // Cards / AR
            'cards.view',
            'cards.create',
            'cards.edit',

            'species_captures.view',
            'species_captures.capture',

            // Levels
            'levels.view',

            // Points
            'point_movements.view',

            // Rewards
            'rewards.view',
            'reward_redemptions.view',
            'reward_redemptions.redeem',

            // Partners
            'partners.view',
            'partner_branches.view',

            // Events
            'events.view',

            // Diplomas
            'diploma_requests.view',
            'diploma_requests.create',

            // Notifications
            'notifications.view',
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