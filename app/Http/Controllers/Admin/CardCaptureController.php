<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CardCapture;
use App\Models\CardQuizState;
use App\Models\QuizAttempt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CardCaptureController extends Controller
{
    /**
     * Quitar una tarjeta obtenida a un usuario (herramienta de desarrollo).
     *
     * Borra la captura y todo el historial de duelos del usuario con esa
     * tarjeta (duelos e intentos/bloqueo), como si nunca la hubiera ganado.
     */
    public function destroy(CardCapture $capture): RedirectResponse
    {
        DB::transaction(function () use ($capture) {
            $where = [
                'user_id' => $capture->user_id,
                'card_id' => $capture->card_id,
            ];

            QuizAttempt::query()->where($where)->delete();
            CardQuizState::query()->where($where)->delete();

            $capture->delete();
        });

        if ($capture->capture_image) {
            Storage::disk('public')->delete($capture->capture_image);
        }

        return back()->with(
            'success',
            'Tarjeta revocada correctamente.'
        );
    }
}
