<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Obtener / crear roles
        |--------------------------------------------------------------------------
        */

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

        $this->ensureQrToken($adminUser);

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

        $this->ensureQrToken($staffUser);

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

        $this->ensureQrToken($visitorUser);

        $visitorUser->syncRoles([$visitor]);
    }

    /*
    |--------------------------------------------------------------------------
    | Generar QR token si el usuario no tiene uno
    |--------------------------------------------------------------------------
    */

    private function ensureQrToken(User $user): void
    {
        if (!$user->qr_token) {
            do {
                $token = Str::random(64);
            } while (
                User::where('qr_token', $token)->exists()
            );

            $user->update([
                'qr_token' => $token,
            ]);
        }
    }
}