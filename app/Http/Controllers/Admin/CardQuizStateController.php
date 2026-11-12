<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CardQuizState;
use App\Models\QuizAttempt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class CardQuizStateController extends Controller
{
    /**
     * Reiniciar los intentos de un usuario con una tarjeta (herramienta de desarrollo).
     *
     * Quita el bloqueo y borra sus duelos no ganados con esa tarjeta,
     * como si nunca hubiera intentado un duelo.
     */
    public function destroy(CardQuizState $state): RedirectResponse
    {
        // Herramienta de desarrollo: no existe fuera de APP_ZOODECK_DEV.
        abort_unless(config('app.zoodeck_dev'), 404);

        DB::transaction(function () use ($state) {
            QuizAttempt::query()
                ->where('user_id', $state->user_id)
                ->where('card_id', $state->card_id)
                ->where('status', '!=', QuizAttempt::STATUS_PASSED)
                ->delete();

            $state->delete();
        });

        return back()->with(
            'success',
            'Intentos reiniciados correctamente.'
        );
    }
}
