<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Card\UpdateDuelMessageSettingsRequest;
use App\Models\DuelMessageSetting;
use Illuminate\Http\RedirectResponse;

class DuelMessageSettingController extends Controller
{
    /**
     * Actualizar los mensajes predeterminados del duelo.
     *
     * Un mensaje vacío usa el texto integrado.
     */
    public function update(
        UpdateDuelMessageSettingsRequest $request
    ): RedirectResponse {
        DuelMessageSetting::current()->update(
            collect($request->validated())
                ->map(fn (?string $message) => trim((string) $message) ?: null)
                ->all()
        );

        return back()->with(
            'success',
            'Mensajes del duelo actualizados correctamente.'
        );
    }
}
