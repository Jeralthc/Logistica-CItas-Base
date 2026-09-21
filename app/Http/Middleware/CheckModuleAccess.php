<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckModuleAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $modulo): Response
    {
        if (Auth::check()) {
            $user = Auth::user();

            if (!$user->tieneModulo($modulo)) {
                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json(['error' => 'No tienes permiso para acceder a este módulo.'], 403);
                }
                abort(403, 'No tienes permiso asignado para acceder al módulo "' . $modulo . '". Contacta a Sistemas.');
            }
        } else {
            // Si no está autenticado y la ruta no es pública, redirigir a login
            if (!$request->is('reservar-cita')) {
                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json(['error' => 'No autenticado.'], 401);
                }
                return redirect('/login');
            }
        }

        return $next($request);
    }
}
