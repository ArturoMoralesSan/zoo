<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permisos para exportar e importar paquetes de tarjetas.
 *
 * En una base existente se dan a admin sin volver a correr
 * RolesAndPermissionsSeeder (sobrescribe los permisos editados en el panel).
 * En una instalación nueva el rol aún no existe y el seeder los crea después.
 */
return new class extends Migration
{
    private const PERMISSIONS = ['cards.export', 'cards.import'];

    public function up(): void
    {
        $admin = Role::query()->where('name', 'admin')->where('guard_name', 'web')->first();

        if (! $admin) {
            return;
        }

        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $admin->givePermissionTo(self::PERMISSIONS);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->whereIn('name', self::PERMISSIONS)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
