<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\SystemAuditLog;
use App\Models\EmailLog;

class MonitoringController extends Controller
{
    /**
     * Muestra la vista unificada del Centro de Monitoreo & Auditoría
     */
    public function index()
    {
        return Inertia::render('Monitoreo');
    }

    /**
     * Determina si el entorno actual debe operar contra la API / erp_ordenes_sync
     */
    private function esModoApi()
    {
        $host = request()->getHost();
        if (str_contains($host, 'citsur.suraki.net') || str_contains($host, 'suraki.net')) {
            return true;
        }

        if (!extension_loaded('pdo_sqlsrv') && !extension_loaded('sqlsrv')) {
            return true;
        }

        $mode = config('app.erp_connection_mode') ?: env('ERP_CONNECTION_MODE', 'api');
        return $mode === 'api';
    }

    /**
     * Retorna indicadores de salud del sistema y semáforos en tiempo real
     */
    public function getHealthStats()
    {
        $startErp = microtime(true);
        $erpOnline = false;
        $erpLatencyMs = 0;
        
        // 1. Probar conexión API ERP o SQL Server Directo
        if ($this->esModoApi()) {
            try {
                $apiUrl = config('app.erp_api_url') ?: env('ERP_API_URL', 'https://citsur.suraki.net/api');
                $token = config('app.erp_api_token') ?: env('ERP_API_TOKEN', 'SurakiSecreto2026');
                $res = \Illuminate\Support\Facades\Http::withToken($token)
                    ->withoutVerifying()
                    ->timeout(3)
                    ->get("{$apiUrl}/erp/ordenes-pendientes");
                $erpLatencyMs = round((microtime(true) - $startErp) * 1000);
                $erpOnline = $res->successful();
            } catch (\Throwable $e) {
                $erpLatencyMs = round((microtime(true) - $startErp) * 1000);
                $erpOnline = false;
            }
        } else {
            try {
                DB::connection('sqlsrv')->getPdo();
                $erpLatencyMs = round((microtime(true) - $startErp) * 1000);
                $erpOnline = true;
            } catch (\Throwable $e) {
                $erpLatencyMs = round((microtime(true) - $startErp) * 1000);
                $erpOnline = false;
            }
        }

        // 2. Conexión DB Local (MySQL)
        $dbOnline = false;
        try {
            DB::connection()->getPdo();
            $dbOnline = true;
        } catch (\Exception $e) {}

        // 3. Conteo de Órdenes Huérfanas (Habilitadas pero sin RIF)
        $huerfanasCount = DB::table('erp_ordenes_sync')
            ->where('estatus_habilitacion', 'habilitada')
            ->where(function($q) {
                $q->whereNull('rif_proveedor')
                  ->orWhere('rif_proveedor', '');
            })
            ->count();

        // 4. Correos Fallidos en las últimas 24 horas
        $correosFallidos24h = EmailLog::where('estatus', 'error')
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        // 5. Total de Órdenes Habilitadas Activas
        $totalHabilitadas = DB::table('erp_ordenes_sync')
            ->where('estatus_habilitacion', 'habilitada')
            ->count();

        // 6. Total de Suscripciones Push Activas
        $pushSubscriptionsCount = DB::table('push_subscriptions')->count();

        // 7. Conteo de Registros en Bitácoras (para el monitor de almacenamiento)
        $totalAuditLogs = SystemAuditLog::count();
        $totalEmailLogs = EmailLog::count();

        return response()->json([
            'erp_online' => $erpOnline,
            'erp_latency_ms' => $erpLatencyMs,
            'db_online' => $dbOnline,
            'huerfanas_count' => $huerfanasCount,
            'correos_fallidos_24h' => $correosFallidos24h,
            'total_habilitadas' => $totalHabilitadas,
            'push_subscriptions' => $pushSubscriptionsCount,
            'total_audit_logs' => $totalAuditLogs,
            'total_email_logs' => $totalEmailLogs,
        ]);
    }

