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

        $superadmin = Role::firstOrCreate([
            'name' => 'SuperAdmin',
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
                'email' => 'ramon.morales41@gmail.com',
            ],
            [
                'name' => 'Super Administrador',
                'password' => Hash::make('12345678'),
            ]
        );

        $adminUser->syncRoles([$superadmin]);

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