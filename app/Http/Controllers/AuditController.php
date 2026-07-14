<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\SystemAuditLog;

class AuditController extends Controller
{
    /**
     * Muestra la vista del Centro de Auditoría
     */
    public function index()
    {
        return Inertia::render('Auditoria');
    }

    /**
     * Retorna los logs paginados para la tabla
     */
    public function getLogs(Request $request)
    {
        $query = SystemAuditLog::query()->orderBy('created_at', 'desc');

        if ($request->has('module') && $request->module !== '') {
            $query->where('module', $request->module);
        }

        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('user_name', 'like', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%")
                  ->orWhere('motive', 'like', "%{$search}%")
                  ->orWhere('auditable_id', 'like', "%{$search}%");
            });
        }

        $logs = $query->paginate(20);

        return response()->json($logs);
    }
}