    /**
     * Búsqueda Forense 360° para una ODC específica (Nivel de detalle profundo)
     */
    public function forensicSearch($numero_oc)
    {
        $numLimpio = preg_replace('/^E/i', '', trim($numero_oc));
        $numPad = str_pad($numLimpio, 9, '0', STR_PAD_LEFT);
        $numConE = 'E' . $numPad;

        // 1. Buscar en erp_ordenes_sync (Local)
        $syncRow = DB::table('erp_ordenes_sync')
            ->whereIn('numero_oc', [$numero_oc, $numLimpio, $numPad, $numConE])
            ->first();

        // 2. Comprador que la habilitó
        $compradorInfo = null;
        if ($syncRow && $syncRow->habilitada_por_user_id) {
            $userComp = DB::table('users')->where('id', $syncRow->habilitada_por_user_id)->first();
            if ($userComp) {
                $compradorInfo = [
                    'id' => $userComp->id,
                    'nombre' => $userComp->name,
                    'email' => $userComp->email,
                    'fecha_habilitacion' => isset($syncRow->updated_at) ? $syncRow->updated_at : (isset($syncRow->created_at) ? $syncRow->created_at : null)
                ];
            }
        }

        // 3. RIF y Datos del Proveedor
        $rifTarget = null;
        if ($syncRow) {
            $resumen = json_decode($syncRow->resumen_json, true) ?? [];
            $rifTarget = $syncRow->rif_proveedor ?: ($resumen['Codigo_Proveedor'] ?? $resumen['c_RIF'] ?? null);
        }

        $limpiarRif = function($val) {
            if (!$val) return '';
            $parts = explode('.', $val);
            return strtoupper(preg_replace('/[^A-Z0-9]/i', '', trim($parts[0] ?? '')));
        };
        $rifLimpio = $limpiarRif($rifTarget);

        // 4. Actividad del Proveedor (¿Se conectó? ¿Existe cuenta?)
        $proveedorActividad = [];
        if ($rifLimpio) {
            $usersProv = DB::table('users')
                ->where('role', 'proveedor')
                ->get()
                ->filter(function($u) use ($limpiarRif, $rifLimpio) {
                    return $limpiarRif($u->rif) === $rifLimpio || $limpiarRif($u->username) === $rifLimpio;
                });

            foreach ($usersProv as $uProv) {
                $ultimoLoginLog = DB::table('system_audit_logs')
                    ->where('user_id', $uProv->id)
                    ->orderBy('created_at', 'desc')
                    ->first();

                $proveedorActividad[] = [
                    'id' => $uProv->id,
                    'nombre' => $uProv->name,
                    'username' => $uProv->username,
                    'email' => $uProv->email,
                    'ultimo_login' => $ultimoLoginLog ? $ultimoLoginLog->created_at : null,
                    'se_conecto_recientemente' => $ultimoLoginLog ? true : false,
                ];
            }

            // Ordenar para mostrar PRIMERO los usuarios que SÍ se han conectado (activos en verde arriba)
            usort($proveedorActividad, function($a, $b) {
                if ($a['se_conecto_recientemente'] !== $b['se_conecto_recientemente']) {
                    return $b['se_conecto_recientemente'] <=> $a['se_conecto_recientemente'];
                }
                return strcmp($b['ultimo_login'] ?? '', $a['ultimo_login'] ?? '');
            });
        }

        // 5. Diagnóstico de Visibilidad en el Panel del Proveedor
        $citaExistente = DB::table('appointments')
            ->whereIn('numero_oc', [$numero_oc, $numLimpio, $numPad, $numConE])
            ->whereIn('estatus', ['programada', 'en muelle'])
            ->first();

        $diagnosticoVisibilidad = [
            'es_visible' => false,
            'motivo' => ''
        ];

        if (!$syncRow) {
            $diagnosticoVisibilidad['motivo'] = 'No existe la orden en la base de datos local.';
        } elseif ($syncRow->estatus_habilitacion !== 'habilitada') {
            $diagnosticoVisibilidad['motivo'] = 'La orden está en estatus "' . ($syncRow->estatus_habilitacion ?? 'pendiente') . '" (No ha sido habilitada por el comprador).';
        } elseif (empty($rifLimpio)) {
            $diagnosticoVisibilidad['motivo'] = 'La orden no tiene RIF de proveedor vinculado (Huérfana).';
        } elseif ($citaExistente) {
            $diagnosticoVisibilidad['motivo'] = 'La orden ya fue agendada en una cita (Cita ID: ' . $citaExistente->id . ', Fecha: ' . $citaExistente->fecha_cita . '). Por eso pasa de pendiente a Agendada.';
        } elseif (empty($proveedorActividad)) {
            $diagnosticoVisibilidad['motivo'] = 'La orden está habilitada pero NO existe un usuario registrado en el sistema con el RIF ' . $rifTarget . '.';
        } else {
            $diagnosticoVisibilidad['es_visible'] = true;
            $diagnosticoVisibilidad['motivo'] = '✅ La orden ESTÁ VISIBLE actualmente en el panel del proveedor (RIF: ' . $rifTarget . ').';
        }

        // 6. Cita Registrada
        $cita = DB::table('appointments')
            ->whereIn('numero_oc', [$numero_oc, $numLimpio, $numPad, $numConE])
            ->orderBy('created_at', 'desc')
            ->first();

        // 7. Correos Enviados
        $emailLogs = EmailLog::whereIn('numero_oc', [$numero_oc, $numLimpio, $numPad, $numConE])
            ->orderBy('created_at', 'desc')
            ->get();

        // 8. Notificaciones Campanita
        $notificaciones = DB::table('notificaciones')
            ->whereIn('numero_oc', [$numero_oc, $numLimpio, $numPad, $numConE])
            ->orderBy('created_at', 'desc')
            ->get();

        // 9. ERP Direct Query
        $erpDatos = null;
        try {
            if (!$this->esModoApi()) {
                $sql = "SELECT O.c_DOCUMENTO, O.c_CODPROVEEDOR, P.c_descripcio, P.c_rif, O.c_status 
                        FROM MA_ODC O WITH(NOLOCK) 
                        LEFT JOIN MA_PROVEEDORES P WITH(NOLOCK) ON O.c_CODPROVEEDOR = P.c_codproveed 
                        WHERE O.c_DOCUMENTO IN (?, ?, ?)";
                $res = DB::connection('sqlsrv')->select($sql, [$numero_oc, $numLimpio, $numPad]);
                if (!empty($res)) {
                    $erpDatos = $res[0];
                }
            }
        } catch (\Throwable $e) {}

        return response()->json([
            'numero_oc' => $numero_oc,
            'sync_row' => $syncRow,
            'comprador_info' => $compradorInfo,
            'rif_target' => $rifTarget,
            'proveedor_actividad' => $proveedorActividad,
            'diagnostico_visibilidad' => $diagnosticoVisibilidad,
            'cita' => $cita,
            'email_logs' => $emailLogs,
            'notificaciones' => $notificaciones,
            'erp_datos' => $erpDatos,
        ]);
    }

