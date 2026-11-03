<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Card\UpdateCardRaritySettingsRequest;
use App\Models\CardRaritySetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CardRaritySettingController extends Controller
{
    /**
     * Actualizar la configuración de duelos por rareza.
     */
    public function update(
        UpdateCardRaritySettingsRequest $request
    ): RedirectResponse {
        DB::transaction(function () use ($request) {
            // Solo se actualizan los campos recibidos.
            foreach ($request->validated('settings') as $setting) {
                $values = Arr::only($setting, [
                    'questions_per_duel',
                    'retries',
                    'cooldown_minutes',
                    'reveal_answers_on_lockout',
                ]);

                CardRaritySetting::updateOrCreate(
                    ['rarity' => $setting['rarity']],
                    $values
                );
            }
        });

        return back()->with(
            'success',
            'Configuración por rareza actualizada correctamente.'
        );
    }
}
