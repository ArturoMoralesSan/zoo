<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Saca del panel a los usuarios de la app. El login con contraseña ya los
 * rechaza, pero las passkeys y las sesiones abiertas antes no pasan por ahí.
 */
class EnsurePanelAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->isAppOnly()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Esta cuenta es de la app y no tiene acceso al panel.',
            ]);
        }

        return $next($request);
    }
}
