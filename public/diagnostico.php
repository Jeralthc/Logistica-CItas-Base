<?php
// DIAGNÓSTICO Y REPARADOR TOTAL DE PRODUCCIÓN - https://citsur.suraki.net/diagnostico.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$root = realpath(__DIR__ . '/..');
echo "<h1>🔍 Diagnóstico y Reparador Total (Servidor Web)</h1>";
echo "<pre>\n";
echo "Raíz: {$root}\n";
echo "Fecha/Hora: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Escribir directamente SyncController.php actualizado en producción
$syncPath = $root . '/app/Http/Controllers/SyncController.php';
$syncCode = <<<'PHP'
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SyncController extends Controller
{
    public function recibir(Request $request)
    {
        $tokenEsperado = env('ERP_API_TOKEN');
        if ($tokenEsperado && $request->bearerToken() !== $tokenEsperado) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $ordenes = $request->input('ordenes', []);

        try {
            DB::beginTransaction();

            $esPrimerChunk = $request->input('es_primer_chunk', true);

            if ($esPrimerChunk) {
                DB::table('erp_ordenes_sync')->whereNotIn('estatus_habilitacion', ['habilitada', 'agendada'])->delete();
            }

            $insertData = [];
            $now = now();
            
            foreach ($ordenes as $orden) {
                $resumen = $orden['resumen'] ?? [];

                $rawFechaEmision = $orden['fecha_emision'] 
                    ?? $orden['fecha_odc'] 
                    ?? $orden['fecha_orden']
                    ?? $resumen['fecha_emision'] 
                    ?? $resumen['Fecha_Emision'] 
                    ?? $resumen['fecha_orden'] 
                    ?? $resumen['fecha_odc'] 
                    ?? null;

                $cleanFechaEmision = $rawFechaEmision ? substr(trim($rawFechaEmision), 0, 10) : null;

                $rawFechaRecepcion = $orden['fecha_recepcion'] 
                    ?? $resumen['fecha_recepcion'] 
                    ?? $resumen['Fecha_Recepcion'] 
                    ?? null;

                $cleanFechaRecepcion = $rawFechaRecepcion ? substr(trim($rawFechaRecepcion), 0, 10) : null;

                $proveedorVal = $orden['proveedor'] 
                    ?? $resumen['Nombre_Proveedor'] 
                    ?? $resumen['proveedor'] 
                    ?? $resumen['nombre_proveedor'] 
                    ?? null;

                $destinoVal = $orden['destino'] 
                    ?? $resumen['sucursal_nombre'] 
                    ?? $resumen['Muelle_Destino'] 
                    ?? $resumen['destino'] 
                    ?? null;
                
                $insertData[] = [
                    'numero_oc' => $orden['numero_oc'],
                    'fecha_emision' => $cleanFechaEmision,
                    'fecha_recepcion' => $cleanFechaRecepcion,
                    'proveedor' => $proveedorVal,
                    'destino' => $destinoVal,
                    'resumen_json' => json_encode($resumen),
                    'detalles_json' => json_encode($orden['detalles'] ?? []),
                    'categoria_sugerida' => \App\Services\AppointmentDurationService::detectarCategoria($resumen),
                    'peso_estimado_ton' => \App\Services\AppointmentDurationService::estimarPesoToneladas($resumen),
                    'estatus_habilitacion' => 'pendiente',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($insertData, 100) as $chunk) {
                DB::table('erp_ordenes_sync')->upsert($chunk, ['numero_oc'], [
                    'fecha_emision', 
                    'fecha_recepcion', 
                    'proveedor', 
                    'destino', 
                    'resumen_json',
                    'detalles_json',
                    'categoria_sugerida',
                    'peso_estimado_ton',
                    'updated_at'
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'Exitoso',
                'mensaje' => 'Sincronización completada con ' . count($insertData) . ' órdenes',
                'timestamp' => $now->toDateTimeString()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al sincronizar: ' . $e->getMessage()], 500);
        }
    }
}
PHP;

if (@file_put_contents($syncPath, $syncCode)) {
    echo "✅ SyncController.php actualizado exitosamente en producción.\n";
} else {
    echo "⚠️ No se pudo escribir SyncController.php.\n";
}

// 2. Cargar Framework Laravel
require_once $root . '/vendor/autoload.php';
$app = require_once $root . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n--- ACTUALIZACIÓN DIRECTA EN BASE DE DATOS PROD (erp_ordenes_sync) ---\n";

$targetBuyers = [
    '33078' => '027',
    '000033078' => '027',
    '33107' => '027',
    '000033107' => '027',
    '33065' => '027',
    '000033065' => '027',
    '33113' => '027',
    '000033113' => '027',
    '33116' => '019',
    '000033116' => '019',
    '33118' => '019',
    '000033118' => '019',
    '33119' => '166',
    '000033119' => '166',
    '33120' => '166',
    '000033120' => '166',
    '33121' => '166',
    '000033121' => '166',
    '33122' => '019',
    '000033122' => '019',
    '33123' => '166',
    '000033123' => '166',
];

$allSync = DB::table('erp_ordenes_sync')->get();
$fixedCount = 0;

foreach ($allSync as $row) {
    $resumen = json_decode($row->resumen_json, true) ?: [];
    $numOc = trim($row->numero_oc);
    $unpadded = ltrim(preg_replace('/^E/i', '', $numOc), '0');
    $padded = str_pad($unpadded, 9, '0', STR_PAD_LEFT);

    $buyerCode = $targetBuyers[$numOc] ?? $targetBuyers[$unpadded] ?? $targetBuyers[$padded] ?? null;

    if ($buyerCode || empty($resumen['Comprador_Interno'])) {
        $buyerToSet = $buyerCode ?: '027'; // Fallback predeterminado a Karynell (027) para órdenes sin comprador ERP
        if (($resumen['Comprador_Interno'] ?? '') !== $buyerToSet) {
            $resumen['Comprador_Interno'] = $buyerToSet;
            $resumen['c_CODCOMPRADOR'] = $buyerToSet;

            DB::table('erp_ordenes_sync')->where('numero_oc', $row->numero_oc)->update([
                'resumen_json' => json_encode($resumen),
                'updated_at' => now(),
            ]);
            $fixedCount++;
        }
    }
}

echo "✅ Total órdenes corregidas en MySQL producción: {$fixedCount}\n";

// 3. Limpiar cachés
try {
    \Illuminate\Support\Facades\Artisan::call('view:clear');
    \Illuminate\Support\Facades\Artisan::call('route:clear');
    \Illuminate\Support\Facades\Artisan::call('cache:clear');
    echo "✅ Cachés de Laravel limpiadas correctamente.\n";
} catch (\Throwable $eCache) {
    echo "Aviso caché: " . $eCache->getMessage() . "\n";
}

echo "\n🎉 ¡PROCESO DE REPARACIÓN FINALIZADO!";
echo "\n</pre>";

