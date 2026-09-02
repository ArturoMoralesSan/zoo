<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;

Route::inertia('/', 'Welcome')->name('home');

/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
*/
Route::get('/test-permission', function () {
    $user = auth()->user();

    return [
        'usuario' => $user->email,
        'roles' => $user->getRoleNames(),
        'permisos_directos' => $user->getPermissionNames(),
        'todos_los_permisos' => $user->getAllPermissions()->pluck('name'),
        'puede_view_dashboard' => $user->can('view.dashboard'),
        'has_permission' => $user->hasPermissionTo('view.dashboard'),
    ];
    })->middleware('auth');

Route::middleware(['auth', 'verified'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Dashboard
        |--------------------------------------------------------------------------
        */

        Route::inertia('dashboard', 'Dashboard')
            ->name('dashboard')
            ->middleware('permission:view.dashboard');

        // Usuarios
        Route::resource('users', UserController::class)
            ->middleware([
                'index'   => 'permission:users.view',
                'create'  => 'permission:users.create',
                'store'   => 'permission:users.create',
                'edit'    => 'permission:users.edit',
                'update'  => 'permission:users.edit',
                'destroy' => 'permission:users.delete',
            ]);

        // Roles
        Route::resource('roles', RoleController::class)
            ->middleware([
                'index'   => 'permission:roles.view',
                'create'  => 'permission:roles.create',
                'store'   => 'permission:roles.create',
                'edit'    => 'permission:roles.edit',
                'update'  => 'permission:roles.edit',
                'destroy' => 'permission:roles.delete',
            ]);

        // Permisos
        Route::resource('permissions', PermissionController::class)
            ->middleware([
                'index'   => 'permission:permissions.view',
                'create'  => 'permission:permissions.create',
                'store'   => 'permission:permissions.create',
                'edit'    => 'permission:permissions.edit',
                'update'  => 'permission:permissions.edit',
                'destroy' => 'permission:permissions.delete',
            ]);

    });

require __DIR__.'/settings.php';