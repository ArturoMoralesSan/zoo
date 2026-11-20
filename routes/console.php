<?php

use App\Models\CardTransfer;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Borra los paquetes de tarjetas (.zip) con más de CardTransfer::KEEP_DAYS días.
Schedule::command('model:prune', ['--model' => [CardTransfer::class]])->daily();
