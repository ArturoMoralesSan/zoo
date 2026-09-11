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
use App\Http\Controllers\Admin\PaymentMethodController;
use App\Http\Controllers\Admin\TicketOrderController;
use App\Http\Controllers\Admin\ZooZoneController;
use App\Http\Controllers\Admin\MapMarkerController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\PointRuleController;
use App\Http\Controllers\Admin\PointMovementController;
use App\Http\Controllers\Admin\RewardController;
use App\Http\Controllers\Admin\RewardRedemptionController;
use App\Http\Controllers\Admin\DonationController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\MapPathController;
use App\Http\Controllers\MapController;

Route::inertia('/', 'Welcome')->name('home');

Route::get('/map', [MapController::class, 'index'])
    ->name('map.index');

Route::get('/map/zone/{zooZone}', [MapController::class, 'zone'])
    ->name('map.zone');
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

        Route::get('dashboard', [DashboardController::class, 'index'])
        ->name('dashboard')
        ->middleware('permission:dashboard.view');

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


        Route::resource('payment-methods', PaymentMethodController::class)
            ->except(['show'])
            ->middleware([
                'index' => 'permission:payment-methods.view',
                'create' => 'permission:payment-methods.create',
                'store' => 'permission:payment-methods.create',
                'edit' => 'permission:payment-methods.edit',
                'update' => 'permission:payment-methods.edit',
                'destroy' => 'permission:payment-methods.delete',
            ]);

        Route::post('ticket-orders/user-by-qr', [TicketOrderController::class, 'userByQr'])
            ->name('ticket-orders.user-by-qr')
            ->middleware('permission:ticket-orders.create');

        Route::resource('ticket-orders', TicketOrderController::class)
        ->only([
            'index',
            'create',
            'store',
            'show',
            'destroy',
        ])
        ->middleware([
            'index' => 'permission:ticket-orders.view',
            'create' => 'permission:ticket-orders.create',
            'store' => 'permission:ticket-orders.create',
            'show' => 'permission:ticket-orders.view',
            'destroy' => 'permission:ticket-orders.delete',
        ]);

        Route::resource('zoo-zones', ZooZoneController::class)
            ->middleware([
                'index' => 'permission:zoo-zones.view',
                'create' => 'permission:zoo-zones.create',
                'store' => 'permission:zoo-zones.create',
                'show' => 'permission:zoo-zones.view',
                'edit' => 'permission:zoo-zones.edit',
                'update' => 'permission:zoo-zones.edit',
                'destroy' => 'permission:zoo-zones.delete',
            ]);

        Route::resource('map-markers', MapMarkerController::class)
        ->middleware([
            'index' => 'permission:map-markers.view',
            'create' => 'permission:map-markers.create',
            'store' => 'permission:map-markers.create',
            'show' => 'permission:map-markers.view',
            'edit' => 'permission:map-markers.edit',
            'update' => 'permission:map-markers.edit',
            'destroy' => 'permission:map-markers.delete',
        ]);

        Route::resource('events', EventController::class)
            ->except(['show'])
            ->middleware([
                'index' => 'permission:events.view',
                'create' => 'permission:events.create',
                'store' => 'permission:events.create',
                'edit' => 'permission:events.edit',
                'update' => 'permission:events.edit',
                'destroy' => 'permission:events.delete',
            ]);

        Route::resource('point-rules', PointRuleController::class)
            ->except(['show'])
            ->middleware([
                'index' => 'permission:point-rules.view',
                'create' => 'permission:point-rules.create',
                'store' => 'permission:point-rules.create',
                'edit' => 'permission:point-rules.edit',
                'update' => 'permission:point-rules.edit',
                'destroy' => 'permission:point-rules.delete',
            ]);
        
        Route::resource('point-movements', PointMovementController::class)
            ->only(['index'])
            ->middleware([
                'index' => 'permission:point-movements.view',
            ]);

        Route::resource('rewards', RewardController::class)
            ->except(['show'])
            ->middleware([
                'index' => 'permission:rewards.view',
                'create' => 'permission:rewards.create',
                'store' => 'permission:rewards.create',
                'edit' => 'permission:rewards.edit',
                'update' => 'permission:rewards.edit',
                'destroy' => 'permission:rewards.delete',
            ]);

        Route::post(
            'reward-redemptions/user-by-qr',
            [RewardRedemptionController::class, 'userByQr']
        )
            ->name('reward-redemptions.user-by-qr')
            ->middleware('permission:reward-redemptions.create');

        Route::resource('reward-redemptions', RewardRedemptionController::class)
            ->only([
                'index',
                'create',
                'store',
            ])
            ->middleware([
                'index' => 'permission:reward-redemptions.view',
                'create' => 'permission:reward-redemptions.create',
                'store' => 'permission:reward-redemptions.create',
            ]);

        Route::post(
            'donations/user-by-qr',
            [DonationController::class, 'userByQr']
        )
            ->name('donations.user-by-qr')
            ->middleware('permission:donations.create');

        Route::resource('donations', DonationController::class)
            ->only([
                'index',
                'create',
                'store',
                'show',
            ])
            ->middleware([
                'index' => 'permission:donations.view',
                'create' => 'permission:donations.create',
                'store' => 'permission:donations.create',
                'show' => 'permission:donations.view',
            ]);

        Route::get('map-paths/zone/{zooZone}', [MapPathController::class, 'zone'])
            ->name('map-paths.zone')
            ->middleware('permission:map_paths.create');

        Route::resource('map-paths', MapPathController::class)
            ->only([
                'index',
                'create',
                'store',
                'show',
                'edit',
                'update',
                'destroy',
            ])
            ->middleware([
                'index' => 'permission:map_paths.view',
                'create' => 'permission:map_paths.create',
                'store' => 'permission:map_paths.create',
                'show' => 'permission:map_paths.view',
                'edit' => 'permission:map_paths.edit',
                'update' => 'permission:map_paths.edit',
                'destroy' => 'permission:map_paths.delete',
            ]);

    });

require __DIR__.'/settings.php';