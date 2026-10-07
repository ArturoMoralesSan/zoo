<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CardCaptureController;
use App\Http\Controllers\Api\CardController;
use App\Http\Controllers\Api\DonationController;
use App\Http\Controllers\Api\ExploreController;
use App\Http\Controllers\Api\MapController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\QuizController;
use App\Http\Controllers\Api\SpeciesController;
use App\Http\Controllers\Api\TicketController;

use Illuminate\Support\Facades\Route;

Route::post('/register', [
    AuthController::class,
    'register',
]);

Route::post('/login', [
    AuthController::class,
    'login',
]);

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user', [
        AuthController::class,
        'user',
    ]);

    Route::post('/logout', [
        AuthController::class,
        'logout',
    ]);

    /*
    |--------------------------------------------------------------------------
    | Perfil
    |--------------------------------------------------------------------------
    */

    Route::get('/profile', [
        ProfileController::class,
        'show',
    ]);

    Route::put('/profile', [
        ProfileController::class,
        'update',
    ]);

    /*
    |--------------------------------------------------------------------------
    | Avatar independiente
    |--------------------------------------------------------------------------
    */

    Route::post('/profile/avatar', [
        ProfileController::class,
        'updateAvatar',
    ]);

    /*
    |--------------------------------------------------------------------------
    | Explorar
    |--------------------------------------------------------------------------
    */

    Route::get('/explore', [
        ExploreController::class,
        'index',
    ]);

    Route::get('/map', [
        MapController::class,
        'index',
    ])->name('api.map.index');

    Route::get('/map/zone/{zooZone}', [
        MapController::class,
        'zone',
    ])->name('api.map.zone');

    Route::get('/species', [
        SpeciesController::class,
        'index',
    ]);

    Route::get('/species/{species}', [
        SpeciesController::class,
        'show',
    ]);

    /*
    |--------------------------------------------------------------------------
    | Cards
    |--------------------------------------------------------------------------
    */

    Route::get('/cards', [
        CardController::class,
        'index',
    ])->name('api.cards.index');

    Route::get('/cards/{card}', [
        CardController::class,
        'show',
    ])->name('api.cards.show');

    /*
    |--------------------------------------------------------------------------
    | Quiz de Cards
    |--------------------------------------------------------------------------
    */

    Route::get('/cards/{card}/quiz', [
        QuizController::class,
        'show',
    ])->name('api.cards.quiz.show');

    Route::post('/cards/{card}/quiz/submit', [
        QuizController::class,
        'submit',
    ])->name('api.cards.quiz.submit');

    /*
    |--------------------------------------------------------------------------
    | Cards capturadas por el usuario
    |--------------------------------------------------------------------------
    */

    Route::get('/my-cards', [
        CardCaptureController::class,
        'index',
    ])->name('api.card-captures.index');

    Route::get('/my-cards/{card}', [
        CardCaptureController::class,
        'show',
    ])->name('api.card-captures.show');

    Route::post('/cards/{card}/capture', [
        CardCaptureController::class,
        'store',
    ])->name('api.card-captures.store');

    /*
    |--------------------------------------------------------------------------
    | Boletos
    |--------------------------------------------------------------------------
    */

    Route::get('/tickets/types', [
        TicketController::class,
        'types',
    ])->name('api.tickets.types');

    Route::post('/tickets/orders', [
        TicketController::class,
        'storeOrder',
    ])->name('api.tickets.orders.store');

    Route::get('/tickets/orders/{ticketOrder}', [
        TicketController::class,
        'showOrder',
    ])->name('api.tickets.orders.show');

    Route::get('/tickets', [
        TicketController::class,
        'myTickets',
    ])->name('api.tickets.index');

    /*
    |--------------------------------------------------------------------------
    | Donaciones
    |--------------------------------------------------------------------------
    */

    Route::get('/donations', [
        DonationController::class,
        'index',
    ]);

    Route::post('/donations', [
        DonationController::class,
        'store',
    ]);

    Route::get('/donations/{donation}', [
        DonationController::class,
        'show',
    ]);
});