<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),

            'profile' => $user->profile
                ? [
                    'phone' => $user->profile->phone,
                    'birth_date' => $user->profile->birth_date?->format('Y-m-d'),
                    'avatar' => $user->profile->avatar,
                    'city' => $user->profile->city,
                    'country' => $user->profile->country,
                ]
                : null,
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validated();

        /*
        |--------------------------------------------------------------------------
        | Actualizar usuario
        |--------------------------------------------------------------------------
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
        |--------------------------------------------------------------------------
        | Crear perfil si no existe
        |--------------------------------------------------------------------------
        */

        $profile = $user->profile()->firstOrCreate([
            'user_id' => $user->id,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Actualizar datos del perfil
        |--------------------------------------------------------------------------
        */

        $profile->fill([
            'phone' => $data['phone'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
            'city' => $data['city'] ?? null,
            'country' => $data['country'] ?? null,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Avatar
        |--------------------------------------------------------------------------
        */

        if ($request->hasFile('avatar')) {
            $avatar = $request->file('avatar');

            if ($avatar && $avatar->isValid()) {

                // Eliminar avatar anterior
                if ($profile->avatar) {
                    Storage::disk('public')->delete($profile->avatar);
                }

                // Generar nombre único
                $filename = uniqid() . '.' . $avatar->getClientOriginalExtension();

                // Guardar físicamente el archivo
                $avatar->move(
                    storage_path('app/public/avatars'),
                    $filename
                );

                // Guardar ruta en BD
                $profile->avatar = 'avatars/' . $filename;
            }
        }

        $profile->save();

        /*
        |--------------------------------------------------------------------------
        | Mensaje
        |--------------------------------------------------------------------------
        */

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Profile updated.'),
        ]);

        return to_route('profile.edit');
    }

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