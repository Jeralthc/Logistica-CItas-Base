<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LogisticaController;
use App\Http\Controllers\ErpApiController;
use App\Http\Controllers\OperarioController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\CitaController;
use App\Http\Controllers\AuditController;

/*
|--------------------------------------------------------------------------
| Rutas de Pruebas (Legacy)
|--------------------------------------------------------------------------
*/
Route::get('/prueba-sql/{orden}', function ($orden) {
    try {
        $resultado = DB::connection('sqlsrv')->select("EXEC sp_CalcularVolumenOC ?", [$orden]);
        return response()->json(['status' => 'Exitoso', 'datos' => $resultado]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

Route::get('/debug-habilitar', function () {
    $log = [];
    $admin = \App\Models\User::where('role', 'admin')->first() ?: \App\Models\User::first();
    if ($admin) {
        auth()->login($admin);
        $log['user'] = $admin->name . " (ID: {$admin->id}, Role: {$admin->role})";
    }

    $dummyData = [
        'numero_oc' => 'ODC-TEST-9999',
        'proveedor' => 'PROVEEDOR PRUEBA C.A.',
        'rif' => 'J-12345678-9',
        'email' => 'test_proveedor_debug@mailinator.com',
        'telefono' => '04121234567',
        'asesor' => 'VENDEDOR PRUEBA',
        'contacto_id' => null,
    ];

    $request = \Illuminate\Http\Request::create('/api/odc/habilitar', 'POST', $dummyData);

    try {
        $controller = app()->make(\App\Http\Controllers\CitaController::class);
        $response = $controller->habilitarOdc($request);
        $log['status_code'] = $response->getStatusCode();
        $log['response'] = json_decode($response->getContent(), true) ?: $response->getContent();
    } catch (\Throwable $e) {
        $log['exception'] = [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => explode("\n", $e->getTraceAsString())
        ];
    }

    try {
        DB::table('erp_ordenes_sync')->where('numero_oc', 'ODC-TEST-9999')->delete();
        \App\Models\User::where('email', 'test_proveedor_debug@mailinator.com')->delete();
        \App\Models\Notificacion::where('numero_oc', 'ODC-TEST-9999')->delete();
        \App\Models\EmailLog::where('numero_oc', 'ODC-TEST-9999')->delete();
        $log['cleanup'] = 'OK';
    } catch (\Throwable $e) {
        $log['cleanup_error'] = $e->getMessage();
    }

    return response()->json($log);
});

/*
|--------------------------------------------------------------------------
| Rutas Oficiales del Sistema Suraki
|--------------------------------------------------------------------------
*/

Route::middleware('web')->group(function () {
    // ---------------------------------------------------------
    // RUTAS PÚBLICAS (Requeridas para el flujo sin sesión del proveedor)
    // ---------------------------------------------------------
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('/orden-completa/{orden}', [LogisticaController::class, 'buscarOrdenCompleta']);
        Route::get('/citas/slots', [CitaController::class, 'slotsDisponibles']);
        Route::post('/citas/reservar', [CitaController::class, 'reservar']);
        Route::post('/citas/registrar-proveedor', [CitaController::class, 'registrarProveedor']);
    });
    
    // ---------------------------------------------------------
    // RUTAS PROTEGIDAS (Requieren Autenticación)
    // ---------------------------------------------------------
    Route::middleware('auth')->group(function () {
        
        // Notificaciones (Genérico para usuarios autenticados)
        Route::get('/notificaciones', [NotificacionController::class, 'index']);
        Route::post('/notificaciones/{notificacion}/leer', [NotificacionController::class, 'marcarLeida']);
        Route::post('/notificaciones/leer-todas', [NotificacionController::class, 'marcarTodasLeidas']);
        Route::post('/notificaciones/sincronizar', [NotificacionController::class, 'sincronizar']);

        // ---- SOLO PROVEEDOR ----
        Route::middleware('role:proveedor')->group(function () {
            Route::get('/odc/mis-pendientes', [CitaController::class, 'odcsPendientesProveedor']);
            Route::post('/odc/agendar', [CitaController::class, 'reservarProveedor']);
            Route::post('/citas/{id}/anular-factura', [CitaController::class, 'anularFactura']);
            Route::post('/citas/{id}/actualizar-factura', [CitaController::class, 'actualizarFactura']);
        });

        // Web Push Routes
        Route::post('/push-subscribe', [\App\Http\Controllers\PushSubscriptionController::class, 'subscribe']);
        Route::post('/push-unsubscribe', [\App\Http\Controllers\PushSubscriptionController::class, 'unsubscribe']);

        // Gestión de Citas (Accesible para Proveedor, Comprador, Receptor y Admin)
        Route::get('/citas', [CitaController::class, 'listar']);
        Route::post('/citas/{id}/reprogramar', [CitaController::class, 'reprogramar']);
        Route::post('/citas/{id}/cancelar', [CitaController::class, 'cancelar']);

        // ---- COMPRADOR, RECEPTOR Y ADMIN ----
        Route::middleware('role:comprador,receptor,admin')->group(function () {
            // Logística y ODC
            Route::post('/orden-recalcular/{orden}', [LogisticaController::class, 'recalcularTiempo']);
            Route::get('/orden-detalles/{orden}', [LogisticaController::class, 'buscarOrden']);
            Route::post('/odc/habilitar', [CitaController::class, 'habilitarOdc']);
            Route::post('/calcular-duracion', [CitaController::class, 'calcularDuracionApi']);

            // Verificación de Productos ODC (Recepción)
            Route::get('/odc/{orden}/verificaciones', [LogisticaController::class, 'obtenerVerificacionesProducto']);
            Route::post('/odc/{orden}/verificar-producto', [LogisticaController::class, 'verificarProducto']);
            Route::post('/odc/{orden}/verificar-todos', [LogisticaController::class, 'verificarTodosProductos']);

            // Detalle y Finalización de Citas
            Route::get('/citas/detalle/{numero_oc}', [CitaController::class, 'detallePorOdc']);
            Route::post('/citas/{id}/finalizar', [CitaController::class, 'finalizar']);

            // Monitor ODC (interno, sin token de API)
            Route::get('/monitor-odc/ordenes', [ErpApiController::class, 'getOrdenesPendientes']);

            // Probar envío de notificación Push
            Route::match(['get', 'post'], '/push-test', [CitaController::class, 'probarPushServidor']);
        });

        // ---- SOLO RECEPTOR Y ADMIN ----
        Route::middleware('role:receptor,admin')->group(function () {
            // Operarios
            Route::get('/operarios', [OperarioController::class, 'index']);
            Route::post('/operarios', [OperarioController::class, 'store']);
            Route::put('/operarios/{operario}', [OperarioController::class, 'update']);
            Route::post('/operarios/{operario}/toggle', [OperarioController::class, 'toggleDisponible']);
            Route::delete('/operarios/{operario}', [OperarioController::class, 'destroy']);
        });

        // ---- MONITOREO DE CORREOS Y TRAZABILIDAD (ADMIN Y COMPRADOR) ----
        Route::middleware('role:admin,comprador')->group(function () {
            Route::get('/monitoreo/health', [\App\Http\Controllers\MonitoringController::class, 'getHealthStats']);
            Route::get('/monitoreo/forensic/{numero_oc}', [\App\Http\Controllers\MonitoringController::class, 'forensicSearch']);
            Route::get('/monitoreo/email-logs', [\App\Http\Controllers\MonitoringController::class, 'getEmailLogs']);
        });

        // ---- SOLO ADMIN ----
        Route::middleware('role:admin')->group(function () {
            // Deshabilitar ODC (Resetear a pendiente)
            Route::post('/odc/{numero_oc}/deshabilitar', [CitaController::class, 'deshabilitarOdc']);
            // Notificar masivamente disculpas y habilitación a proveedores
            Route::match(['get', 'post'], '/odc/notificar-habilitadas-hoy', [CitaController::class, 'notificarOdcsHabilitadas']);

            // Configuración ERP
            Route::get('/configuracion-erp', [\App\Http\Controllers\ConfigController::class, 'getErpConfig']);
            Route::post('/configuracion-erp', [\App\Http\Controllers\ConfigController::class, 'updateErpConfig']);

            // Configuración de Categorías
            Route::get('/categorias-rendimiento', [\App\Http\Controllers\CategoriaRendimientoController::class, 'index']);
            Route::put('/categorias-rendimiento/{id}', [\App\Http\Controllers\CategoriaRendimientoController::class, 'update']);

            // Monitoreo & Auditoría Global (Acciones Administrativas)
            Route::get('/auditoria/logs', [\App\Http\Controllers\MonitoringController::class, 'getAuditLogs']);
            Route::get('/monitoreo/audit-logs', [\App\Http\Controllers\MonitoringController::class, 'getAuditLogs']);
            Route::post('/monitoreo/auto-repair', [\App\Http\Controllers\MonitoringController::class, 'autoRepairOrphans']);
            Route::post('/monitoreo/vincular-proveedor', [\App\Http\Controllers\MonitoringController::class, 'manualLinkSupplier']);
            Route::post('/monitoreo/purge-logs', [\App\Http\Controllers\MonitoringController::class, 'purgeOldLogs']);

            // Gestión de Usuarios
            Route::get('/usuarios', [\App\Http\Controllers\UserController::class, 'index']);
            Route::post('/usuarios', [\App\Http\Controllers\UserController::class, 'store']);
            Route::put('/usuarios/{user}', [\App\Http\Controllers\UserController::class, 'update']);
            Route::post('/usuarios/{user}/password', [\App\Http\Controllers\UserController::class, 'updatePassword']);
            Route::post('/usuarios/{user}/toggle-activo', [\App\Http\Controllers\UserController::class, 'toggleActivo']);
        });
    });
});

// Rutas del Agente API (Server-to-Server) - Protegidas por API Token y Rate Limiting
Route::middleware(['throttle:60,1', 'erp.api'])->group(function () {
    Route::prefix('erp')->group(function () {
        Route::get('/ordenes-pendientes', [ErpApiController::class, 'getOrdenesPendientes']);
        Route::get('/buscar-orden/{orden}', [ErpApiController::class, 'getOrden']);
        Route::post('/recalcular-tiempo/{orden}', [ErpApiController::class, 'recalcularTiempo']);
    });

    Route::post('/sync/recibir', [App\Http\Controllers\SyncController::class, 'recibir']);
});

// --- APIS ENTERPRISE LOGÍSTICA BASE ---
use App\Http\Controllers\GaritaController;
use App\Http\Controllers\KpiLogisticaController;
use App\Http\Controllers\ErpUniversalController;
use App\Http\Controllers\EpodController;
use App\Http\Controllers\CompanySettingController;

// Garita & Patio (YMS)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/garita/validar-qr', [GaritaController::class, 'validarQr']);
    Route::post('/garita/citas/{id}/checkin', [GaritaController::class, 'registrarEntrada']);
    Route::post('/garita/citas/{id}/llamar-muelle', [GaritaController::class, 'llamarMuelle']);
    Route::post('/garita/citas/{id}/salida', [GaritaController::class, 'registrarSalida']);

    // Conectores ERP
    Route::post('/conectores-erp/importar-excel', [ErpUniversalController::class, 'importarExcel']);
    Route::post('/conectores-erp/api-keys', [ErpUniversalController::class, 'generarApiKey']);
    Route::delete('/conectores-erp/api-keys/{id}', [ErpUniversalController::class, 'eliminarApiKey']);

    // e-POD (Actas Digitales)
    Route::post('/epod/citas/{id}/guardar', [EpodController::class, 'guardarEpod']);
    Route::get('/epod/citas/{id}', [EpodController::class, 'obtenerEpod']);

    // Configuración Empresa
    Route::post('/configuracion-empresa', [CompanySettingController::class, 'guardarAjustes']);
    Route::post('/configuracion-empresa/almacenes', [CompanySettingController::class, 'crearAlmacen']);
});

// Plantilla CSV pública para importación
Route::get('/conectores-erp/plantilla', [ErpUniversalController::class, 'descargarPlantilla']);

// API REST Ingestión Externa Abierta (Autenticada por X-ERP-API-KEY)
Route::post('/v1/erp/ordenes', [ErpUniversalController::class, 'apiIngestarOdc']);