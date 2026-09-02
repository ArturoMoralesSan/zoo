<?php

use Illuminate\Support\Facades\Route;

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

    });

require __DIR__.'/settings.php';