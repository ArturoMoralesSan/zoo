<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Level;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /**
     * Mostrar el perfil del usuario autenticado.
     */
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->load([
            'level',
            'profile',
        ]);

        return response()->json([
            'success' => true,
            'user' => $this->userData($user),
        ]);
    }

    /**
     * Actualizar los datos personales del usuario autenticado.
     *
     * Este método NO actualiza el avatar.
     */
    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')
                    ->ignore($user->id),
            ],
            'phone' => [
                'nullable',
                'string',
                'max:30',
            ],
            'birth_date' => [
                'nullable',
                'date',
            ],
            'city' => [
                'nullable',
                'string',
                'max:255',
            ],
            'country' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        /**
         * -------------------------------------------------------------
         * Actualizar usuario
         * -------------------------------------------------------------
         */

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        /**
         * Si cambia el correo, vuelve a requerir verificación.
         */
        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        /**
         * -------------------------------------------------------------
         * Crear perfil si no existe
         * -------------------------------------------------------------
         */

        $profile = $user->profile()->firstOrCreate([
            'user_id' => $user->id,
        ]);

        /**
         * -------------------------------------------------------------
         * Actualizar datos del perfil
         * -------------------------------------------------------------
         */

        $profile->fill([
            'phone' => $validated['phone'] ?? null,
            'birth_date' => $validated['birth_date'] ?? null,
            'city' => $validated['city'] ?? null,
            'country' => $validated['country'] ?? null,
        ]);

        $profile->save();

        /**
         * -------------------------------------------------------------
         * Recargar relaciones
         * -------------------------------------------------------------
         */

        $user->load([
            'level',
            'profile',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Perfil actualizado correctamente.',
            'user' => $this->userData($user),
        ]);
    }

    /**
     * Actualizar únicamente la foto de perfil.
     *
     * La fotografía se procesa de forma independiente
     * al resto de los datos personales.
     */
    public function updateAvatar(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'avatar' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
        ]);

        /**
         * -------------------------------------------------------------
         * Crear perfil si no existe
         * -------------------------------------------------------------
         */

        $profile = $user->profile()->firstOrCreate([
            'user_id' => $user->id,
        ]);

        /**
         * -------------------------------------------------------------
         * Eliminar avatar anterior
         * -------------------------------------------------------------
         */

        if ($profile->avatar) {
            Storage::disk('public')->delete(
                $profile->avatar
            );
        }

        /**
         * -------------------------------------------------------------
         * Generar nombre único
         * -------------------------------------------------------------
         */

        $avatar = $validated['avatar'];

        $filename =
            uniqid().
            '.'.
            $avatar->getClientOriginalExtension();

        /**
         * -------------------------------------------------------------
         * Asegurar que exista el directorio
         * -------------------------------------------------------------
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

        /**
         * -------------------------------------------------------------
         * Guardar físicamente
         * -------------------------------------------------------------
         */

        $avatar->move(
            $directory,
            $filename
        );

        /**
         * -------------------------------------------------------------
         * Guardar ruta en BD
         * -------------------------------------------------------------
         */

        $profile->avatar =
            'avatars/'.$filename;

        $profile->save();

        /**
         * -------------------------------------------------------------
         * Recargar perfil
         * -------------------------------------------------------------
         */

        $user->load([
            'level',
            'profile',
        ]);

        /**
         * -------------------------------------------------------------
         * Respuesta
         * -------------------------------------------------------------
         */

        return response()->json([
            'success' => true,
            'message' => 'Foto de perfil actualizada correctamente.',
            'avatar' => $profile->avatar,
            'user' => $this->userData($user),
        ]);
    }

    /**
     * Construir la respuesta completa del usuario.
     */
    private function userData(User $user): array
    {
        $points = (int) ($user->points ?? 0);

        /**
         * -------------------------------------------------------------
         * Nivel actual
         * -------------------------------------------------------------
         */

        $currentLevel = $user->level;

        /**
         * -------------------------------------------------------------
         * Siguiente nivel
         * -------------------------------------------------------------
         */

        $nextLevel = Level::query()
            ->where('min_points', '>', $points)
            ->orderBy('min_points')
            ->first();

        /**
         * -------------------------------------------------------------
         * Progreso
         * -------------------------------------------------------------
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
            $progress = 100;
            $pointsToNextLevel = 0;
        }

        return [
            /**
             * ---------------------------------------------------------
             * Usuario
             * ---------------------------------------------------------
             */

            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at,
            'qr_token' => $user->qr_token,
            'points' => $points,
            'level_id' => $user->level_id,

            /**
             * ---------------------------------------------------------
             * Nivel
             * ---------------------------------------------------------
             */

            'level' => $currentLevel
                ? [
                    'id' => $currentLevel->id,
                    'name' => $currentLevel->name,
                    'min_points' => (int) $currentLevel->min_points,
                    'max_points' => $currentLevel->max_points !== null
                        ? (int) $currentLevel->max_points
                        : null,
                    'description' => $currentLevel->description,
                ]
                : null,

            /**
             * ---------------------------------------------------------
             * Perfil
             * ---------------------------------------------------------
             */

            'profile' => $user->profile
                ? [
                    'phone' => $user->profile->phone,
                    'birth_date' => $user->profile->birth_date
                        ? $user->profile->birth_date->format('Y-m-d')
                        : null,
                    'avatar' => $user->profile->avatar,
                    'city' => $user->profile->city,
                    'country' => $user->profile->country,
                ]
                : null,

            /**
             * ---------------------------------------------------------
             * Gamificación
             * ---------------------------------------------------------
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
        ];
    }
}