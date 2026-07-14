<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified', 'role:receptor,admin,proveedor'])->name('dashboard');

Route::get('/operarios', function () {
    return Inertia::render('Operarios');
})->middleware(['auth', 'verified', 'role:receptor,admin'])->name('operarios');

Route::get('/reservar-cita', function () {
    return Inertia::render('ReservarCita');
})->name('reservar-cita');

Route::get('/monitor-odc', function () {
    return Inertia::render('MonitorOdc');
})->middleware(['auth', 'verified', 'role:receptor,admin'])->name('monitor-odc');

Route::get('/auditoria', [\App\Http\Controllers\AuditController::class, 'index'])
    ->middleware(['auth', 'verified', 'role:admin'])
    ->name('auditoria');

Route::get('/despliegue', function () {
    return Inertia::render('Despliegue');
})->middleware(['auth', 'verified', 'role:admin'])->name('despliegue');

Route::get('/configuracion-erp', function () {
    return Inertia::render('ConfiguracionERP');
})->middleware(['auth', 'verified', 'role:admin'])->name('configuracion-erp');

Route::get('/categorias', function () {
    return Inertia::render('Categorias');
})->middleware(['auth', 'verified', 'role:admin'])->name('categorias');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Rutas de Mantenimiento y Despliegue (Solo admin)
    Route::middleware('role:admin')->group(function () {
        Route::get('/api/mantenimiento/estado', [\App\Http\Controllers\MaintenanceController::class, 'estado']);
        Route::post('/api/mantenimiento/activar', [\App\Http\Controllers\MaintenanceController::class, 'activar']);
        Route::post('/api/mantenimiento/desactivar', [\App\Http\Controllers\MaintenanceController::class, 'desactivar']);

        // Control de Cambios / Despliegue
        Route::get('/api/despliegue/info', [\App\Http\Controllers\DeployController::class, 'info']);
        Route::post('/api/despliegue/ejecutar-migracion', [\App\Http\Controllers\DeployController::class, 'runMigrations']);
        Route::post('/api/despliegue/limpiar-cache', [\App\Http\Controllers\DeployController::class, 'clearCache']);
        Route::post('/api/despliegue/optimizar', [\App\Http\Controllers\DeployController::class, 'optimize']);
        Route::post('/api/despliegue/crear-backup', [\App\Http\Controllers\DeployController::class, 'createBackup']);
        Route::post('/api/despliegue/restaurar-backup', [\App\Http\Controllers\DeployController::class, 'restoreBackup']);
        Route::post('/api/despliegue/subir-zip', [\App\Http\Controllers\DeployController::class, 'uploadZip']);
        Route::get('/api/despliegue/descargar-backup/{filename}', [\App\Http\Controllers\DeployController::class, 'downloadBackup'])->name('despliegue.descargar-backup');
    });
});

Route::get('/instalar-bd', function () {
    try {
        // SEGURIDAD: Verificar si la base de datos ya fue instalada
        if (\Illuminate\Support\Facades\Schema::hasTable('users')) {
            return 'ACCESO DENEGADO';
        }

        \Illuminate\Support\Facades\Artisan::call('migrate', [
            '--force' => true,
            '--seed' => true
        ]);
        return '¡Instalación de Base de Datos completada con éxito! Ya puedes entrar al sistema.';
    } catch (\Exception $e) {
        return 'Error en la instalación: ' . $e->getMessage();
    }
});

require __DIR__.'/auth.php';

Route::get('/post-deploy-fase10', function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'CategoriasRendimientoSeeder', '--force' => true]);
        \Illuminate\Support\Facades\Artisan::call('optimize:clear');
        \Illuminate\Support\Facades\Artisan::call('storage:link');
        return '¡Actualización de Fase 10 (Migraciones, Seeder, Caché y Symlink) completada con éxito! Ya puedes usar el sistema.';
    } catch (\Exception $e) {
        return 'Error en la actualización: ' . $e->getMessage();
    }
});

