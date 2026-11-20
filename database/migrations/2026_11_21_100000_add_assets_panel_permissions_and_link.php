<?php

use App\Models\Link;
use App\Models\Menu;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Página "Recursos" (Administración): permisos y enlace del menú.
 *
 * En una base existente se dan a admin y se agrega el enlace sin volver a
 * correr los seeders (RolesAndPermissionsSeeder sobrescribe los permisos
 * editados en el panel). En una instalación nueva aún no hay roles ni menú,
 * y los seeders los crean después.
 */
return new class extends Migration
{
    private const PERMISSIONS = ['assets.view', 'assets.optimize'];

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

        $menu = Menu::query()->where('name', 'Administración')->first();

        if ($menu) {
            Link::updateOrCreate(
                ['menu_id' => $menu->id, 'name' => 'Recursos'],
                [
                    'icon' => 'HardDrive',
                    'order' => 4,
                    'route' => 'admin.assets.index',
                    'permission_id' => Permission::query()
                        ->where('name', 'assets.view')
                        ->where('guard_name', 'web')
                        ->value('id'),
                ]
            );
        }
    }

    public function down(): void
    {
        Link::query()->where('route', 'admin.assets.index')->delete();
        Permission::query()->whereIn('name', self::PERMISSIONS)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
