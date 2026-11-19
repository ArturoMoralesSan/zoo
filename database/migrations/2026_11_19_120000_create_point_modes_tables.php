<?php

use App\Models\Link;
use App\Models\Menu;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Modos de puntos (temporadas o eventos): cada modo cambia los puntos de
 * algunas reglas. Solo uno puede estar activo; sin modo activo se usan los
 * puntos base de cada regla.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'point-modes.view',
        'point-modes.create',
        'point-modes.edit',
        'point-modes.delete',
    ];

    private const LINK_NAME = 'Modos de puntos';

    public function up(): void
    {
        Schema::create('point_modes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(false)->index();
            $table->timestamps();
        });

        // Solo se guardan las reglas que el modo cambia; las demás usan sus puntos base.
        Schema::create('point_mode_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('point_mode_id')->constrained()->cascadeOnDelete();
            $table->foreignId('point_rule_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('points');
            $table->timestamps();

            $table->unique(['point_mode_id', 'point_rule_id']);
        });

        Schema::table('point_movements', function (Blueprint $table) {
            $table->foreignId('point_mode_id')
                ->nullable()
                ->after('type')
                ->constrained()
                ->nullOnDelete();
        });

        $this->grantPermissionsAndLink();
    }

    public function down(): void
    {
        Schema::table('point_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('point_mode_id');
        });

        Schema::dropIfExists('point_mode_rules');
        Schema::dropIfExists('point_modes');

        Link::query()->where('name', self::LINK_NAME)->delete();
        Permission::query()->whereIn('name', self::PERMISSIONS)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * En una base existente agrega los permisos y el enlace del menú sin volver
     * a correr los seeders (RolesAndPermissionsSeeder sobrescribe los permisos
     * editados en el panel). En una instalación nueva los roles y el menú aún
     * no existen: esto no hace nada y los seeders lo crean después.
     */
    private function grantPermissionsAndLink(): void
    {
        $admin = Role::query()->where('name', 'admin')->where('guard_name', 'web')->first();

        if ($admin) {
            foreach (self::PERMISSIONS as $permission) {
                Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
            }

            $admin->givePermissionTo(self::PERMISSIONS);

            Role::query()
                ->where('name', 'staff')
                ->where('guard_name', 'web')
                ->first()
                ?->givePermissionTo('point-modes.view');

            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        $menu = Menu::query()->where('name', 'Gamificación')->first();

        if (! $menu || Link::query()->where('menu_id', $menu->id)->where('name', self::LINK_NAME)->exists()) {
            return;
        }

        // Va después de "Reglas de puntos" (orden 2).
        Link::query()->where('menu_id', $menu->id)->where('order', '>=', 3)->increment('order');

        Link::create([
            'menu_id' => $menu->id,
            'name' => self::LINK_NAME,
            'icon' => 'CalendarRange',
            'order' => 3,
            'route' => 'admin.point-modes.index',
            'permission_id' => Permission::query()
                ->where('name', 'point-modes.view')
                ->where('guard_name', 'web')
                ->value('id'),
        ]);
    }
};
