<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Notificacion;

class NotificacionController extends Controller
{
    /**
     * Listar notificaciones no leídas (para el panel de notificaciones).
     */
    public function index()
    {
        $user = auth('web')->user();
        
        $query = Notificacion::orderBy('created_at', 'desc')->limit(50);
        
        if ($user && $user->role === 'proveedor') {
            // Proveedor solo ve notificaciones dirigidas a él
            $query->where('target_user_id', $user->id);
        } elseif ($user && in_array($user->role, ['admin', 'receptor'])) {
            // Admin/receptor ven notificaciones sin target (globales) O dirigidas a ellos
            $query->where(function ($q) use ($user) {
                $q->whereNull('target_user_id')
                  ->orWhere('target_user_id', $user->id);
            });
        }

        $notificaciones = $query->get();

        $totalNoLeidasQuery = Notificacion::where('leida', false);
        if ($user && $user->role === 'proveedor') {
            $totalNoLeidasQuery->where('target_user_id', $user->id);
        } elseif ($user && in_array($user->role, ['admin', 'receptor'])) {
            $totalNoLeidasQuery->where(function ($q) use ($user) {
                $q->whereNull('target_user_id')
                  ->orWhere('target_user_id', $user->id);
            });
        }
        $totalNoLeidas = $totalNoLeidasQuery->count();

        return response()->json([
            'notificaciones' => $notificaciones,
            'total_no_leidas' => $totalNoLeidas,
        ]);
    }

    /**
     * Marcar una notificación como leída.
     */
    public function marcarLeida(Notificacion $notificacion)
    {
        $notificacion->update(['leida' => true]);

        return response()->json(['message' => 'Notificación marcada como leída.']);
    }

    /**
     * Marcar todas las notificaciones como leídas.
     */
    public function marcarTodasLeidas()
    {
        Notificacion::where('leida', false)->update(['leida' => true]);

        return response()->json(['message' => 'Todas las notificaciones marcadas como leídas.']);
    }

    /**
     * Sincronizar ODCs nuevas (ahora maneja citas locales, no hace polling al ERP).
     */
    public function sincronizar()
    {
        try {
            $totalNoLeidas = Notificacion::where('leida', false)->count();
            
            return response()->json([
                'message' => 'Sincronizado correctamente.',
                'nuevas' => 0, // Ya no se insertan en lote desde aquí
                'total_no_leidas' => $totalNoLeidas,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al sincronizar: ' . $e->getMessage()], 500);
        }
    }
}
