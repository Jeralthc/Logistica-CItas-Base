<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ErpApiMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = env('ERP_API_TOKEN');

        if (!$expectedToken) {
            return response()->json(['error' => 'ERP_API_TOKEN no configurado en el servidor.'], 500);
        }

        $providedToken = $request->header('X-ERP-API-TOKEN') ?? $request->bearerToken();

        if ($providedToken !== $expectedToken) {
            return response()->json(['error' => 'No autorizado. Token de API inválido.'], 401);
        }

        return $next($request);
    }
}
