<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * La exportación e importación pasa a Recursos y ahora incluye especies:
 * cards.export / cards.import se convierten en assets.export / assets.import.
 *
 * Cada rol o usuario que tenía el permiso viejo recibe el nuevo, así nadie
 * pierde acceso y no hace falta volver a correr los seeders. En una
 * instalación nueva no hay nada que mover y el seeder crea los nuevos.
 */
return new class extends Migration
{
    private const RENAMES = [
        'cards.export' => 'assets.export',
        'cards.import' => 'assets.import',
    ];

    public function up(): void
    {
        $this->move(self::RENAMES);
    }

    public function down(): void
    {
        $this->move(array_flip(self::RENAMES));
    }

    /**
     * @param  array<string, string>  $renames  Permiso viejo → nuevo.
     */
    private function move(array $renames): void
    {
        foreach ($renames as $from => $to) {
            $old = Permission::query()->where('name', $from)->where('guard_name', 'web')->first();

            if (! $old) {
                continue;
            }

            $new = Permission::firstOrCreate(['name' => $to, 'guard_name' => 'web']);

            foreach ($old->roles as $role) {
                /** @var Role $role */
                $role->givePermissionTo($new);
            }

            foreach ($old->users as $user) {
                $user->givePermissionTo($new);
            }

            $old->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