Route::get('/test-calculo', function () {
    $categorias = \App\Models\CategoriaRendimiento::all();
    if ($categorias->isEmpty()) { return 'No hay categorias en la base de datos.'; }
    
    $html = '<html><head><title>Prueba de Calculadora</title><style>body{font-family:sans-serif;padding:20px;} table{border-collapse:collapse;width:100%;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} th{background-color:#f2f2f2;} .cat-row{background-color:#eef5ff;font-weight:bold;}</style></head><body>';
    $html .= '<h1>Prueba de Calculadora de Tiempo de Descarga</h1>';
    $html .= '<table>';
    $html .= '<tr><th>Categoría</th><th>Peso (Ton)</th><th>Formato</th><th>Tiempo Fijo (min)</th><th>Velocidad (Ton/min)</th><th>Total Calculado (min)</th></tr>';
    
    $casos = [
        ['peso' => 5, 'formato' => 'suelta'],
        ['peso' => 15, 'formato' => 'suelta'],
        ['peso' => 30, 'formato' => 'suelta'],
        ['peso' => 5, 'formato' => 'paletizada'],
        ['peso' => 15, 'formato' => 'paletizada'],
        ['peso' => 30, 'formato' => 'paletizada'],
    ];

    foreach ($categorias as $cat) {
        $html .= '<tr class="cat-row"><td colspan="6">' . $cat->nombre . ' (Tiempo Fijo: ' . $cat->tiempo_fijo . 'm, Vel: ' . $cat->velocidad_descarga . ' Ton/m)</td></tr>';
        foreach ($casos as $caso) {
            $total = \App\Services\AppointmentDurationService::calcular($cat->nombre, $caso['peso'], $caso['formato'], 0);

            $html .= '<tr>';
            $html .= '<td>' . $cat->nombre . '</td>';
            $html .= '<td>' . $caso['peso'] . ' Ton</td>';
            $html .= '<td>' . ucfirst($caso['formato']) . '</td>';
            $html .= '<td>' . $cat->tiempo_fijo . '</td>';
            $html .= '<td>' . $cat->velocidad_descarga . '</td>';
            $html .= '<td><strong>' . $total . ' min</strong></td>';
            $html .= '</tr>';
        }
    }
    $html .= '</table></body></html>';
    return $html;
});

Route::get('/test-odc/{numero_oc}', function ($numero_oc) {
    // Forzar actualización de tiempos fijos en base de datos antes de calcular
    \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'CategoriasRendimientoSeeder', '--force' => true]);

    $logistica = new \App\Http\Controllers\LogisticaController();
    $response = $logistica->buscarOrdenCompleta($numero_oc, false);
    
    if ($response->status() !== 200) {
        return $response;
    }

    $erpInfo = json_decode($response->content(), true);

    $categoriaDetectada = \App\Services\AppointmentDurationService::detectarCategoria($erpInfo);
    $pesoEstimado = \App\Services\AppointmentDurationService::estimarPesoToneladas($erpInfo);

    $duracionSuelta = \App\Services\AppointmentDurationService::calcular($categoriaDetectada, $pesoEstimado, 'suelta');
    $duracionPaletizada = \App\Services\AppointmentDurationService::calcular($categoriaDetectada, $pesoEstimado, 'paletizada');

    return response()->json([
        'ODC' => $numero_oc,
        'Proveedor' => $erpInfo['proveedor'] ?? 'Desconocido',
        'Categoria_Sugerida_Por_Algoritmo' => $categoriaDetectada,
        'Peso_Estimado_Por_Algoritmo_Ton' => $pesoEstimado,
        'Duracion_Calculada_Suelta_min' => $duracionSuelta,
        'Duracion_Calculada_Paletizada_min' => $duracionPaletizada,
        'Resumen_Devuelto_Por_ERP' => $erpInfo,
    ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
});

// Ruta pública para restablecer el sistema completo desde el navegador
Route::get('/reset-sistema', function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('app:reset-sistema');
        $output = \Illuminate\Support\Facades\Artisan::output();
        return response()->json([
            'success' => true,
            'message' => '¡Sistema restablecido con éxito!',
            'details' => array_filter(explode("\n", trim($output)))
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});
