<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\LevelController;
use App\Http\Controllers\Admin\SpeciesCategoryController;
use App\Http\Controllers\Admin\SpeciesController;
use App\Http\Controllers\Admin\SpeciesTagController;
use App\Http\Controllers\Admin\TicketTypeController;


Route::inertia('/', 'Welcome')->name('home');

/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
*/

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

        // Levels
        Route::resource('levels', LevelController::class)
            ->middleware([
                'index'   => 'permission:levels.view',
                'create'  => 'permission:levels.create',
                'store'   => 'permission:levels.create',
                'edit'    => 'permission:levels.edit',
                'update'  => 'permission:levels.edit',
                'destroy' => 'permission:levels.delete',
            ]);

        Route::resource('species-categories', SpeciesCategoryController::class)
            ->except(['show'])
            ->middleware([
                'index' => 'permission:species_categories.view',
                'create' => 'permission:species_categories.create',
                'store' => 'permission:species_categories.create',
                'edit' => 'permission:species_categories.edit',
                'update' => 'permission:species_categories.edit',
                'destroy' => 'permission:species_categories.delete',
            ]);
        
        Route::resource('species', SpeciesController::class)
            ->middleware([
                'index' => 'permission:species.view',
                'create' => 'permission:species.create',
                'store' => 'permission:species.create',
                'show' => 'permission:species.view',
                'edit' => 'permission:species.edit',
                'update' => 'permission:species.edit',
                'destroy' => 'permission:species.delete',
            ]);

        Route::resource('species-tags', SpeciesTagController::class)
            ->except(['show'])
            ->middleware([
                'index' => 'permission:species_tags.view',
                'create' => 'permission:species_tags.create',
                'store' => 'permission:species_tags.create',
                'edit' => 'permission:species_tags.edit',
                'update' => 'permission:species_tags.edit',
                'destroy' => 'permission:species_tags.delete',
            ]);


        Route::resource('ticket-types', TicketTypeController::class)
            ->except(['show'])
            ->middleware([
                'index' => 'permission:ticket-types.view',
                'create' => 'permission:ticket-types.create',
                'store' => 'permission:ticket-types.create',
                'edit' => 'permission:ticket-types.edit',
                'update' => 'permission:ticket-types.edit',
                'destroy' => 'permission:ticket-types.delete',
            ]);


    });

require __DIR__.'/settings.php';