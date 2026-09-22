<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Carbon\Carbon;

class ErpUniversalController extends Controller
{
    /**
     * Pantalla de administración de Conectores ERP y API Keys
     */
    public function index()
    {
        $configuraciones = Schema::hasTable('erp_configurations')
            ? DB::table('erp_configurations')->get()
            : [];

        $apiKeys = Schema::hasTable('erp_api_keys')
            ? DB::table('erp_api_keys')->orderBy('created_at', 'desc')->get()
            : [];

        $totalSincronizadas = DB::table('erp_ordenes_sync')->count();

        return Inertia::render('ConectoresERP', [
            'configuraciones' => $configuraciones,
            'apiKeys' => $apiKeys,
            'totalSincronizadas' => $totalSincronizadas,
        ]);
    }

    /**
     * Genera una nueva API Key para conectar SAP, Odoo, Dynamics o scripts externos
     */
    public function generarApiKey(Request $request)
    {
        $request->validate([
            'nombre_cliente' => 'required|string|max:150',
        ]);

        $key = 'citsur_' . Str::random(40);

        DB::table('erp_api_keys')->insert([
            'nombre_cliente' => $request->get('nombre_cliente'),
            'api_key' => $key,
            'activo' => true,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'API Key generada exitosamente.',
            'api_key' => $key,
        ]);
    }

    /**
     * Elimina o revoca una API Key
     */
    public function eliminarApiKey($id)
    {
        DB::table('erp_api_keys')->where('id', $id)->delete();
        return response()->json(['status' => 'success', 'message' => 'API Key revocada.']);
    }

    /**
     * API REST Abierta: Ingestión de Órdenes de Compra desde cualquier ERP externo
     * Endpoint: POST /api/v1/erp/ordenes
     */
    public function apiIngestarOdc(Request $request)
    {
        // 1. Validar autenticación por API Key en header
        $apiKey = $request->header('X-ERP-API-KEY');
        if (!$apiKey) {
            return response()->json(['error' => 'Header X-ERP-API-KEY no proporcionado.'], 401);
        }

        $valida = DB::table('erp_api_keys')
            ->where('api_key', $apiKey)
            ->where('activo', true)
            ->first();

        if (!$valida) {
            return response()->json(['error' => 'API Key inválida o inactiva.'], 403);
        }

        // Registrar último uso
        DB::table('erp_api_keys')->where('id', $valida->id)->update([
            'ultimo_uso' => Carbon::now()
        ]);

        // 2. Procesar payload de órdenes
        $ordenes = $request->get('ordenes');
        if (!is_array($ordenes) || empty($ordenes)) {
            return response()->json(['error' => 'El cuerpo de la solicitud debe contener un array "ordenes".'], 422);
        }

        $procesadas = 0;
        $errores = [];

        foreach ($ordenes as $idx => $odc) {
            $numOdc = trim($odc['numero_oc'] ?? $odc['numero_odc'] ?? '');
            $proveedor = trim($odc['proveedor'] ?? '');
            $rif = trim($odc['rif_proveedor'] ?? $odc['rif'] ?? '');

            if (!$numOdc || !$proveedor) {
                $errores[] = "Fila #$idx omitida: 'numero_oc' y 'proveedor' son obligatorios.";
                continue;
            }

            $fechaEmision = isset($odc['fecha_emision']) ? Carbon::parse($odc['fecha_emision']) : Carbon::now();
            $monto = floatval($odc['monto_total'] ?? $odc['monto'] ?? 0);
            $tipo = $odc['tipo_mercancia'] ?? 'secos';
            $articulos = isset($odc['articulos']) ? json_encode($odc['articulos']) : json_encode([]);

            // Insertar o actualizar en erp_ordenes_sync
            DB::table('erp_ordenes_sync')->updateOrInsert(
                ['numero_oc' => $numOdc],
                [
                    'proveedor' => $proveedor,
                    'rif_proveedor' => $rif ?: null,
                    'fecha_emision' => $fechaEmision,
                    'monto_total' => $monto,
                    'tipo_mercancia' => $tipo,
                    'articulos_json' => $articulos,
                    'estatus' => 'pendiente',
                    'updated_at' => Carbon::now(),
                ]
            );

            $procesadas++;
        }

        return response()->json([
            'status' => 'success',
            'message' => "Se procesaron $procesadas órdenes de compra exitosamente.",
            'procesadas' => $procesadas,
            'errores' => $errores
        ]);
    }

