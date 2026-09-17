<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Level;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Registrar un nuevo visitante.
     */
    public function register(Request $request): JsonResponse
    {
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
                'unique:users,email',
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ]);

        $level = Level::where('name', 'Bronce')->first();

        if (!$level) {
            return response()->json([
                'success' => false,
                'message' => 'El nivel Bronce no está configurado.',
            ], 500);
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'level_id' => $level->id,
            'points' => 0,
        ]);

        $user->assignRole('visitor');

        $token = $user->createToken('zooapp')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Usuario registrado correctamente.',
            'token' => $token,
            'user' => $user->load('level'),
        ], 201);
    }

    /**
     * Iniciar sesión.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => [
                'required',
                'email',
            ],
            'password' => [
                'required',
                'string',
            ],
        ]);

        if (!Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => [
                    'Las credenciales proporcionadas no son correctas.',
                ],
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        $token = $user->createToken('zooapp')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Inicio de sesión exitoso.',
            'token' => $token,
            'user' => $user->load('level'),
        ]);
    }

    /**
     * Obtener el usuario autenticado.
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user()->load('level');

        return response()->json([
            'success' => true,
            'user' => $user,
        ]);
    }

    /**
     * Cerrar sesión.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada correctamente.',
        ]);
    }
}