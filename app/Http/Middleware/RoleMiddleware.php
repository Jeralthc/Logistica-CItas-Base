<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!Auth::check()) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['error' => 'No autenticado.'], 401);
            }
            return redirect('/login');
        }

        $user = Auth::user();

        // Admin has access to everything
        if ($user->role === 'admin') {
            return $next($request);
        }

        // Check if user has any of the required roles
        if (in_array($user->role, $roles)) {
            return $next($request);
        }

        // If it's an API request, return JSON 403 Forbidden
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['error' => 'No tienes permiso para acceder a este endpoint.'], 403);
        }

        // If not, redirect them to their respective default page
        if ($user->role === 'comprador') {
            return redirect()->route('reservar-cita')->with('error', 'No tienes permiso para acceder a esa área.');
        }

        if ($user->role === 'receptor') {
            return redirect()->route('dashboard')->with('error', 'No tienes permiso para acceder a esa área.');
        }

        if ($user->role === 'proveedor') {
            return redirect()->route('dashboard')->with('error', 'No tienes permiso para acceder a esa área.');
        }

        return redirect('/');
    }
}
