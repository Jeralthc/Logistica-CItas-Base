<?php

/**
 * ACTUALIZADOR TOTAL PARA SERVIDOR WEB DE PRODUCCIÓN
 * Ruta de acceso web: https://citsur.suraki.net/actualizar_servidor.php
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

$root = realpath(__DIR__ . '/..');

echo "<h1>🚀 Actualización y Reparación en Servidor Web (citsur.suraki.net)</h1>";
echo "<pre>\n";
echo "Carpeta raíz: {$root}\n";
echo "Fecha/Hora actual: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Ejecutar Git Pull si git está disponible
if (function_exists('exec')) {
    echo "--- 1. EJECUTANDO GIT PULL EN EL SERVIDOR ---\n";
    $output = [];
    $ret = -1;
    @exec("cd " . escapeshellarg($root) . " && git pull origin main 2>&1", $output, $ret);
    if (!empty($output)) {
        echo implode("\n", $output) . "\n\n";
    } else {
        echo "Git exec no devolvió salida directa o git no está configurado en PATH.\n\n";
    }
}

// 2. Parche directo en LogisticaController.php
echo "--- 2. VERIFICANDO LOGISTICACONTROLLER.PHP ---\n";
$logisticaFile = $root . '/app/Http/Controllers/LogisticaController.php';
if (file_exists($logisticaFile)) {
    $code = file_get_contents($logisticaFile);
    if (!str_contains($code, 'MA_ODC.c_CODCOMPRADOR AS Comprador_Interno')) {
        $code = str_replace(
            "MA_ODC.c_CODPROVEEDOR AS Codigo_Proveedor,\n                    PROV.c_rif AS c_rif,",
            "MA_ODC.c_CODPROVEEDOR AS Codigo_Proveedor,\n                    MA_ODC.c_CODCOMPRADOR AS Comprador_Interno,\n                    PROV.c_rif AS c_rif,",
            $code
        );
        $code = str_replace(
            "GROUP BY MA_ODC.c_DOCUMENTO, MA_ODC.d_FECHA, MA_ODC.d_fecha_recepcion, MA_ODC.c_DESCRIPCION, CAST(MA_ODC.c_OBSERVACION AS VARCHAR(MAX)), MA_ODC.C_DESPACHAR, MA_ODC.c_CODPROVEEDOR, PROV.c_rif, PROV.c_email, PROV.c_telefono",
            "GROUP BY MA_ODC.c_DOCUMENTO, MA_ODC.d_FECHA, MA_ODC.d_fecha_recepcion, MA_ODC.c_DESCRIPCION, CAST(MA_ODC.c_OBSERVACION AS VARCHAR(MAX)), MA_ODC.C_DESPACHAR, MA_ODC.c_CODPROVEEDOR, MA_ODC.c_CODCOMPRADOR, PROV.c_rif, PROV.c_email, PROV.c_telefono",
            $code
        );
        file_put_contents($logisticaFile, $code);
        echo "✅ LogisticaController.php parcheado correctamente con MA_ODC.c_CODCOMPRADOR.\n";
    } else {
        echo "✅ LogisticaController.php ya contiene c_CODCOMPRADOR.\n";
    }
}

// 3. Parche directo en CitaController.php
echo "\n--- 3. VERIFICANDO CITACONTROLLER.PHP ---\n";
$citaFile = $root . '/app/Http/Controllers/CitaController.php';
if (file_exists($citaFile)) {
    $code = file_get_contents($citaFile);
    if (!str_contains($code, 'DANIEL (SURAKARNES)')) {
        $oldSearch = "'166' => 'MARIA JOSE CONTRERAS',";
        $newReplace = "'166' => 'MARIA JOSE CONTRERAS',\n            '228' => 'DANIEL (SURAKARNES)',";
        $code = str_replace($oldSearch, $newReplace, $code);
        
        $oldJeralth = "if (\$nombreOriginal) {\n            \$n = trim(\$nombreOriginal);";
        $newJeralth = "if (\$nombreOriginal) {\n            \$n = trim(\$nombreOriginal);\n            if (preg_match('/Jeralth|Admin/i', \$n)) return 'Comprador ERP';";
        $code = str_replace($oldJeralth, $newJeralth, $code);

        file_put_contents($citaFile, $code);
        echo "✅ CitaController.php parcheado con el nuevo mapeo de compradores.\n";
    } else {
        echo "✅ CitaController.php ya contiene el mapeo de compradores.\n";
    }
}

// 4. Parche directo en SyncController.php
echo "\n--- 4. VERIFICANDO SYNCCONTROLLER.PHP ---\n";
$syncFile = $root . '/app/Http/Controllers/SyncController.php';
if (file_exists($syncFile)) {
    $code = file_get_contents($syncFile);
    if (!str_contains($code, "'resumen_json',")) {
        $code = str_replace(
            "'destino', \n                    'detalles_json',",
            "'destino', \n                    'resumen_json',\n                    'detalles_json',",
            $code
        );
        $code = str_replace(
            "'destino',\n                    'detalles_json',",
            "'destino',\n                    'resumen_json',\n                    'detalles_json',",
            $code
        );
        file_put_contents($syncFile, $code);
        echo "✅ SyncController.php parcheado con resumen_json en upsert.\n";
    } else {
        echo "✅ SyncController.php ya actualizaba resumen_json.\n";
    }
}

// 5. Cargar Framework Laravel
require_once $root . '/vendor/autoload.php';
$app = require_once $root . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n--- 5. ACTUALIZANDO COMPRADORES EN BASE DE DATOS LOCAL WEB ---\n";

$mapBuyers = [];
if (extension_loaded('sqlsrv') || extension_loaded('pdo_sqlsrv')) {
    try {
        $odcsErp = DB::connection('sqlsrv')->select("
            SELECT c_DOCUMENTO, c_CODCOMPRADOR 
            FROM MA_ODC WITH (NOLOCK) 
            WHERE d_FECHA >= '2026-01-01'
        ");
        foreach ($odcsErp as $row) {
            $num = trim($row->c_DOCUMENTO);
            $buyer = trim($row->c_CODCOMPRADOR ?? '');
            if ($num && $buyer !== '') {
                $mapBuyers[$num] = $buyer;
            }
        }
        echo "Obtenidas " . count($mapBuyers) . " órdenes desde SQL Server MA_ODC.\n";
    } catch (\Throwable $eErp) {
        echo "Aviso SQL Server: " . $eErp->getMessage() . "\n";
    }
}

// Mapeos estáticos conocidos para órdenes activas
$knownBuyerMap = [
    '000033078' => '027',
    '33078' => '027',
    '000033107' => '027',
    '33107' => '027',
    '000033123' => '166',
    '33123' => '166',
    '000033122' => '019',
    '33122' => '019',
    '000033113' => '027',
    '33113' => '027',
];

$mapBuyers = array_merge($knownBuyerMap, $mapBuyers);

$rows = DB::table('erp_ordenes_sync')->get();
$updatedCount = 0;

foreach ($rows as $s) {
    $resumen = json_decode($s->resumen_json, true) ?: [];
    $numOc = $s->numero_oc;
    $ordenLimpia = preg_replace('/^E/i', '', $numOc);
    $ordenPad = str_pad($ordenLimpia, 9, '0', STR_PAD_LEFT);

    $erpBuyer = $mapBuyers[$numOc] ?? $mapBuyers[$ordenLimpia] ?? $mapBuyers[$ordenPad] ?? null;

    if ($erpBuyer && (empty($resumen['Comprador_Interno']) || $resumen['Comprador_Interno'] !== $erpBuyer)) {
        $resumen['Comprador_Interno'] = $erpBuyer;
        $resumen['c_CODCOMPRADOR'] = $erpBuyer;

        DB::table('erp_ordenes_sync')->where('numero_oc', $numOc)->update([
            'resumen_json' => json_encode($resumen),
            'updated_at' => now(),
        ]);
        $updatedCount++;
    }
}

echo "✅ Se actualizaron {$updatedCount} registros en erp_ordenes_sync con su comprador real.\n";

// 6. Limpieza de cachés
echo "\n--- 6. LIMPIANDO CACHÉS DE LARAVEL ---\n";
try {
    \Illuminate\Support\Facades\Artisan::call('view:clear');
    \Illuminate\Support\Facades\Artisan::call('route:clear');
    \Illuminate\Support\Facades\Artisan::call('cache:clear');
    echo "✅ Cachés de vistas, rutas y aplicación limpiadas con éxito.\n";
} catch (\Throwable $eCache) {
    echo "Aviso al limpiar caché: " . $eCache->getMessage() . "\n";
}

echo "\n🎉 ¡PROCESO FINALIZADO CON ÉXITO!";
echo "\n</pre>";

