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

/*
|--------------------------------------------------------------------------
| Rutas Oficiales del Sistema Empresa Base
|--------------------------------------------------------------------------
*/

Route::middleware('web')->group(function () {
    // ---------------------------------------------------------
    // RUTAS PÚBLICAS (Requeridas para el flujo sin sesión del proveedor)
    // ---------------------------------------------------------
    Route::get('/orden-completa/{orden}', [LogisticaController::class, 'buscarOrdenCompleta']);
    Route::get('/citas/slots', [CitaController::class, 'slotsDisponibles']);
    Route::post('/citas/reservar', [CitaController::class, 'reservar']);
    Route::post('/citas/registrar-proveedor', [CitaController::class, 'registrarProveedor']);
    
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
        });

        // ---- SOLO RECEPTOR Y ADMIN ----
        Route::middleware('role:receptor,admin')->group(function () {
            // Logística y ODC
            Route::post('/orden-recalcular/{orden}', [LogisticaController::class, 'recalcularTiempo']);
            Route::get('/orden-detalles/{orden}', [LogisticaController::class, 'buscarOrden']);
            Route::post('/odc/habilitar', [CitaController::class, 'habilitarOdc']);
            Route::post('/calcular-duracion', [CitaController::class, 'calcularDuracionApi']);
            
            // Gestión de Citas (Dashboard interno)
            Route::get('/citas', [CitaController::class, 'listar']);
            Route::get('/citas/detalle/{numero_oc}', [CitaController::class, 'detallePorOdc']);
            Route::post('/citas/{id}/reprogramar', [CitaController::class, 'reprogramar']);
            Route::post('/citas/{id}/cancelar', [CitaController::class, 'cancelar']);
            Route::post('/citas/{id}/finalizar', [CitaController::class, 'finalizar']);

            // Operarios
            Route::get('/operarios', [OperarioController::class, 'index']);
            Route::post('/operarios', [OperarioController::class, 'store']);
            Route::put('/operarios/{operario}', [OperarioController::class, 'update']);
            Route::post('/operarios/{operario}/toggle', [OperarioController::class, 'toggleDisponible']);
            Route::delete('/operarios/{operario}', [OperarioController::class, 'destroy']);
        });

        // ---- SOLO ADMIN ----
        Route::middleware('role:admin')->group(function () {
            // Configuración ERP
            Route::get('/configuracion-erp', [\App\Http\Controllers\ConfigController::class, 'getErpConfig']);
            Route::post('/configuracion-erp', [\App\Http\Controllers\ConfigController::class, 'updateErpConfig']);

            // Configuración de Categorías
            Route::get('/categorias-rendimiento', [\App\Http\Controllers\CategoriaRendimientoController::class, 'index']);
            Route::put('/categorias-rendimiento/{id}', [\App\Http\Controllers\CategoriaRendimientoController::class, 'update']);

            // Auditoría Global
            Route::get('/auditoria/logs', [AuditController::class, 'getLogs']);
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