    /**
     * Paginación de la Bitácora Global (Auditoría)
     */
    public function getAuditLogs(Request $request)
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

        $logs = $query->paginate(25);

        return response()->json($logs);
    }

    /**
     * Paginación de la Bitácora de Envíos de Correo
     */
    public function getEmailLogs(Request $request)
    {
        $query = EmailLog::query()->orderBy('created_at', 'desc');

        if ($request->has('estatus') && $request->estatus !== 'todos') {
            $query->where('estatus', $request->estatus);
        }

        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('numero_oc', 'like', "%{$search}%")
                  ->orWhere('proveedor', 'like', "%{$search}%")
                  ->orWhere('email_destino', 'like', "%{$search}%")
                  ->orWhere('vendedor_nombre', 'like', "%{$search}%");
            });
        }

        $logs = $query->paginate(25);

        return response()->json($logs);
    }

    /**
     * Auto-Reparación Inteligente: Escanea y auto-vincula RIFs en ODCs huérfanas
     */
    public function autoRepairOrphans()
    {
        $huerfanas = DB::table('erp_ordenes_sync')
            ->where('estatus_habilitacion', 'habilitada')
            ->where(function($q) {
                $q->whereNull('rif_proveedor')
                  ->orWhere('rif_proveedor', '');
            })
            ->get();

        $reparadas = 0;
        foreach ($huerfanas as $row) {
            $resumen = json_decode($row->resumen_json, true) ?? [];
            $rifEncontrado = $resumen['Codigo_Proveedor'] ?? $resumen['c_RIF'] ?? $resumen['Cod_Proveedor'] ?? null;

            // Si no estaba en el resumen, intentar buscar en ERP por c_DOCUMENTO
            if (!$rifEncontrado) {
                try {
                    $res = DB::connection('sqlsrv')->select("
                        SELECT O.c_CODPROVEEDOR, P.c_rif 
                        FROM MA_ODC O WITH(NOLOCK)
                        LEFT JOIN MA_PROVEEDORES P WITH(NOLOCK) ON O.c_CODPROVEEDOR = P.c_codproveed
                        WHERE O.c_DOCUMENTO = ?
                    ", [$row->numero_oc]);
                    if (!empty($res)) {
                        $rifEncontrado = $res[0]->c_rif ?: $res[0]->c_CODPROVEEDOR;
                    }
                } catch (\Exception $e) {}
            }

            if ($rifEncontrado) {
                $resumen['Codigo_Proveedor'] = $rifEncontrado;
                DB::table('erp_ordenes_sync')
                    ->where('numero_oc', $row->numero_oc)
                    ->update([
                        'rif_proveedor' => $rifEncontrado,
                        'resumen_json' => json_encode($resumen)
                    ]);
                $reparadas++;
            }
        }

        return response()->json([
            'status' => 'Exitoso',
            'mensaje' => "Se analizaron " . count($huerfanas) . " órdenes huérfanas y se vincularon automáticamente {$reparadas} órdenes con su RIF.",
            'reparadas' => $reparadas
        ]);
    }

    /**
     * Vinculación Manual de RIF a una ODC
     */
    public function manualLinkSupplier(Request $request)
    {
        $validated = $request->validate([
            'numero_oc' => 'required|string',
            'rif' => 'required|string'
        ]);

        $numOc = $validated['numero_oc'];
        $rifVal = strtoupper(preg_replace('/[^A-Z0-9]/i', '', trim($validated['rif'])));

        $row = DB::table('erp_ordenes_sync')->where('numero_oc', $numOc)->first();
        if (!$row) {
            return response()->json(['error' => 'La orden no existe en la base local'], 404);
        }

        $resumen = json_decode($row->resumen_json, true) ?? [];
        $resumen['Codigo_Proveedor'] = $rifVal;

        DB::table('erp_ordenes_sync')
            ->where('numero_oc', $numOc)
            ->update([
                'rif_proveedor' => $rifVal,
                'resumen_json' => json_encode($resumen),
                'updated_at' => now()
            ]);

        return response()->json([
            'status' => 'Exitoso',
            'message' => "La orden {$numOc} fue vinculada exitosamente con el RIF {$rifVal}."
        ]);
    }

    /**
     * Optimización de Almacenamiento: Limpia registros más antiguos a 30 días
     */
    public function purgeOldLogs()
    {
        $limitDate = now()->subDays(30);

        $deletedEmailLogs = EmailLog::where('created_at', '<', $limitDate)->delete();
        $deletedAuditLogs = SystemAuditLog::where('created_at', '<', $limitDate)->delete();

        return response()->json([
            'status' => 'Exitoso',
            'message' => "Optimización de almacenamiento completada.",
            'detalles' => [
                'email_logs_eliminados' => $deletedEmailLogs,
                'audit_logs_eliminados' => $deletedAuditLogs,
                'retencion_dias' => 30
            ]
        ]);
    }
}
