<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\Level;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Mostrar el formulario de perfil.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        /*
         * --------------------------------------------------------------------------
         * Puntos actuales
         * --------------------------------------------------------------------------
         */
        $points = (int) ($user->points ?? 0);

        /*
         * --------------------------------------------------------------------------
         * Nivel actual
         * --------------------------------------------------------------------------
         *
         * El nivel actual viene directamente de users.level_id.
         */
        $currentLevel = $user->level;

        /*
         * --------------------------------------------------------------------------
         * Siguiente nivel
         * --------------------------------------------------------------------------
         */
        $nextLevel = Level::query()
            ->where('min_points', '>', $points)
            ->orderBy('min_points')
            ->first();

        /*
         * --------------------------------------------------------------------------
         * Progreso
         * --------------------------------------------------------------------------
         */
        $progress = 0;
        $pointsToNextLevel = 0;

        if ($nextLevel) {
            $currentMinimum = $currentLevel
                ? (int) $currentLevel->min_points
                : 0;

            $nextMinimum = (int) $nextLevel->min_points;

            $range = $nextMinimum - $currentMinimum;

            if ($range > 0) {
                $progress = (
                    ($points - $currentMinimum) / $range
                ) * 100;
            }

            $progress = max(
                0,
                min(100, $progress)
            );

            $pointsToNextLevel = max(
                0,
                $nextMinimum - $points
            );
        } else {
            /*
             * ----------------------------------------------------------------------
             * Ya está en el nivel máximo
             * ----------------------------------------------------------------------
             */
            $progress = 100;
            $pointsToNextLevel = 0;
        }

        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,

            'status' => $request->session()->get('status'),

            /*
             * ----------------------------------------------------------------------
             * Perfil
             * ----------------------------------------------------------------------
             */
            'profile' => $user->profile
                ? [
                    'phone' => $user->profile->phone,

                    'birth_date' => $user->profile->birth_date?->format(
                        'Y-m-d'
                    ),

                    'avatar' => $user->profile->avatar,

                    'city' => $user->profile->city,

                    'country' => $user->profile->country,
                ]
                : null,

            /*
             * ----------------------------------------------------------------------
             * Gamificación
             * ----------------------------------------------------------------------
             */
            'gamification' => [
                'points' => $points,

                'current_level' => $currentLevel
                    ? [
                        'id' => $currentLevel->id,

                        'name' => $currentLevel->name,

                        'min_points' => (int) $currentLevel->min_points,

                        'max_points' => $currentLevel->max_points !== null
                            ? (int) $currentLevel->max_points
                            : null,
                    ]
                    : null,

                'next_level' => $nextLevel
                    ? [
                        'id' => $nextLevel->id,

                        'name' => $nextLevel->name,

                        'min_points' => (int) $nextLevel->min_points,

                        'max_points' => $nextLevel->max_points !== null
                            ? (int) $nextLevel->max_points
                            : null,
                    ]
                    : null,

                'progress' => round($progress, 2),

                'points_to_next_level' => $pointsToNextLevel,
            ],
        ]);
    }

    /**
     * Actualizar el perfil.
     */
    public function update(
        ProfileUpdateRequest $request
    ): RedirectResponse {
        $user = $request->user();

        $data = $request->validated();

        /*
         * --------------------------------------------------------------------------
         * Actualizar usuario
         * --------------------------------------------------------------------------
         */
        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        /*
         * --------------------------------------------------------------------------
         * Crear perfil si no existe
         * --------------------------------------------------------------------------
         */
        $profile = $user->profile()->firstOrCreate([
            'user_id' => $user->id,
        ]);

        /*
         * --------------------------------------------------------------------------
         * Actualizar datos del perfil
         * --------------------------------------------------------------------------
         */
        $profile->fill([
            'phone' => $data['phone'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
            'city' => $data['city'] ?? null,
            'country' => $data['country'] ?? null,
        ]);

        /*
         * --------------------------------------------------------------------------
         * Avatar
         * --------------------------------------------------------------------------
         */
        if ($request->hasFile('avatar')) {
            $avatar = $request->file('avatar');

            if ($avatar && $avatar->isValid()) {
                /*
                 * ----------------------------------------------------------------------
                 * Eliminar avatar anterior
                 * ----------------------------------------------------------------------
                 */
                if ($profile->avatar) {
                    Storage::disk('public')->delete(
                        $profile->avatar
                    );
                }

                /*
                 * ----------------------------------------------------------------------
                 * Generar nombre único
                 * ----------------------------------------------------------------------
                 */
                $filename =
                    uniqid().
                    '.'.
                    $avatar->getClientOriginalExtension();

                /*
                 * ----------------------------------------------------------------------
                 * Asegurar que exista el directorio
                 * ----------------------------------------------------------------------
                 */
                $directory = storage_path(
                    'app/public/avatars'
                );

                if (! is_dir($directory)) {
                    mkdir(
                        $directory,
                        0755,
                        true
                    );
                }

                /*
                 * ----------------------------------------------------------------------
                 * Guardar físicamente
                 * ----------------------------------------------------------------------
                 */
                $avatar->move(
                    $directory,
                    $filename
                );

                /*
                 * ----------------------------------------------------------------------
                 * Guardar ruta en BD
                 * ----------------------------------------------------------------------
                 */
                $profile->avatar =
                    'avatars/'.$filename;
            }
        }

        $profile->save();

        /*
         * --------------------------------------------------------------------------
         * Mensaje
         * --------------------------------------------------------------------------
         */
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Profile updated.'),
        ]);

        return to_route('profile.edit');
    }

    /**
     * Eliminar la cuenta.
     */
    public function destroy(
        ProfileDeleteRequest $request
    ): RedirectResponse {
        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