    /**
     * Importador Masivo por archivo Excel o CSV (Drag & Drop)
     */
    public function importarExcel(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|max:10240' // máx 10MB
        ]);

        $file = $request->file('archivo');
        $ext = strtolower($file->getClientOriginalExtension());

        if (!in_array($ext, ['csv', 'txt'])) {
            return response()->json(['error' => 'Por favor sube un archivo CSV o TXT delimitado por comas o punto y coma.'], 422);
        }

        $handle = fopen($file->getRealPath(), 'r');
        if (!$handle) {
            return response()->json(['error' => 'No se pudo leer el archivo cargado.'], 500);
        }

        $delimitador = ',';
        $primeraLinea = fgets($handle);
        if (strpos($primeraLinea, ';') !== false) {
            $delimitador = ';';
        }
        rewind($handle);

        $cabeceras = fgetcsv($handle, 0, $delimitador);
        if (!$cabeceras) {
            fclose($handle);
            return response()->json(['error' => 'El archivo está vacío o no tiene encabezados válidos.'], 422);
        }

        $map = [];
        foreach ($cabeceras as $i => $col) {
            $colNorm = strtolower(trim(str_replace([' ', '_', '-'], '', $col)));
            if (in_array($colNorm, ['numerooc', 'numeroodc', 'ordendecompra', 'odc', 'docnum', 'documento'])) $map['oc'] = $i;
            if (in_array($colNorm, ['proveedor', 'nombreproveedor', 'empresa', 'nomprov'])) $map['proveedor'] = $i;
            if (in_array($colNorm, ['rif', 'rifproveedor', 'cuit', 'rut', 'nit'])) $map['rif'] = $i;
            if (in_array($colNorm, ['monto', 'montototal', 'total'])) $map['monto'] = $i;
            if (in_array($colNorm, ['tipo', 'tipomercancia', 'categoria'])) $map['tipo'] = $i;
            if (in_array($colNorm, ['fecha', 'fechaemision'])) $map['fecha'] = $i;
        }

        if (!isset($map['oc']) || !isset($map['proveedor'])) {
            fclose($handle);
            return response()->json([
                'error' => 'No se identificaron las columnas mínimas ("numero_oc" y "proveedor"). Encabezados encontrados: ' . implode(', ', $cabeceras)
            ], 422);
        }

        $insertadas = 0;
        $errores = [];
        $fila = 1;

        while (($datos = fgetcsv($handle, 0, $delimitador)) !== false) {
            $fila++;
            $numOc = trim($datos[$map['oc']] ?? '');
            $prov = trim($datos[$map['proveedor']] ?? '');

            if (!$numOc || !$prov) continue;

            $rif = isset($map['rif']) ? trim($datos[$map['rif']] ?? '') : null;
            $monto = isset($map['monto']) ? floatval(str_replace(['$', ' ', ','], ['', '', '.'], $datos[$map['monto']] ?? 0)) : 0;
            $tipo = isset($map['tipo']) ? trim($datos[$map['tipo']] ?? 'secos') : 'secos';
            $fecha = isset($map['fecha']) && !empty($datos[$map['fecha']]) ? Carbon::parse($datos[$map['fecha']]) : Carbon::now();

            DB::table('erp_ordenes_sync')->updateOrInsert(
                ['numero_oc' => $numOc],
                [
                    'proveedor' => $prov,
                    'rif_proveedor' => $rif,
                    'fecha_emision' => $fecha,
                    'monto_total' => $monto,
                    'tipo_mercancia' => $tipo,
                    'articulos_json' => json_encode([]),
                    'estatus' => 'pendiente',
                    'updated_at' => Carbon::now(),
                ]
            );

            $insertadas++;
        }

        fclose($handle);

        return response()->json([
            'status' => 'success',
            'message' => "Se importaron $insertadas órdenes de compra con éxito.",
            'insertadas' => $insertadas
        ]);
    }

    /**
     * Descarga plantilla modelo para importación rápida
     */
    public function descargarPlantilla()
    {
        $csv = "numero_oc,proveedor,rif_proveedor,monto_total,tipo_mercancia,fecha_emision\n";
        $csv .= "ODC-50001,Distribuidora Los Andes C.A.,J-12345678-9,1500.50,viveres,2026-09-22\n";
        $csv .= "ODC-50002,Alimentos Polar C.A.,J-00000001-0,3200.00,viveres,2026-09-22\n";
        $csv .= "ODC-50003,Laboratorios Farmacéuticos Unidos,J-98765432-1,850.75,medicamentos,2026-09-22\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="plantilla_importacion_odc.csv"',
        ]);
    }
}