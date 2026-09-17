<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ExploreController;
use App\Http\Controllers\Api\MapController;
use App\Http\Controllers\Api\SpeciesController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    /**
     * Perfil
     */
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);

    /**
     * Avatar independiente.
     */
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar']);

    /**
     * Explorar
     */
    Route::get('/explore', [ExploreController::class, 'index']);


    Route::get('/map', [ MapController::class, 'index', ])->name('api.map.index');
    Route::get('/map/zone/{zooZone}', [ MapController::class, 'zone', ])->name('api.map.zone');

    Route::get('/species', [SpeciesController::class, 'index']);
    Route::get('/species/{species}', [SpeciesController::class, 'show']);
});
