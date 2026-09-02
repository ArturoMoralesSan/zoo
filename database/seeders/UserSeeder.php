<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Obtener / crear roles
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
        | ADMIN
        |--------------------------------------------------------------------------
        */

        $adminUser = User::updateOrCreate(
            [
                'email' => 'admin@zooapp.com',
            ],
            [
                'name' => 'Administrador',
                'password' => Hash::make('Admin12345'),
            ]
        );

        $adminUser->syncRoles([$admin]);

        /*
        |--------------------------------------------------------------------------
        | STAFF
        |--------------------------------------------------------------------------
        */

        $staffUser = User::updateOrCreate(
            [
                'email' => 'staff@zooapp.com',
            ],
            [
                'name' => 'Personal Zoo',
                'password' => Hash::make('Staff12345'),
            ]
        );

        $staffUser->syncRoles([$staff]);

        /*
        |--------------------------------------------------------------------------
        | VISITOR
        |--------------------------------------------------------------------------
        */

        $visitorUser = User::updateOrCreate(
            [
                'email' => 'visitor@zooapp.com',
            ],
            [
                'name' => 'Visitante',
                'password' => Hash::make('Visitor12345'),
            ]
        );

        $visitorUser->syncRoles([$visitor]);
    }
}