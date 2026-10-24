<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Card\UpdateCardRaritySettingsRequest;
use App\Models\CardRaritySetting;
use Illuminate\Http\RedirectResponse;
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
            foreach ($request->validated('settings') as $setting) {
                CardRaritySetting::updateOrCreate(
                    ['rarity' => $setting['rarity']],
                    [
                        'questions_per_duel' => $setting['questions_per_duel'],
                        'retries' => $setting['retries'],
                    ]
                );
            }
        });

        return back()->with(
            'success',
            'Configuración por rareza actualizada correctamente.'
        );
    }
}
