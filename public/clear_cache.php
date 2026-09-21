<?php
/**
 * Limpiador de cachés de Laravel en servidor sin SSH.
 * ELIMINAR DESPUÉS DE USAR.
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    require __DIR__.'/../vendor/autoload.php';
    $app = require_once __DIR__.'/../bootstrap/app.php';
    
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();

    echo "<h2>Limpiando Cachés de Laravel...</h2><pre>";

    \Illuminate\Support\Facades\Artisan::call('route:clear');
    echo "1. Rutas: " . \Illuminate\Support\Facades\Artisan::output();

    \Illuminate\Support\Facades\Artisan::call('config:clear');
    echo "2. Configuración: " . \Illuminate\Support\Facades\Artisan::output();

    \Illuminate\Support\Facades\Artisan::call('cache:clear');
    echo "3. Caché general: " . \Illuminate\Support\Facades\Artisan::output();

    \Illuminate\Support\Facades\Artisan::call('view:clear');
    echo "4. Vistas: " . \Illuminate\Support\Facades\Artisan::output();

    echo "\n5. Actualizando columnas de fechas y proveedores en erp_ordenes_sync...\n";
    $rows = \Illuminate\Support\Facades\DB::table('erp_ordenes_sync')->get();
    $updatedCount = 0;
    foreach ($rows as $row) {
        $resumen = json_decode($row->resumen_json, true) ?? [];
        
        $rawDate = $row->fecha_emision 
            ?? $resumen['fecha_emision'] 
            ?? $resumen['Fecha_Emision'] 
            ?? $resumen['fecha_orden'] 
            ?? $resumen['fecha_odc'] 
            ?? null;

        $rawProveedor = $row->proveedor
            ?? $resumen['proveedor']
            ?? $resumen['Nombre_Proveedor']
            ?? $resumen['nombre_proveedor']
            ?? null;

        $rawDestino = $row->destino
            ?? $resumen['destino']
            ?? $resumen['sucursal_nombre']
            ?? $resumen['Muelle_Destino']
            ?? null;

        $cleanDate = $rawDate ? substr(trim($rawDate), 0, 10) : null;

        $updates = [];
        if (empty($row->fecha_emision) && $cleanDate) {
            $updates['fecha_emision'] = $cleanDate;
        }
        if (empty($row->proveedor) && $rawProveedor) {
            $updates['proveedor'] = $rawProveedor;
        }
        if (empty($row->destino) && $rawDestino) {
            $updates['destino'] = $rawDestino;
        }

        if (!empty($updates)) {
            \Illuminate\Support\Facades\DB::table('erp_ordenes_sync')->where('numero_oc', $row->numero_oc)->update($updates);
            $updatedCount++;
        }
    }
    echo "   Se actualizaron $updatedCount órdenes con sus fechas y datos correctos.\n";

    echo "\n6. Limpiando archivos de logs, sesiones y zips viejos para liberar espacio de disco...\n";
    $logPath = storage_path('logs/laravel.log');
    if (file_exists($logPath) && filesize($logPath) > 2000000) {
        @file_put_contents($logPath, '');
        echo "   -> Archivo de logs laravel.log vaciado exitosamente.\n";
    }

    $sessionsPath = storage_path('framework/sessions');
    if (is_dir($sessionsPath)) {
        $files = glob($sessionsPath . '/*');
        $deletedSessions = 0;
        foreach ($files as $file) {
            if (is_file($file) && (time() - filemtime($file) > 43200)) {
                @unlink($file);
                $deletedSessions++;
            }
        }
        echo "   -> Eliminados $deletedSessions archivos de sesiones temporales antiguas.\n";
    }

    $rootPath = base_path();
    $zipFiles = glob($rootPath . '/*.zip');
    $deletedZips = 0;
    foreach ($zipFiles as $zipFile) {
        if (is_file($zipFile)) {
            @unlink($zipFile);
            $deletedZips++;
        }
    }
    echo "   -> Eliminados $deletedZips archivos .zip de actualizaciones acumuladas.\n";

    echo "\n✅ ¡TODOS LOS CACHÉS HAN SIDO LIMPIADOS Y EL ESPACIO DE DISCO OPTIMIZADO EXITOSAMENTE!\n";
    echo "</pre>";

} catch (\Throwable $e) {
    echo "❌ Error al limpiar caché: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . " (Línea " . $e->getLine() . ")\n";
}
