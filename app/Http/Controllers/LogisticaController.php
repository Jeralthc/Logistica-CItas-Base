<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Operario;

class LogisticaController extends Controller
{
    /**
     * Determina de forma segura y robusta si el sistema debe operar en modo API / erp_ordenes_sync.
     * En producción o sin driver ODBC de SQL Server, SIEMPRE fuerza modo API.
     */
    public function esModoApi()
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
     * Traer todas las órdenes DPE para el monitor.
     */
    public function ordenesPendientes($forceDb = false)
    {
        if (!$forceDb && $this->esModoApi()) {
            return $this->ordenesPendientesSync();
        }


        try {
            $sql = "
                SELECT 
                    MA_ODC.c_DOCUMENTO AS numero_oc,
                    MA_ODC.d_FECHA AS fecha_emision,
                    MA_ODC.d_fecha_recepcion AS fecha_recepcion,
                    MA_ODC.c_DESCRIPCION AS proveedor,
                    CAST(MA_ODC.c_OBSERVACION AS VARCHAR(MAX)) AS observacion,
                    MA_ODC.C_DESPACHAR AS destino,
                    MA_ODC.c_CODPROVEEDOR AS Codigo_Proveedor,
                    MA_ODC.c_CODCOMPRADOR AS Comprador_Interno,
                    PROV.c_rif AS c_rif,
                    MAX(COALESCE(
                        NULLIF(LTRIM(RTRIM(PROV.c_email)), ''),
                        NULLIF(LTRIM(RTRIM(PROV.c_email_ven)), ''),
                        NULLIF(LTRIM(RTRIM(PROV.c_email_adm)), ''),
                        NULLIF(LTRIM(RTRIM(PROV.c_email_vdd)), ''),
                        NULLIF(LTRIM(RTRIM(PROV.c_email_fiscal)), ''),
                        NULLIF(LTRIM(RTRIM(PROV.c_email_reg)), ''),
                        NULLIF(LTRIM(RTRIM(PROV.c_email_depo)), ''),
                        NULLIF(LTRIM(RTRIM(PROV.c_email_dep)), '')
                    )) AS Email_Proveedor,
                    PROV.c_telefono AS Telefono_Proveedor,
                    COUNT(TR_ODC.c_CODARTICULO) as cant_productos,
                    
                    -- Bultos para Secos
                    SUM(CASE 
                        WHEN MA_PRODUCTOS.c_departamento NOT IN ('14', '10', '11', '12', '13', '15', '21', '23') 
                        THEN CAST(TR_ODC.n_CANTIDAD / NULLIF(MA_PRODUCTOS.n_cantibul, 0) AS DECIMAL(18, 2)) 
                        ELSE 0 
                    END) AS total_bultos,
                    
                    -- Bultos Granulares para Secos
                    SUM(CASE WHEN MA_PRODUCTOS.c_departamento = '01' THEN CAST(TR_ODC.n_CANTIDAD / NULLIF(MA_PRODUCTOS.n_cantibul, 0) AS DECIMAL(18, 2)) ELSE 0 END) AS total_bultos_viveres,
                    SUM(CASE WHEN MA_PRODUCTOS.c_departamento = '02' THEN CAST(TR_ODC.n_CANTIDAD / NULLIF(MA_PRODUCTOS.n_cantibul, 0) AS DECIMAL(18, 2)) ELSE 0 END) AS total_bultos_cuidado_personal,
                    SUM(CASE WHEN MA_PRODUCTOS.c_departamento = '03' THEN CAST(TR_ODC.n_CANTIDAD / NULLIF(MA_PRODUCTOS.n_cantibul, 0) AS DECIMAL(18, 2)) ELSE 0 END) AS total_bultos_limpieza,
                    SUM(CASE WHEN MA_PRODUCTOS.c_departamento = '04' THEN CAST(TR_ODC.n_CANTIDAD / NULLIF(MA_PRODUCTOS.n_cantibul, 0) AS DECIMAL(18, 2)) ELSE 0 END) AS total_bultos_licor_bebidas,
                    
                    -- KG para Perecederos y Fruver
                    SUM(CASE 
                        WHEN MA_PRODUCTOS.c_departamento IN ('10', '11', '12', '13', '15', '21', '23') 
                             OR (MA_PRODUCTOS.c_departamento = '14' AND MA_PRODUCTOS.c_presenta = 'KG')
                        THEN CAST(TR_ODC.n_CANTIDAD AS DECIMAL(18, 2)) 
                        ELSE 0 
                    END) AS total_kg,
                    
                    -- Unidades para Fruver
                    SUM(CASE 
                        WHEN MA_PRODUCTOS.c_departamento = '14' AND MA_PRODUCTOS.c_presenta = 'UND'
                        THEN CAST(TR_ODC.n_CANTIDAD AS DECIMAL(18, 2)) 
                        ELSE 0 
                    END) AS total_und,

                    -- Totales Granulares Fruver (Fase 4)
                    SUM(CASE WHEN MA_PRODUCTOS.c_departamento = '14' AND MA_PRODUCTOS.c_presenta = 'KG' AND (MA_GRUPOS.C_DESCRIPCIO LIKE '%FRUTA%' OR MA_SUBGRUPOS.C_DESCRIPCIO LIKE '%FRUTA%') AND NOT (MA_SUBGRUPOS.C_DESCRIPCIO LIKE '%HORTALIZA%' OR MA_PRODUCTOS.c_descri LIKE '%BROCOLI%') THEN CAST(TR_ODC.n_CANTIDAD AS DECIMAL(18, 2)) ELSE 0 END) AS total_kg_frutas,
                    SUM(CASE WHEN MA_PRODUCTOS.c_departamento = '14' AND MA_PRODUCTOS.c_presenta <> 'KG' AND (MA_GRUPOS.C_DESCRIPCIO LIKE '%FRUTA%' OR MA_SUBGRUPOS.C_DESCRIPCIO LIKE '%FRUTA%') AND NOT (MA_SUBGRUPOS.C_DESCRIPCIO LIKE '%HORTALIZA%' OR MA_PRODUCTOS.c_descri LIKE '%BROCOLI%') THEN CAST(TR_ODC.n_CANTIDAD AS DECIMAL(18, 2)) ELSE 0 END) AS total_und_frutas,
                    
                    SUM(CASE WHEN MA_PRODUCTOS.c_departamento = '14' AND MA_PRODUCTOS.c_presenta = 'KG' AND (MA_GRUPOS.C_DESCRIPCIO LIKE '%VERDURA%' OR MA_SUBGRUPOS.C_DESCRIPCIO LIKE '%VERDURA%' OR MA_SUBGRUPOS.C_DESCRIPCIO LIKE '%CRIOLLA%' OR MA_SUBGRUPOS.C_DESCRIPCIO LIKE '%LEGUMBRE%') AND NOT (MA_SUBGRUPOS.C_DESCRIPCIO LIKE '%HORTALIZA%' OR MA_PRODUCTOS.c_descri LIKE '%BROCOLI%') THEN CAST(TR_ODC.n_CANTIDAD AS DECIMAL(18, 2)) ELSE 0 END) AS total_kg_verduras,
                    SUM(CASE WHEN MA_PRODUCTOS.c_departamento = '14' AND MA_PRODUCTOS.c_presenta <> 'KG' AND (MA_GRUPOS.C_DESCRIPCIO LIKE '%VERDURA%' OR MA_SUBGRUPOS.C_DESCRIPCIO LIKE '%VERDURA%' OR MA_SUBGRUPOS.C_DESCRIPCIO LIKE '%CRIOLLA%' OR MA_SUBGRUPOS.C_DESCRIPCIO LIKE '%LEGUMBRE%') AND NOT (MA_SUBGRUPOS.C_DESCRIPCIO LIKE '%HORTALIZA%' OR MA_PRODUCTOS.c_descri LIKE '%BROCOLI%') THEN CAST(TR_ODC.n_CANTIDAD AS DECIMAL(18, 2)) ELSE 0 END) AS total_und_verduras,
                    
                    SUM(CASE WHEN MA_PRODUCTOS.c_departamento = '14' AND MA_PRODUCTOS.c_presenta = 'KG' AND (MA_SUBGRUPOS.C_DESCRIPCIO LIKE '%HORTALIZA%' OR MA_PRODUCTOS.c_descri LIKE '%BROCOLI%' OR MA_PRODUCTOS.c_descri LIKE '%CELERY%' OR MA_PRODUCTOS.c_descri LIKE '%ZANAHORIA%') THEN CAST(TR_ODC.n_CANTIDAD AS DECIMAL(18, 2)) ELSE 0 END) AS total_kg_hortalizas,
                    SUM(CASE WHEN MA_PRODUCTOS.c_departamento = '14' AND MA_PRODUCTOS.c_presenta <> 'KG' AND (MA_SUBGRUPOS.C_DESCRIPCIO LIKE '%HORTALIZA%' OR MA_PRODUCTOS.c_descri LIKE '%BROCOLI%' OR MA_PRODUCTOS.c_descri LIKE '%CELERY%' OR MA_PRODUCTOS.c_descri LIKE '%ZANAHORIA%') THEN CAST(TR_ODC.n_CANTIDAD AS DECIMAL(18, 2)) ELSE 0 END) AS total_und_hortalizas,

                    -- Totales Granulares Perecederos (Fase 5)
                    SUM(CASE WHEN MA_PRODUCTOS.c_departamento = '11' AND MA_PRODUCTOS.c_presenta = 'KG' THEN CAST(TR_ODC.n_CANTIDAD AS DECIMAL(18, 2)) ELSE 0 END) AS total_kg_carnes,
                    SUM(CASE WHEN MA_PRODUCTOS.c_departamento = '11' AND MA_PRODUCTOS.c_presenta <> 'KG' THEN CAST(TR_ODC.n_CANTIDAD AS DECIMAL(18, 2)) ELSE 0 END) AS total_und_carnes,
                    
                    SUM(CASE WHEN (MA_PRODUCTOS.c_departamento IN ('10', '12') OR (MA_PRODUCTOS.c_departamento = '15' AND (MA_GRUPOS.C_DESCRIPCIO LIKE '%LACTEA%' OR MA_GRUPOS.C_DESCRIPCIO LIKE '%LECHE%' OR MA_GRUPOS.C_DESCRIPCIO LIKE '%QUESO%' OR MA_GRUPOS.C_DESCRIPCIO LIKE '%YOGURT%'))) AND MA_PRODUCTOS.c_presenta = 'KG' THEN CAST(TR_ODC.n_CANTIDAD AS DECIMAL(18, 2)) ELSE 0 END) AS total_kg_charcuteria,
                    SUM(CASE WHEN (MA_PRODUCTOS.c_departamento IN ('10', '12') OR (MA_PRODUCTOS.c_departamento = '15' AND (MA_GRUPOS.C_DESCRIPCIO LIKE '%LACTEA%' OR MA_GRUPOS.C_DESCRIPCIO LIKE '%LECHE%' OR MA_GRUPOS.C_DESCRIPCIO LIKE '%QUESO%' OR MA_GRUPOS.C_DESCRIPCIO LIKE '%YOGURT%'))) AND MA_PRODUCTOS.c_presenta <> 'KG' THEN CAST(TR_ODC.n_CANTIDAD AS DECIMAL(18, 2)) ELSE 0 END) AS total_und_charcuteria,
                    
                    SUM(CASE WHEN MA_PRODUCTOS.c_departamento = '13' AND MA_PRODUCTOS.c_presenta = 'KG' THEN CAST(TR_ODC.n_CANTIDAD AS DECIMAL(18, 2)) ELSE 0 END) AS total_kg_pescaderia,
                    SUM(CASE WHEN MA_PRODUCTOS.c_departamento = '13' AND MA_PRODUCTOS.c_presenta <> 'KG' THEN CAST(TR_ODC.n_CANTIDAD AS DECIMAL(18, 2)) ELSE 0 END) AS total_und_pescaderia,
                    
                    SUM(CASE WHEN MA_PRODUCTOS.c_departamento = '15' AND NOT (MA_GRUPOS.C_DESCRIPCIO LIKE '%LACTEA%' OR MA_GRUPOS.C_DESCRIPCIO LIKE '%LECHE%' OR MA_GRUPOS.C_DESCRIPCIO LIKE '%QUESO%' OR MA_GRUPOS.C_DESCRIPCIO LIKE '%YOGURT%') AND MA_PRODUCTOS.c_presenta = 'KG' THEN CAST(TR_ODC.n_CANTIDAD AS DECIMAL(18, 2)) ELSE 0 END) AS total_kg_congelados,
                    SUM(CASE WHEN MA_PRODUCTOS.c_departamento = '15' AND NOT (MA_GRUPOS.C_DESCRIPCIO LIKE '%LACTEA%' OR MA_GRUPOS.C_DESCRIPCIO LIKE '%LECHE%' OR MA_GRUPOS.C_DESCRIPCIO LIKE '%QUESO%' OR MA_GRUPOS.C_DESCRIPCIO LIKE '%YOGURT%') AND MA_PRODUCTOS.c_presenta <> 'KG' THEN CAST(TR_ODC.n_CANTIDAD AS DECIMAL(18, 2)) ELSE 0 END) AS total_und_congelados,

                    -- Banderas de categoría
                    MAX(CASE WHEN MA_PRODUCTOS.c_departamento = '14' THEN 1 ELSE 0 END) AS es_fruver,
                    MAX(CASE WHEN MA_PRODUCTOS.c_departamento IN ('10', '11', '12', '13', '15', '23') THEN 1 ELSE 0 END) AS es_perecederos,
                    MAX(CASE WHEN MA_PRODUCTOS.c_departamento NOT IN ('14', '10', '11', '12', '13', '15', '23') THEN 1 ELSE 0 END) AS es_secos,

                    -- Sub-banderas Fruver
                    MAX(CASE WHEN MA_PRODUCTOS.c_departamento = '14' AND (MA_SUBGRUPOS.C_DESCRIPCIO LIKE '%HORTALIZA%' OR MA_PRODUCTOS.c_descri LIKE '%BROCOLI%' OR MA_PRODUCTOS.c_descri LIKE '%CELERY%' OR MA_PRODUCTOS.c_descri LIKE '%ZANAHORIA%') THEN 1 ELSE 0 END) AS es_hortaliza,
                    MAX(CASE WHEN MA_PRODUCTOS.c_departamento = '14' AND (MA_GRUPOS.C_DESCRIPCIO LIKE '%FRUTA%' OR MA_SUBGRUPOS.C_DESCRIPCIO LIKE '%FRUTA%') AND NOT (MA_SUBGRUPOS.C_DESCRIPCIO LIKE '%HORTALIZA%' OR MA_PRODUCTOS.c_descri LIKE '%BROCOLI%') THEN 1 ELSE 0 END) AS es_fruta,
                    MAX(CASE WHEN MA_PRODUCTOS.c_departamento = '14' AND (MA_GRUPOS.C_DESCRIPCIO LIKE '%VERDURA%' OR MA_SUBGRUPOS.C_DESCRIPCIO LIKE '%VERDURA%' OR MA_SUBGRUPOS.C_DESCRIPCIO LIKE '%CRIOLLA%' OR MA_SUBGRUPOS.C_DESCRIPCIO LIKE '%LEGUMBRE%') AND NOT (MA_SUBGRUPOS.C_DESCRIPCIO LIKE '%HORTALIZA%' OR MA_PRODUCTOS.c_descri LIKE '%BROCOLI%') THEN 1 ELSE 0 END) AS es_verdura,

                    -- Sub-banderas Perecederos (Fase 5)
                    MAX(CASE WHEN MA_PRODUCTOS.c_departamento = '11' THEN 1 ELSE 0 END) AS es_carnes,
                    MAX(CASE WHEN MA_PRODUCTOS.c_departamento IN ('10', '12') OR (MA_PRODUCTOS.c_departamento = '15' AND (MA_GRUPOS.C_DESCRIPCIO LIKE '%LACTEA%' OR MA_GRUPOS.C_DESCRIPCIO LIKE '%LECHE%' OR MA_GRUPOS.C_DESCRIPCIO LIKE '%QUESO%' OR MA_GRUPOS.C_DESCRIPCIO LIKE '%YOGURT%')) THEN 1 ELSE 0 END) AS es_charcuteria,
                    MAX(CASE WHEN MA_PRODUCTOS.c_departamento = '13' THEN 1 ELSE 0 END) AS es_pescaderia,
                    MAX(CASE WHEN MA_PRODUCTOS.c_departamento = '15' AND NOT (MA_GRUPOS.C_DESCRIPCIO LIKE '%LACTEA%' OR MA_GRUPOS.C_DESCRIPCIO LIKE '%LECHE%' OR MA_GRUPOS.C_DESCRIPCIO LIKE '%QUESO%' OR MA_GRUPOS.C_DESCRIPCIO LIKE '%YOGURT%') THEN 1 ELSE 0 END) AS es_congelados
                FROM MA_ODC WITH (NOLOCK)
                INNER JOIN TR_ODC WITH (NOLOCK) ON MA_ODC.c_DOCUMENTO = TR_ODC.c_DOCUMENTO 
                INNER JOIN MA_PRODUCTOS WITH (NOLOCK) ON TR_ODC.c_CODARTICULO = MA_PRODUCTOS.C_CODIGO
                LEFT JOIN MA_GRUPOS WITH (NOLOCK) ON MA_PRODUCTOS.c_grupo = MA_GRUPOS.c_codigo
                LEFT JOIN MA_SUBGRUPOS WITH (NOLOCK) ON MA_PRODUCTOS.c_subgrupo = MA_SUBGRUPOS.c_codigo
                LEFT JOIN MA_PROVEEDORES PROV WITH (NOLOCK) ON MA_ODC.c_CODPROVEEDOR = PROV.c_codproveed
                WHERE (
                    LTRIM(RTRIM(UPPER(MA_ODC.c_status))) = 'DPE'
                    OR (
                        LTRIM(RTRIM(UPPER(MA_ODC.c_status))) = 'DCO' 
                        AND MA_ODC.d_FECHA >= DATEADD(day, -30, GETDATE())
                    )
                )
                AND LTRIM(RTRIM(UPPER(MA_ODC.C_DESPACHAR))) LIKE '01%'
                GROUP BY MA_ODC.c_DOCUMENTO, MA_ODC.d_FECHA, MA_ODC.d_fecha_recepcion, MA_ODC.c_DESCRIPCION, CAST(MA_ODC.c_OBSERVACION AS VARCHAR(MAX)), MA_ODC.C_DESPACHAR, MA_ODC.c_CODPROVEEDOR, MA_ODC.c_CODCOMPRADOR, PROV.c_rif, PROV.c_telefono
                ORDER BY MA_ODC.d_FECHA DESC
            ";
            
            $ordenes = DB::connection('sqlsrv')->select($sql);
            
            $citasFacturas = DB::table('appointments')
                ->whereNotIn('estatus', ['cancelada', 'anulada'])
                ->get(['numero_oc', 'numero_factura', 'factura_path', 'created_at'])
                ->keyBy('numero_oc');

            $allOcs = array_map(fn($o) => trim($o->numero_oc ?? ''), $ordenes);
            $emailLogs = DB::table('email_logs')
                ->whereIn('numero_oc', $allOcs)
                ->where('tipo_evento', 'odc_habilitada')
                ->orderBy('created_at', 'desc')
                ->get()
                ->keyBy('numero_oc');

            $syncRows = DB::table('erp_ordenes_sync')
                ->whereIn('numero_oc', $allOcs)
                ->get(['numero_oc', 'estatus_habilitacion', 'habilitada_por_user_id', 'fecha_emision', 'created_at', 'updated_at'])
                ->keyBy('numero_oc');

            foreach ($ordenes as $o) {
                $numOc = trim($o->numero_oc);
                $o->numero_factura = isset($citasFacturas[$numOc]) ? $citasFacturas[$numOc]->numero_factura : null;
                $o->factura_url = (isset($citasFacturas[$numOc]) && $citasFacturas[$numOc]->factura_path)
                    ? \Illuminate\Support\Facades\Storage::url($citasFacturas[$numOc]->factura_path)
                    : null;
                
                $fEnvio = isset($emailLogs[$numOc]) ? $emailLogs[$numOc]->created_at : null;
                if (!$fEnvio && isset($syncRows[$numOc])) {
                    $sRow = $syncRows[$numOc];
                    $sCreated = $sRow->created_at ?? null;
                    $sUpdated = $sRow->updated_at ?? null;
                    $fueHab = in_array($sRow->estatus_habilitacion ?? null, ['habilitada', 'agendada']) || !empty($sRow->habilitada_por_user_id ?? null);
                    if ($fueHab && !empty($sUpdated) && $sUpdated != $sCreated) {
                        $fEnvio = $sUpdated;
                    } elseif (!empty($sRow->fecha_emision ?? null)) {
                        $fEnvio = $sRow->fecha_emision;
                    } elseif (!empty($sCreated)) {
                        $fEnvio = $sCreated;
                    }
                }
                if (!$fEnvio && !empty($o->fecha_emision)) {
                    $fEnvio = $o->fecha_emision;
                }
                $o->fecha_envio_comprador = $fEnvio;
                $o->fecha_registro_cita = isset($citasFacturas[$numOc]) ? $citasFacturas[$numOc]->created_at : null;
            }

            $authUser = auth('web')->user() ?: request()->user();
            $isTestAuthorized = $authUser && ($authUser->role === 'admin' || in_array($authUser->username, ['Compras.Juan', 'PROV.PRUEBA']));

            if ($isTestAuthorized) {
                $testSync = DB::table('erp_ordenes_sync')
                    ->where('numero_oc', 'like', 'TEST-%')
                    ->get();
                foreach ($testSync as $tRow) {
                    $obj = json_decode($tRow->resumen_json);
                    if ($obj) {
                        $obj->numero_oc = $tRow->numero_oc;
                        $obj->estatus_habilitacion = $tRow->estatus_habilitacion;
                        $obj->numero_factura = null;
                        $obj->factura_url = null;
                        $ordenes[] = $obj;
                    }
                }
            } else {
                $ordenes = array_values(array_filter($ordenes, function($o) {
                    $num = trim($o->numero_oc ?? '');
                    return !str_starts_with(strtoupper($num), 'TEST-');
                }));
            }

            return response()->json(['status' => 'Exitoso', 'ordenes' => $ordenes]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Fallo conexión SQLSRV directa en ordenesPendientes: " . $e->getMessage() . ". Usando fallback erp_ordenes_sync.");
            return $this->ordenesPendientesSync();
        }
    }

    /**
     * Lectura de órdenes desde erp_ordenes_sync de forma local y segura
     */
    private function ordenesPendientesSync()
    {
        try {
            $authUser = auth('web')->user() ?: request()->user();
            $isTestAuthorized = $authUser && ($authUser->role === 'admin' || in_array($authUser->username, ['Compras.Juan', 'PROV.PRUEBA']));

            $querySync = DB::table('erp_ordenes_sync')
                ->where(function($q) {
                    $q->where('fecha_emision', '>=', '2026-06-01')
                      ->orWhereNull('fecha_emision')
                      ->orWhere('fecha_emision', '');
                });

            if (!$isTestAuthorized) {
                $querySync->where(function($q) {
                    $q->where('numero_oc', 'NOT LIKE', 'TEST-%')
                      ->where(function($sub) {
                          $sub->whereNull('rif_proveedor')
                              ->orWhereNotIn('rif_proveedor', ['J-999999999', 'J999999999']);
                      });
                });
            }

            $ordenesSync = $querySync
                ->orderByRaw("CASE WHEN fecha_emision IS NULL OR fecha_emision = '' THEN 1 ELSE 0 END, fecha_emision DESC, numero_oc DESC")
                ->limit(2000)
                ->get(['numero_oc', 'fecha_emision', 'fecha_recepcion', 'proveedor', 'destino', 'resumen_json', 'estatus_habilitacion', 'habilitada_por_user_id', 'rif_proveedor', 'created_at', 'updated_at']);
            
            if (empty($ordenesSync) || (is_object($ordenesSync) && method_exists($ordenesSync, 'isEmpty') && $ordenesSync->isEmpty()) || (is_array($ordenesSync) && count($ordenesSync) === 0)) {
                $apiUrl = config('app.erp_api_url') ?: env('ERP_API_URL', 'https://citsur.suraki.net/api');
                $token = config('app.erp_api_token') ?: env('ERP_API_TOKEN', 'SurakiSecreto2026');
                if ($apiUrl) {
                    try {
                        $apiResponse = \Illuminate\Support\Facades\Http::withToken($token)
                            ->withoutVerifying()
                            ->timeout(15)
                            ->get("{$apiUrl}/erp/ordenes-pendientes");
                            
                        if ($apiResponse->successful()) {
                            $todas = $apiResponse->json()['ordenes'] ?? [];
                            $now = now();
                            $insertData = [];
                            foreach ($todas as $o) {
                                $numOc = $o['Numero_OC'] ?? $o['numero_oc'] ?? null;
                                if (!$numOc) continue;
                                $insertData[] = [
                                    'numero_oc' => $numOc,
                                    'fecha_emision' => $o['fecha_odc'] ?? $o['Fecha_Emision'] ?? $o['fecha_emision'] ?? null,
                                    'fecha_recepcion' => $o['fecha_recepcion'] ?? null,
                                    'proveedor' => $o['Nombre_Proveedor'] ?? $o['proveedor'] ?? null,
                                    'destino' => $o['Muelle_Destino'] ?? $o['destino'] ?? null,
                                    'resumen_json' => json_encode($o),
                                    'detalles_json' => json_encode($o['detalles'] ?? []),
                                    'categoria_sugerida' => \App\Services\AppointmentDurationService::detectarCategoria($o),
                                    'peso_estimado_ton' => \App\Services\AppointmentDurationService::estimarPesoToneladas($o),
                                    'estatus_habilitacion' => $o['estatus_habilitacion'] ?? 'pendiente',
                                    'created_at' => $now,
                                    'updated_at' => $now,
                                ];
                            }
                            foreach (array_chunk($insertData, 100) as $chunk) {
                                DB::table('erp_ordenes_sync')->upsert($chunk, ['numero_oc'], [
                                    'fecha_emision', 'fecha_recepcion', 'proveedor', 'destino',
                                    'detalles_json', 'categoria_sugerida', 'peso_estimado_ton', 'updated_at'
                                ]);
                            }
                            $ordenesSync = DB::table('erp_ordenes_sync')
                                ->where(function($q) {
                                    $q->where('fecha_emision', '>=', '2026-06-01')
                                      ->orWhereNull('fecha_emision')
                                      ->orWhere('fecha_emision', '');
                                })
                                ->orderByRaw("CASE WHEN fecha_emision IS NULL OR fecha_emision = '' THEN 1 ELSE 0 END, fecha_emision DESC, numero_oc DESC")
                                ->limit(2000)
                                ->get(['numero_oc', 'fecha_emision', 'fecha_recepcion', 'proveedor', 'destino', 'resumen_json', 'estatus_habilitacion', 'habilitada_por_user_id', 'created_at', 'updated_at']);
                        }
                    } catch (\Exception $ex) {}
                }
            }
            
            $citasFacturas = DB::table('appointments')
                ->whereNotIn('estatus', ['cancelada', 'anulada'])
                ->get(['numero_oc', 'numero_factura', 'factura_path', 'created_at', 'updated_at', 'estatus', 'fecha_completada', 'completada_por_nombre'])
                ->keyBy('numero_oc');

            $emailLogs = DB::table('email_logs')
                ->where('tipo_evento', 'odc_habilitada')
                ->orderBy('created_at', 'desc')
                ->get(['numero_oc', 'created_at'])
                ->keyBy('numero_oc');

            $ordenesFinales = [];
            foreach ($ordenesSync as $row) {
                if (empty(trim($row->resumen_json))) continue;
                
                $obj = json_decode($row->resumen_json, true);
                if ($obj && is_array($obj)) {
                    $obj['estatus_habilitacion'] = $row->estatus_habilitacion;
                    $obj['numero_oc'] = $obj['numero_oc'] ?? $obj['Numero_OC'] ?? $row->numero_oc;
                    $obj['proveedor'] = $obj['proveedor'] ?? $obj['Nombre_Proveedor'] ?? $row->proveedor ?? '';
                    $obj['destino'] = $obj['destino'] ?? $obj['Muelle_Destino'] ?? $row->destino ?? '0101';
                    $obj['fecha_emision'] = $obj['fecha_emision'] ?? $obj['Fecha_Emision'] ?? $row->fecha_emision ?? '';
                    $obj['fecha_recepcion'] = $obj['fecha_recepcion'] ?? $obj['Fecha_Recepcion'] ?? $row->fecha_recepcion ?? '';
                    $obj['es_secos'] = $obj['es_secos'] ?? $row->es_secos ?? 1;
                    $obj['es_perecederos'] = $obj['es_perecederos'] ?? $row->es_perecederos ?? 0;
                    $obj['es_fruver'] = $obj['es_fruver'] ?? $row->es_fruver ?? 0;
                    
                    $numOc = $row->numero_oc;
                    $obj['numero_factura'] = isset($citasFacturas[$numOc]) ? $citasFacturas[$numOc]->numero_factura : null;
                    $obj['factura_url'] = (isset($citasFacturas[$numOc]) && $citasFacturas[$numOc]->factura_path)
                        ? \Illuminate\Support\Facades\Storage::url($citasFacturas[$numOc]->factura_path)
                        : null;
                    
                    $posiblesNum = array_values(array_unique(array_filter([
                        $numOc,
                        preg_replace('/^E/i', '', $numOc),
                        ltrim(preg_replace('/^E/i', '', $numOc), '0'),
                        str_pad(ltrim(preg_replace('/^E/i', '', $numOc), '0'), 9, '0', STR_PAD_LEFT),
                    ])));
                    
                    $fechaEnvio = null;
                    foreach ($posiblesNum as $pNum) {
                        if (isset($emailLogs[$pNum])) {
                            $fechaEnvio = $emailLogs[$pNum]->created_at;
                            break;
                        }
                    }
                    
                    if (!$fechaEnvio && $row) {
                        $rowCreated = $row->created_at ?? null;
                        $rowUpdated = $row->updated_at ?? null;
                        $fueHabilitada = in_array($row->estatus_habilitacion ?? null, ['habilitada', 'agendada']) || !empty($row->habilitada_por_user_id ?? null);
                        if ($fueHabilitada && !empty($rowUpdated) && $rowUpdated != $rowCreated) {
                            $fechaEnvio = $rowUpdated;
                        } elseif (!empty($row->fecha_emision ?? null)) {
                            $fechaEnvio = $row->fecha_emision;
                        } elseif (!empty($rowCreated)) {
                            $fechaEnvio = $rowCreated;
                        }
                    }
                    $obj['fecha_envio_comprador'] = $fechaEnvio;
                    $obj['fecha_registro_cita'] = isset($citasFacturas[$numOc]) 
                        ? $citasFacturas[$numOc]->created_at 
                        : null;
                    $obj['fecha_completada'] = (isset($citasFacturas[$numOc]) && $citasFacturas[$numOc]->estatus === 'finalizada')
                        ? ($citasFacturas[$numOc]->fecha_completada ?? $citasFacturas[$numOc]->updated_at)
                        : null;
                    $obj['completada_por_nombre'] = (isset($citasFacturas[$numOc]) && $citasFacturas[$numOc]->estatus === 'finalizada')
                        ? ($citasFacturas[$numOc]->completada_por_nombre ?? 'Recepción')
                        : null;
                    
                    $ordenesFinales[] = $obj;
                }
            }

            usort($ordenesFinales, function($a, $b) {
                $dateA = $a['fecha_emision'] ?? $a['fecha_odc'] ?? $a['Fecha_Emision'] ?? '';
                $dateB = $b['fecha_emision'] ?? $b['fecha_odc'] ?? $b['Fecha_Emision'] ?? '';
                if ($dateA === $dateB) {
                    return strcmp($b['numero_oc'] ?? '', $a['numero_oc'] ?? '');
                }
                return strcmp($dateB, $dateA);
            });
            
            return response()->json([
                'status' => 'Exitoso',
                'ordenes' => $ordenesFinales
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], 500);
        }
    }

    /**
     * Buscar orden en base de datos local erp_ordenes_sync (Modo Seguro / Web)
     */
    public function buscarOrdenSync($orden)
    {
        try {
                if (str_starts_with(strtoupper($orden), 'TEST-')) {
                    $authUser = auth('web')->user() ?: request()->user();
                    $isTestAuthorized = $authUser && ($authUser->role === 'admin' || in_array($authUser->username, ['Compras.Juan', 'PROV.PRUEBA']));
                    if (!$isTestAuthorized) {
                        return response()->json(['error' => 'Orden de Compra no encontrada.'], 404);
                    }
                }

                if (str_starts_with(strtoupper($orden), 'TI-')) {
                    $citaTI = DB::table('appointments')->where('numero_oc', $orden)->first();
                    if ($citaTI) {
                        return response()->json([
                            'status' => 'Exitoso',
                            'es_traslado_interno' => true,
                            'nombre_proveedor' => $citaTI->proveedor,
                            'resumen' => [
                                'Numero_OC' => $citaTI->numero_oc,
                                'Nombre_Proveedor' => $citaTI->proveedor,
                                'Codigo_Proveedor' => $citaTI->rif_proveedor ?: 'J-10715201',
                                'Muelle_Destino' => $citaTI->muelle_asignado,
                                'fecha_odc' => $citaTI->created_at,
                                'fecha_recepcion' => $citaTI->fecha_cita,
                                'status_odc' => $citaTI->estatus,
                                'observacion_odc' => $citaTI->observaciones ?: 'Traslado Interno de Galpón',
                            ],
                            'detalles' => [],
                            'tiempos' => ['tiempo_optimo_minutos' => $citaTI->duracion_minutos ?: 60],
                        ]);
                    }
                }

                $ordenLimpia = preg_replace('/^E/i', '', $orden);
                $ordenPad = str_pad($ordenLimpia, 9, '0', STR_PAD_LEFT);
                $ordenConE = 'E' . $ordenPad;
                $ordenLimpiaConE = 'E' . $ordenLimpia;
                
                $row = DB::table('erp_ordenes_sync')
                    ->whereIn('numero_oc', [$orden, $ordenLimpia, $ordenPad, $ordenConE, $ordenLimpiaConE])
                    ->first();
                    
                if (!$row) {
                    // Si no está en la BD local, intentar sincronizar dinámicamente desde la API remota
                    $apiUrl = env('ERP_API_URL', 'https://citsur.suraki.net/api');
                    $token = env('ERP_API_TOKEN', 'SurakiSecreto2026');
                    
                    if ($apiUrl) {
                        try {
                            $apiResponse = \Illuminate\Support\Facades\Http::withToken($token)
                                ->withoutVerifying()
                                ->timeout(10)
                                ->get("{$apiUrl}/erp/ordenes-pendientes");
                                
                            if ($apiResponse->successful()) {
                                $todas = $apiResponse->json()['ordenes'] ?? [];
                                $now = now();
                                $insertData = [];
                                
                                foreach ($todas as $o) {
                                    $numOc = $o['Numero_OC'] ?? $o['numero_oc'] ?? null;
                                    if (!$numOc) continue;
                                    $insertData[] = [
                                        'numero_oc' => $numOc,
                                        'fecha_emision' => $o['fecha_odc'] ?? $o['Fecha_Emision'] ?? null,
                                        'fecha_recepcion' => $o['fecha_recepcion'] ?? null,
                                        'proveedor' => $o['Nombre_Proveedor'] ?? $o['proveedor'] ?? null,
                                        'destino' => $o['Muelle_Destino'] ?? $o['destino'] ?? null,
                                        'resumen_json' => json_encode($o),
                                        'detalles_json' => json_encode($o['detalles'] ?? []),
                                        'categoria_sugerida' => \App\Services\AppointmentDurationService::detectarCategoria($o),
                                        'peso_estimado_ton' => \App\Services\AppointmentDurationService::estimarPesoToneladas($o),
                                        'estatus_habilitacion' => $o['estatus_habilitacion'] ?? 'pendiente',
                                        'created_at' => $now,
                                        'updated_at' => $now,
                                    ];
                                }
                                
                                foreach (array_chunk($insertData, 100) as $chunk) {
                                    DB::table('erp_ordenes_sync')->upsert($chunk, ['numero_oc'], [
                                        'fecha_emision', 'fecha_recepcion', 'proveedor', 'destino',
                                        'resumen_json', 'detalles_json', 'categoria_sugerida', 'peso_estimado_ton', 'updated_at'
                                    ]);
                                }
                                
                                $row = DB::table('erp_ordenes_sync')
                                    ->whereIn('numero_oc', [$orden, $ordenLimpia, $ordenPad, $ordenConE, $ordenLimpiaConE])
                                    ->first();
                            }
                        } catch (\Exception $ex) {}
                    }
                }
                
                if (!$row) {
                    if (!$this->esModoApi() && (extension_loaded('sqlsrv') || extension_loaded('pdo_sqlsrv'))) {
                        try {
                            return $this->buscarOrdenCompleta($orden, true);
                        } catch (\Throwable $eFallback) {
                            \Illuminate\Support\Facades\Log::error("Fallo fallback DB al buscar {$orden}: " . $eFallback->getMessage());
                        }
                    }
                    return response()->json(['error' => "La Orden de Compra {$orden} no existe en el sistema o no ha sido sincronizada."], 404);
                }
                
                $detalles = json_decode($row->detalles_json, true) ?: [];
                $resumen = json_decode($row->resumen_json, true) ?: [];
                
                $destino = trim($row->destino ?? $resumen['Muelle_Destino'] ?? $resumen['destino'] ?? '0101');
                
                $dptosPerecederosFruver = ['10', '11', '12', '13', '14', '15', '21', '23'];
                $esPerecederoOFruver = !empty($row->es_perecederos) || !empty($row->es_fruver) || !empty($resumen['es_perecederos']) || !empty($resumen['es_fruver']);

                if (!$esPerecederoOFruver) {
                    foreach ($detalles as $item) {
                        $dpto = trim(is_array($item) ? ($item['c_departamento'] ?? $item['departamento'] ?? '') : ($item->c_departamento ?? ''));
                        if (in_array($dpto, $dptosPerecederosFruver)) {
                            $esPerecederoOFruver = true;
                            break;
                        }
                    }
                }

                // Sedes o depósitos de tiendas que no reciben agendamiento de proveedores en muelle central
                $destinosNoPermitidos = ['0130', '0131', '01993'];
                if (!empty($destino) && in_array($destino, $destinosNoPermitidos)) {
                    $sucursalMap = [
                        '0130' => '30 DEP SUCURSALES / TIENDAS',
                        '0131' => '31 DEP SUCURSALES / TIENDAS',
                        '01993' => '993 SEDE CC YUAN LIN',
                    ];
                    $nomDep = $sucursalMap[$destino] ?? ("Sede " . $destino);
                    return response()->json([
                        'error' => "La Orden de Compra {$orden} está asignada a la sede \"{$nomDep}\" (Código: {$destino}). La recepción de mercancía y agendamiento de citas se gestiona únicamente a través de los centros de distribución y depósitos operativos de Suraki."
                    ], 422);
                }

                $citaActiva = DB::table('appointments')
                    ->where('numero_oc', $row->numero_oc)
                    ->whereNotIn('estatus', ['cancelada', 'anulada'])
                    ->orderBy('created_at', 'desc')
                    ->first();

                $rif = $resumen['Codigo_Proveedor'] ?? $row->rif_proveedor ?? null;
                $rifLimpio = strtoupper(preg_replace('/[^A-Z0-9]/i', '', trim($rif ?? '')));
                $proveedorEmail = $resumen['Email_Proveedor'] ?? null;
                $proveedorTelefono = $resumen['Telefono_Proveedor'] ?? '';
                $proveedorAsesor = '';
                $contactoId = null;
                $contactosRegistrados = [];
                $user = null;
                $emailsDetectadosErp = $resumen['emails_detectados_erp'] ?? [];
                if ($proveedorEmail && filter_var($proveedorEmail, FILTER_VALIDATE_EMAIL) && !in_array(strtolower($proveedorEmail), $emailsDetectadosErp)) {
                    array_unshift($emailsDetectadosErp, strtolower($proveedorEmail));
                }

                if ($rifLimpio) {
                    // Priorizar el usuario con username exacto (c_codproveed) EXCLUSIVAMENTE con rol proveedor
                    $user = DB::table('users')
                        ->where('role', 'proveedor')
                        ->where(function($q) use ($rifLimpio) {
                            $q->where('username', $rifLimpio)->orWhere('rif', $rifLimpio);
                        })
                        ->first();

                    if ($user) {
                        if (!empty($user->email) && !str_contains($user->email, '@proveedor.suraki.net')) {
                            $proveedorEmail = trim($user->email);
                        }
                        $proveedorAsesor = $user->name;
                        
                        // Obtener contactos de TODOS los usuarios vinculados a este RIF de proveedor
                        $proveedorUserIds = DB::table('users')
                            ->where('role', 'proveedor')
                            ->where(function($q) use ($rifLimpio) {
                                $q->where('username', $rifLimpio)->orWhere('rif', $rifLimpio);
                            })
                            ->pluck('id');

                        $contactosRegistrados = DB::table('proveedor_contactos')
                            ->whereIn('user_id', $proveedorUserIds)
                            ->select('id', 'nombre', 'email', 'telefono')
                            ->get();

                        $contactoValido = $contactosRegistrados
                            ->where('email', '!=', '')
                            ->reject(fn($c) => str_contains($c->email, '@proveedor.suraki.net'))
                            ->first();

                        if ($contactoValido) {
                            $proveedorEmail = trim($contactoValido->email) ?: $proveedorEmail;
                            $proveedorTelefono = trim($contactoValido->telefono) ?: $proveedorTelefono;
                            $proveedorAsesor = trim($contactoValido->nombre) ?: $proveedorAsesor;
                            $contactoId = $contactoValido->id;
                        }
                    }
                }

                // Fallback directo a MA_PROVEEDORES en ERP escaneando TODOS los 8 campos de correo
                if ($rifLimpio) {
                    try {
                        $provErp = DB::connection('sqlsrv')->selectOne("
                            SELECT 
                                COALESCE(
                                    NULLIF(LTRIM(RTRIM(c_email)), ''),
                                    NULLIF(LTRIM(RTRIM(c_email_ven)), ''),
                                    NULLIF(LTRIM(RTRIM(c_email_adm)), ''),
                                    NULLIF(LTRIM(RTRIM(c_email_vdd)), ''),
                                    NULLIF(LTRIM(RTRIM(c_email_fiscal)), ''),
                                    NULLIF(LTRIM(RTRIM(c_email_reg)), ''),
                                    NULLIF(LTRIM(RTRIM(c_email_depo)), ''),
                                    NULLIF(LTRIM(RTRIM(c_email_dep)), '')
                                ) AS c_email,
                                LTRIM(RTRIM(c_email)) AS email_main,
                                LTRIM(RTRIM(c_email_ven)) AS email_ven,
                                LTRIM(RTRIM(c_email_adm)) AS email_adm,
                                LTRIM(RTRIM(c_email_vdd)) AS email_vdd,
                                LTRIM(RTRIM(c_email_fiscal)) AS email_fiscal,
                                LTRIM(RTRIM(c_email_reg)) AS email_reg,
                                LTRIM(RTRIM(c_email_depo)) AS email_depo,
                                LTRIM(RTRIM(c_email_dep)) AS email_dep,
                                LTRIM(RTRIM(c_telefono)) AS c_telefono, 
                                LTRIM(RTRIM(c_descripcio)) AS c_descripcio 
                            FROM MA_PROVEEDORES WITH (NOLOCK)
                            WHERE c_codproveed = ? OR c_rif LIKE ?
                            ORDER BY CASE WHEN c_codproveed = ? THEN 1 ELSE 2 END
                        ", [$rif, "%{$rifLimpio}%", $rif]);

                        if ($provErp) {
                            if (!empty($provErp->c_email)) {
                                $proveedorEmail = trim($provErp->c_email);
                            }
                            $proveedorTelefono = $proveedorTelefono ?: trim($provErp->c_telefono);

                            $emailsRaw = [
                                $provErp->email_main ?? '',
                                $provErp->email_ven ?? '',
                                $provErp->email_adm ?? '',
                                $provErp->email_vdd ?? '',
                                $provErp->email_fiscal ?? '',
                                $provErp->email_reg ?? '',
                                $provErp->email_depo ?? '',
                                $provErp->email_dep ?? '',
                            ];
                            foreach ($emailsRaw as $em) {
                                $em = strtolower(trim((string)$em));
                                if ($em !== '' && filter_var($em, FILTER_VALIDATE_EMAIL) && !in_array($em, $emailsDetectadosErp)) {
                                    $emailsDetectadosErp[] = $em;
                                }
                            }

                            if ((empty($proveedorEmail) || str_contains($proveedorEmail, '@proveedor.suraki.net')) && !empty($emailsDetectadosErp)) {
                                $proveedorEmail = $emailsDetectadosErp[0];
                                if (!empty($user) && str_contains($user->email, '@proveedor.suraki.net')) {
                                    DB::table('users')->where('id', $user->id)->update(['email' => $proveedorEmail]);
                                    $user->email = $proveedorEmail;
                                }
                            }

                            // Sincronizar todos los correos detectados en proveedor_contactos si el usuario existe
                            if ($user && count($emailsDetectadosErp) > 0) {
                                foreach ($emailsDetectadosErp as $emReal) {
                                    \App\Models\ProveedorContacto::firstOrCreate(
                                        ['user_id' => $user->id, 'email' => $emReal],
                                        ['nombre' => $user->name ?: ($resumen['Nombre_Proveedor'] ?? 'Contacto'), 'telefono' => $proveedorTelefono ?: '0000000000']
                                    );
                                }
                                $contactosRegistrados = DB::table('proveedor_contactos')
                                    ->where('user_id', $user->id)
                                    ->select('id', 'nombre', 'email', 'telefono')
                                    ->get();
                            }
                        }
                    } catch (\Throwable $eEmail) {}
                }

                // Auto-crear cuenta de proveedor si no existía aún (excluyendo nombres reservados de sistema)
                $nombresReservados = ['ADMIN', 'ROOT', 'COMPRADOR', 'RECEPTOR', 'GENERAL', 'SISTEMAS', 'TEST'];
                if ($rifLimpio && empty($user) && !in_array($rifLimpio, $nombresReservados)) {
                    try {
                        $emailFinal = (!empty($proveedorEmail) && !str_contains($proveedorEmail, '@proveedor.suraki.net')) 
                            ? $proveedorEmail 
                            : (!empty($emailsDetectadosErp) ? $emailsDetectadosErp[0] : strtolower($rifLimpio) . '@proveedor.suraki.net');

                        $uNew = \App\Models\User::create([
                            'name' => $resumen['Nombre_Proveedor'] ?? $row->proveedor ?? 'Proveedor ' . $rifLimpio,
                            'username' => $rifLimpio,
                            'rif' => $rifLimpio,
                            'email' => $emailFinal,
                            'role' => 'proveedor',
                            'password' => \Illuminate\Support\Facades\Hash::make($rifLimpio),
                        ]);
                        if (!empty($emailFinal) && !str_contains($emailFinal, '@proveedor.suraki.net')) {
                            \App\Models\ProveedorContacto::create([
                                'user_id' => $uNew->id,
                                'nombre' => $uNew->name,
                                'email' => $emailFinal,
                                'telefono' => $proveedorTelefono ?: '0000000000',
                            ]);
                        }
                        $user = $uNew;
                    } catch (\Throwable $eUser) {}
                }

                $emailLogs = DB::table('email_logs')
                    ->whereIn('numero_oc', [$orden, $ordenLimpia, $ordenPad, $ordenConE])
                    ->orderBy('created_at', 'desc')
                    ->get(['id', 'email_destino', 'vendedor_nombre', 'tipo_evento', 'estatus', 'error_mensaje', 'created_at']);

                $habilitadaInfo = null;
                if ($row && $row->habilitada_por_user_id) {
                    $uComp = DB::table('users')->where('id', $row->habilitada_por_user_id)->first();
                    if ($uComp) {
                        $habilitadaInfo = [
                            'nombre' => $uComp->name,
                            'email' => $uComp->email,
                            'fecha' => $row->updated_at ?? $row->created_at
                        ];
                    }
                }

                $emailLogHabilitada = $emailLogs->where('tipo_evento', 'odc_habilitada')->first();
                $fechaEnvioComprador = $emailLogHabilitada ? $emailLogHabilitada->created_at : null;
                if (!$fechaEnvioComprador && $row) {
                    $rCreated = $row->created_at ?? null;
                    $rUpdated = $row->updated_at ?? null;
                    $fueHabilitada = in_array($row->estatus_habilitacion ?? null, ['habilitada', 'agendada']) || !empty($row->habilitada_por_user_id ?? null);
                    if ($fueHabilitada && !empty($rUpdated) && $rUpdated != $rCreated) {
                        $fechaEnvioComprador = $rUpdated;
                    } elseif (!empty($row->fecha_emision ?? null)) {
                        $fechaEnvioComprador = $row->fecha_emision;
                    } elseif (!empty($rCreated)) {
                        $fechaEnvioComprador = $rCreated;
                    }
                }
                $fechaRegistroCita = $citaActiva ? $citaActiva->created_at : null;
                $fechaCompletada = ($citaActiva && $citaActiva->estatus === 'finalizada') 
                    ? ($citaActiva->fecha_completada ?? $citaActiva->updated_at) 
                    : null;
                $completadaPorNombre = ($citaActiva && $citaActiva->estatus === 'finalizada') 
                    ? ($citaActiva->completada_por_nombre ?? 'Recepción') 
                    : null;

                // Return same structure as DB mode (computed database fields take priority over raw JSON)
                return response()->json(array_merge($resumen, [
                    'status' => 'Exitoso',
                    'orden_original' => $row->numero_oc,
                    'estatus_habilitacion' => $row->estatus_habilitacion ?? 'pendiente',
                    'habilitada_info' => $habilitadaInfo,
                    'fecha_envio_comprador' => $fechaEnvioComprador,
                    'fecha_registro_cita' => $fechaRegistroCita,
                    'fecha_completada' => $fechaCompletada,
                    'completada_por_nombre' => $completadaPorNombre,
                    'email_logs' => $emailLogs,
                    'detalles' => $detalles,
                    'tipo_mercancia' => $citaActiva ? $citaActiva->tipo_mercancia : null,
                    'tipo_vehiculo' => $citaActiva ? $citaActiva->tipo_vehiculo : null,
                    'factura_path' => ($citaActiva && $citaActiva->factura_path) ? \Illuminate\Support\Facades\Storage::url($citaActiva->factura_path) : null,
                    'factura_proveedor' => ($citaActiva && $citaActiva->numero_factura) ? $citaActiva->numero_factura : 'Por facturar',
                    'proveedor_email' => $proveedorEmail,
                    'proveedor_telefono' => $proveedorTelefono,
                    'proveedor_asesor' => $proveedorAsesor,
                    'contacto_id' => $contactoId,
                    'contactos_registrados' => $contactosRegistrados ?? [],
                    'emails_detectados_erp' => array_slice(array_values(array_unique($emailsDetectadosErp)), 0, 3),
                ]));
            } 
            catch (\Throwable $e) {
                return response()->json(['error' => 'Fallo al leer orden sincronizada: ' . $e->getMessage()], 500);
            }
    }

    /**
     * Buscar orden completa: resumen + productos + factura + fechas + tiempo óptimo.
     */
    public function buscarOrdenCompleta($orden, $forceDb = false)
    {
        if (str_starts_with(strtoupper($orden), 'TEST-')) {
            $authUser = auth('web')->user() ?: request()->user();
            $isTestAuthorized = $authUser && ($authUser->role === 'admin' || in_array($authUser->username, ['Compras.Juan', 'PROV.PRUEBA']));
            if (!$isTestAuthorized) {
                return response()->json(['error' => 'Orden de Compra no encontrada.'], 404);
            }
            return $this->buscarOrdenSync($orden);
        }

        if (str_starts_with(strtoupper($orden), 'TI-')) {
            $citaTI = DB::table('appointments')->where('numero_oc', strtoupper($orden))->first();
            if ($citaTI) {
                return response()->json([
                    'status' => 'Exitoso',
                    'nombre_proveedor' => $citaTI->proveedor,
                    'resumen' => [
                        'Numero_OC' => $citaTI->numero_oc,
                        'Nombre_Proveedor' => $citaTI->proveedor,
                        'Codigo_Proveedor' => $citaTI->rif_proveedor ?: 'J-10715201',
                        'Comprador_Interno' => 'TRASLADO INTERNO',
                        'Muelle_Destino' => $citaTI->muelle_asignado,
                        'fecha_odc' => $citaTI->created_at,
                        'fecha_recepcion' => $citaTI->fecha_cita,
                        'status_odc' => $citaTI->estatus,
                        'observacion_odc' => $citaTI->observaciones ?: 'Traslado Interno de Galpón',
                    ],
                    'detalles' => [],
                    'factura_proveedor' => null,
                    'factura_path' => null,
                    'fecha_orden' => $citaTI->created_at,
                    'fecha_recepcion' => $citaTI->fecha_cita,
                    'status_orden' => $citaTI->estatus,
                    'status_texto' => 'Traslado Interno',
                    'tipo_vehiculo' => $citaTI->tipo_vehiculo ?: 'camion_350',
                    'tiempos' => [
                        'tiempo_optimo_minutos' => $citaTI->duracion_minutos ?: 60,
                        'operarios_usados' => 2
                    ],
                ]);
            }
        }

        if (!$forceDb && $this->esModoApi()) {
            return $this->buscarOrdenSync($orden);
        }

        try {
            // Limpiamos la orden de un posible prefijo 'E' que el usuario pueda escribir manualmente
            $ordenLimpia = preg_replace('/^E/i', '', $orden);
            // También intentamos rellenar con ceros por si acaso
            $ordenPad = str_pad($ordenLimpia, 9, '0', STR_PAD_LEFT);

            // Buscar datos de la ODC original en MA_ODC (Órdenes de Compra)
            $odcQuery = "
                SELECT 
                    O.c_DOCUMENTO AS Numero_OC,
                    O.c_CODPROVEEDOR AS Codigo_Proveedor,
                    P.c_descripcio AS Nombre_Proveedor,
                    P.c_telefono AS Telefono_Proveedor,
                    COALESCE(
                        NULLIF(LTRIM(RTRIM(P.c_email)), ''),
                        NULLIF(LTRIM(RTRIM(P.c_email_ven)), ''),
                        NULLIF(LTRIM(RTRIM(P.c_email_adm)), ''),
                        NULLIF(LTRIM(RTRIM(P.c_email_vdd)), ''),
                        NULLIF(LTRIM(RTRIM(P.c_email_fiscal)), ''),
                        NULLIF(LTRIM(RTRIM(P.c_email_reg)), ''),
                        NULLIF(LTRIM(RTRIM(P.c_email_depo)), ''),
                        NULLIF(LTRIM(RTRIM(P.c_email_dep)), '')
                    ) AS Email_Proveedor,
                    P.c_email AS prov_email_main,
                    P.c_email_ven AS prov_email_ven,
                    P.c_email_adm AS prov_email_adm,
                    P.c_email_vdd AS prov_email_vdd,
                    P.c_email_fiscal AS prov_email_fiscal,
                    P.c_email_reg AS prov_email_reg,
                    P.c_email_depo AS prov_email_depo,
                    P.c_email_dep AS prov_email_dep,
                    COALESCE(NULLIF(LTRIM(RTRIM(O.c_CODCOMPRADOR)), ''), NULLIF(LTRIM(RTRIM(P.CS_COMPRADOR)), '')) AS Comprador_Interno,
                    O.C_DESPACHAR AS Muelle_Destino,
                    O.d_FECHA AS fecha_odc,
                    O.d_fecha_recepcion AS fecha_recepcion,
                    O.c_status AS status_odc,
                    O.c_OBSERVACION AS observacion_odc
                FROM [MA_ODC] O WITH (NOLOCK)
                LEFT JOIN [MA_PROVEEDORES] P WITH (NOLOCK) ON O.c_CODPROVEEDOR = P.c_codproveed
                WHERE O.c_DOCUMENTO IN (?, ?, ?)
            ";
            
            $odcData = DB::connection('sqlsrv')->select($odcQuery, [$orden, $ordenLimpia, $ordenPad]);

            if (count($odcData) === 0) {
                return response()->json(['error' => 'La Orden de Compra no existe en el ERP.'], 404);
            }

            $documentoReal = $odcData[0]->Numero_OC;

            // Revisar si existe una cita programada para usar su fecha como fecha de recepción
            $citaActiva = DB::table('appointments')
                ->where('numero_oc', $documentoReal)
                ->whereNotIn('estatus', ['cancelada', 'anulada'])
                ->orderBy('created_at', 'desc')
                ->first();
                
            $fechaRecepcionMostrar = $citaActiva 
                ? \Carbon\Carbon::parse($citaActiva->fecha_cita)->format('Y-m-d H:i:s') 
                : $odcData[0]->fecha_recepcion;

            // Traer detalle de productos desde TR_ODC y aplicar cálculo exacto (Fase 2)
            $detalles = DB::connection('sqlsrv')->select("
                SELECT 
                    D.c_CODARTICULO AS codigo,
                    LTRIM(RTRIM(REPLACE(REPLACE(P.c_descri, CHAR(13), ''), CHAR(10), ''))) AS producto,
                    D.n_CANTIDAD AS cantidad_unidades,
                    P.n_cantibul AS unidades_por_caja,
                    CAST(D.n_CANTIDAD / NULLIF(P.n_cantibul, 0) AS DECIMAL(18, 2)) AS bultos,
                    D.n_COSTO AS costo_unitario,
                    D.n_subtotal AS subtotal,
                    P.c_departamento,
                    P.c_presenta,
                    G.C_DESCRIPCIO as grupo_nombre,
                    S.C_DESCRIPCIO as subgrupo_nombre,
                    CASE 
                        WHEN P.n_cantibul >= 24 THEN 1
                        WHEN P.n_cantibul BETWEEN 12 AND 23 THEN 2
                        WHEN P.n_cantibul BETWEEN 4 AND 11 THEN 3
                        ELSE 4 
                    END AS factor_carrito
                FROM [TR_ODC] D WITH (NOLOCK)
                INNER JOIN [MA_PRODUCTOS] P WITH (NOLOCK) ON D.c_CODARTICULO = P.c_CODIGO
                LEFT JOIN [MA_GRUPOS] G WITH (NOLOCK) ON P.c_grupo = G.c_codigo
                LEFT JOIN [MA_SUBGRUPOS] S WITH (NOLOCK) ON P.c_subgrupo = S.c_codigo
                WHERE D.c_DOCUMENTO = ?
                ORDER BY D.c_CODARTICULO
            ", [$documentoReal]);

            $destinoReal = trim($odcData[0]->Muelle_Destino ?? '0101');
            $dptosPerecederosFruver = ['10', '11', '12', '13', '14', '15', '21', '23'];
            $esPerecederoOFruver = false;
            foreach ($detalles as $item) {
                $dpto = trim($item->c_departamento ?? '');
                if (in_array($dpto, $dptosPerecederosFruver)) {
                    $esPerecederoOFruver = true;
                    break;
                }
            }

            // Sedes o depósitos de tiendas que no reciben agendamiento de proveedores en muelle central
            $destinosNoPermitidos = ['0130', '0131', '01993'];
            if (!empty($destinoReal) && in_array($destinoReal, $destinosNoPermitidos)) {
                $sucursalMap = [
                    '0130' => '30 DEP SUCURSALES / TIENDAS',
                    '0131' => '31 DEP SUCURSALES / TIENDAS',
                    '01993' => '993 SEDE CC YUAN LIN',
                ];
                $nomDep = $sucursalMap[$destinoReal] ?? ("Sede " . $destinoReal);
                return response()->json([
                    'error' => "La Orden de Compra {$orden} está asignada a la sede \"{$nomDep}\" (Código: {$destinoReal}). La recepción de mercancía y agendamiento de citas se gestiona únicamente a través de los centros de distribución y depósitos operativos de Suraki."
                ], 422);
            }

            $totalCajas = 0;
            $totalKgPerecederos = 0;
            $totalKgFrutas = 0;
            $totalUndFrutas = 0;
            $totalKgVerduras = 0;
            $totalUndVerduras = 0;
            $totalKgHortalizas = 0;
            $totalUndHortalizas = 0;
            $totalFruverBultos = 0; // Para el cálculo de tiempo de descarga (esfuerzo)

            // Perecederos Granulares (Fase 5)
            $totalKgCarnes = 0;
            $totalUndCarnes = 0;
            $totalKgCharcuteria = 0;
            $totalUndCharcuteria = 0;
            $totalKgPescaderia = 0;
            $totalUndPescaderia = 0;
            $totalKgCongelados = 0;
            $totalUndCongelados = 0;
            
            $sumaFactores = 0;
            $cantProductos = count($detalles);
            $minPorCiclo = [1 => 12, 2 => 16, 3 => 20, 4 => 25]; // min por ciclo según complejidad

            $dptosPerecederos = ['10', '11', '12', '13', '15', '23'];

            foreach ($detalles as $item) {
                $dpto = trim($item->c_departamento);
                $presenta = trim($item->c_presenta);
                $prodNombre = strtoupper($item->producto);
                $grupo = strtoupper($item->grupo_nombre ?? '');
                $subgrupo = strtoupper($item->subgrupo_nombre ?? '');
                
                // Clasificación FRUVER Avanzada (Fase 4)
                if ($dpto === '14') {
                    // Prioridad 1: Hortalizas (por subgrupo o palabras clave)
                    $esHortaliza = (strpos($subgrupo, 'HORTALIZA') !== false) || 
                                   (strpos($prodNombre, 'BROCOLI') !== false) || 
                                   (strpos($prodNombre, 'CELERY') !== false) || 
                                   (strpos($prodNombre, 'ZANAHORIA') !== false) ||
                                   (strpos($prodNombre, 'PIMENTON') !== false) ||
                                   (strpos($prodNombre, 'CEBOLLA') !== false) ||
                                   (strpos($prodNombre, 'AJO') !== false) ||
                                   (strpos($prodNombre, 'AJI') !== false);
                    
                    // Prioridad 2: Frutas
                    $esFruta = !$esHortaliza && ((strpos($grupo, 'FRUTA') !== false) || (strpos($subgrupo, 'FRUTA') !== false));
                    
                    // Prioridad 3: Verduras
                    $esVerdura = !$esHortaliza && !$esFruta && ((strpos($grupo, 'VERDURA') !== false) || (strpos($subgrupo, 'VERDURA') !== false) || (strpos($subgrupo, 'CRIOLLA') !== false) || (strpos($subgrupo, 'LEGUMBRE') !== false));

                    // Guardar categoría detectada en el objeto para el frontend
                    $item->categoria_fruver = $esHortaliza ? 'Hortaliza' : ($esFruta ? 'Fruta' : ($esVerdura ? 'Verdura' : 'Otros'));

                    // Acumuladores por categoría y unidad
                    if ($presenta === 'KG') {
                        if ($esHortaliza) $totalKgHortalizas += (float)$item->cantidad_unidades;
                        elseif ($esFruta) $totalKgFrutas += (float)$item->cantidad_unidades;
                        elseif ($esVerdura) $totalKgVerduras += (float)$item->cantidad_unidades;
                    } else {
                        // Tratar UND, CAJA, CARTON como unidades base
                        if ($esHortaliza) $totalUndHortalizas += (float)$item->cantidad_unidades;
                        elseif ($esFruta) $totalUndFrutas += (float)$item->cantidad_unidades;
                        elseif ($esVerdura) $totalUndVerduras += (float)$item->cantidad_unidades;
                    }
                    
                    // Estimación de esfuerzo para tiempo de descarga
                    if ($presenta === 'KG') {
                        $totalFruverBultos += (float)$item->cantidad_unidades / 20; // 20kg ~ 1 bulto
                    } else {
                        // Si el bulto calculado es > 0 lo usamos, sino asumimos 24 unidades = 1 bulto de esfuerzo
                        $totalFruverBultos += ((float)$item->bultos > 0) ? (float)$item->bultos : ((float)$item->cantidad_unidades / 24);
                    }

                } elseif (in_array($dpto, $dptosPerecederos)) {
                    $totalKgPerecederos += (float)$item->cantidad_unidades;
                    
                    // Clasificación PERECEDEROS Avanzada (Fase 5)
                    $esCarne = ($dpto === '11');
                    $esPescado = ($dpto === '13');
                    $esCharcuteria = ($dpto === '10' || $dpto === '12') || 
                                     ($dpto === '15' && (strpos($grupo, 'LACTEA') !== false || strpos($grupo, 'LECHE') !== false || strpos($grupo, 'QUESO') !== false || strpos($grupo, 'YOGURT') !== false));
                    $esCongelado = ($dpto === '15' && !$esCharcuteria);

                    $item->categoria_perecedero = $esCarne ? 'Carnes' : ($esCharcuteria ? 'Charcutería' : ($esPescado ? 'Pescadería' : ($esCongelado ? 'Congelados' : 'Otros')));

                    if ($presenta === 'KG') {
                        if ($esCarne) $totalKgCarnes += (float)$item->cantidad_unidades;
                        elseif ($esCharcuteria) $totalKgCharcuteria += (float)$item->cantidad_unidades;
                        elseif ($esPescado) $totalKgPescaderia += (float)$item->cantidad_unidades;
                        elseif ($esCongelado) $totalKgCongelados += (float)$item->cantidad_unidades;
                    } else {
                        if ($esCarne) $totalUndCarnes += (float)$item->cantidad_unidades;
                        elseif ($esCharcuteria) $totalUndCharcuteria += (float)$item->cantidad_unidades;
                        elseif ($esPescado) $totalUndPescaderia += (float)$item->cantidad_unidades;
                        elseif ($esCongelado) $totalUndCongelados += (float)$item->cantidad_unidades;
                    }
                } else {
                    $totalCajas += (float)$item->bultos;
                }

                $sumaFactores += $item->factor_carrito;
            }

            // El volumen total de esfuerzo suma Secos + Equivalencia Fruver + Equivalencia Perecederos
            $volumenEsfuerzo = $totalCajas + $totalFruverBultos + ($totalKgPerecederos / 25);
            $ciclos = ceil($volumenEsfuerzo / 96);
            
            // Si hay mercancía pero el cálculo da < 1 ciclo, forzamos 1 ciclo para que tenga tiempo
            if ($ciclos < 1 && ($volumenEsfuerzo > 0)) $ciclos = 1;

            $factorPromedio = $cantProductos > 0 ? round($sumaFactores / $cantProductos) : 2;
            $factorPromedio = max(1, min(4, $factorPromedio));
            $tiempoPorCiclo = $minPorCiclo[$factorPromedio];
            $margenPreparacion = 15; // papeleo, verificación, posicionamiento

            $minutosTotales = ($ciclos * $tiempoPorCiclo) + $margenPreparacion;
            
            // Completar resumen para frontend
            $datosResumen['Nombre_Proveedor'] = trim($odcData[0]->Nombre_Proveedor);
            $datosResumen['Codigo_Proveedor'] = trim($odcData[0]->Codigo_Proveedor);
            $codCompFinal = trim($odcData[0]->Comprador_Interno ?? '');
            if ($codCompFinal === '' || $codCompFinal === 'General') {
                $codCompFinal = '027';
            }
            $datosResumen['Comprador_Interno'] = $codCompFinal;
            $datosResumen['c_CODCOMPRADOR'] = $codCompFinal;
            $datosResumen['Observacion'] = trim($odcData[0]->observacion_odc ?? 'Ninguna');

            // Recopilar todos los correos válidos detectados en los 8 campos de la ficha del ERP
            $emailsRaw = [
                $odcData[0]->prov_email_main ?? '',
                $odcData[0]->prov_email_ven ?? '',
                $odcData[0]->prov_email_adm ?? '',
                $odcData[0]->prov_email_vdd ?? '',
                $odcData[0]->prov_email_fiscal ?? '',
                $odcData[0]->prov_email_reg ?? '',
                $odcData[0]->prov_email_depo ?? '',
                $odcData[0]->prov_email_dep ?? '',
            ];
            $emailsDetectados = [];
            foreach ($emailsRaw as $em) {
                $em = strtolower(trim((string)$em));
                if ($em !== '' && filter_var($em, FILTER_VALIDATE_EMAIL) && !in_array($em, $emailsDetectados)) {
                    $emailsDetectados[] = $em;
                }
            }
            $datosResumen['emails_detectados_erp'] = array_slice($emailsDetectados, 0, 3);
            if (!empty($emailsDetectados[0])) {
                $datosResumen['Email_Proveedor'] = $emailsDetectados[0];
            }

            
            $datosResumen['Total_SKUs'] = $cantProductos;
            $datosResumen['Total_Cajas_Fisicas'] = round($totalCajas);
            $datosResumen['Total_KG_Perecederos'] = round($totalKgPerecederos, 2);

            // Desglose Perecederos (Fase 5)
            $datosResumen['Total_KG_Carnes'] = round($totalKgCarnes, 2);
            $datosResumen['Total_UND_Carnes'] = round($totalUndCarnes);
            $datosResumen['Total_KG_Charcuteria'] = round($totalKgCharcuteria, 2);
            $datosResumen['Total_UND_Charcuteria'] = round($totalUndCharcuteria);
            $datosResumen['Total_KG_Pescaderia'] = round($totalKgPescaderia, 2);
            $datosResumen['Total_UND_Pescaderia'] = round($totalUndPescaderia);
            $datosResumen['Total_KG_Congelados'] = round($totalKgCongelados, 2);
            $datosResumen['Total_UND_Congelados'] = round($totalUndCongelados);

            $datosResumen['Total_KG_Frutas'] = round($totalKgFrutas, 2);
            $datosResumen['Total_UND_Frutas'] = round($totalUndFrutas);
            $datosResumen['Total_KG_Verduras'] = round($totalKgVerduras, 2);
            $datosResumen['Total_UND_Verduras'] = round($totalUndVerduras);
            $datosResumen['Total_KG_Hortalizas'] = round($totalKgHortalizas, 2);
            $datosResumen['Total_UND_Hortalizas'] = round($totalUndHortalizas);
            $datosResumen['Ciclos_Necesarios'] = $ciclos;

            // 6. Obtener operarios disponibles para cálculo óptimo
            $receptoresDisponibles = Operario::where('tipo', 'receptor')->where('disponible', true)->count();
            $cargaDisponibles = Operario::where('tipo', 'carga')->where('disponible', true)->count();

            // Cuadrillas completas: 1 receptor + 1 carga = 1 equipo
            $equiposOperativos = min($receptoresDisponibles, $cargaDisponibles);
            $numEquipos = max($equiposOperativos, 1); // mínimo 1 para el cálculo base

            $tiempoOptimo = ceil(($ciclos * $tiempoPorCiclo / $numEquipos) + $margenPreparacion);
            $tiempoBase = $minutosTotales;

            // Mapear status a texto legible
            $statusOrden = $odcData[0]->status_odc;
            $statusTexto = match(strtoupper(trim($statusOrden))) {
                'DWT' => 'En Espera',
                'DPE' => 'Pendiente',
                'DCO' => 'Completada',
                'DAN' => 'Anulada',
                default => $statusOrden ?? 'Desconocido',
            };

            $rif = trim($odcData[0]->Codigo_Proveedor);
            $proveedorEmail = $odcData[0]->Email_Proveedor ?? null;
            $proveedorTelefono = $odcData[0]->Telefono_Proveedor ?? '';
            $proveedorAsesor = '';
            $contactosRegistrados = [];
            $contactoId = null;

            if ($rif) {
                $user = DB::table('users')->where('role', 'proveedor')->where('username', $rif)->first();
                if ($user) {
                    if (!empty($user->email) && !str_contains($user->email, '@proveedor.suraki.net')) {
                        $proveedorEmail = $user->email;
                    }
                    $proveedorAsesor = $user->name;
                    
                    $contactosRegistrados = DB::table('proveedor_contactos')
                        ->where('user_id', $user->id)
                        ->select('id', 'nombre', 'email', 'telefono')
                        ->get();

                    $contacto = DB::table('proveedor_contactos')
                        ->where('user_id', $user->id)
                        ->orderBy('id', 'desc')
                        ->first();
                    if ($contacto) {
                        if (!empty($contacto->email) && !str_contains($contacto->email, '@proveedor.suraki.net')) {
                            $proveedorEmail = $contacto->email;
                        }
                        $proveedorTelefono = $contacto->telefono ?: $proveedorTelefono;
                        $proveedorAsesor = $contacto->nombre ?: $proveedorAsesor;
                        $contactoId = $contacto->id;
                    }
                }
            }

            if ((empty($proveedorEmail) || str_contains($proveedorEmail, '@proveedor.suraki.net')) && !empty($datosResumen['Email_Proveedor'])) {
                $proveedorEmail = $datosResumen['Email_Proveedor'];
                if (!empty($user) && str_contains($user->email, '@proveedor.suraki.net')) {
                    DB::table('users')->where('id', $user->id)->update(['email' => $proveedorEmail]);
                }
            }

            try {
                $now = now();
                DB::table('erp_ordenes_sync')->upsert([[
                    'numero_oc' => $documentoReal,
                    'fecha_emision' => $odcData[0]->fecha_odc,
                    'fecha_recepcion' => $fechaRecepcionMostrar,
                    'proveedor' => trim($odcData[0]->Nombre_Proveedor),
                    'destino' => trim($odcData[0]->Muelle_Destino),
                    'resumen_json' => json_encode($datosResumen),
                    'detalles_json' => json_encode($detalles),
                    'categoria_sugerida' => \App\Services\AppointmentDurationService::detectarCategoria((array)$datosResumen),
                    'peso_estimado_ton' => \App\Services\AppointmentDurationService::estimarPesoToneladas((array)$datosResumen),
                    'estatus_habilitacion' => 'pendiente',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]], ['numero_oc'], [
                    'fecha_emision', 'fecha_recepcion', 'proveedor', 'destino',
                    'resumen_json', 'detalles_json', 'categoria_sugerida', 'peso_estimado_ton', 'updated_at'
                ]);
            } catch (\Exception $ex) {}

            $syncRowLocal = DB::table('erp_ordenes_sync')->where('numero_oc', $documentoReal)->first();
            $emailLogs = DB::table('email_logs')
                ->whereIn('numero_oc', [$orden, $ordenLimpia, $ordenPad, $documentoReal])
                ->orderBy('created_at', 'desc')
                ->get(['id', 'email_destino', 'vendedor_nombre', 'tipo_evento', 'estatus', 'error_mensaje', 'created_at']);

            $habilitadaInfo = null;
            if ($syncRowLocal && $syncRowLocal->habilitada_por_user_id) {
                $uComp = DB::table('users')->where('id', $syncRowLocal->habilitada_por_user_id)->first();
                if ($uComp) {
                    $habilitadaInfo = [
                        'nombre' => $uComp->name,
                        'email' => $uComp->email,
                        'fecha' => $syncRowLocal->updated_at ?? $syncRowLocal->created_at
                    ];
                }
            }

            $emailLogHabilitada = $emailLogs->where('tipo_evento', 'odc_habilitada')->first();
            $fechaEnvioComprador = $emailLogHabilitada ? $emailLogHabilitada->created_at : null;
            if (!$fechaEnvioComprador && $syncRowLocal) {
                $srCreated = $syncRowLocal->created_at ?? null;
                $srUpdated = $syncRowLocal->updated_at ?? null;
                $fueHabilitada = in_array($syncRowLocal->estatus_habilitacion ?? null, ['habilitada', 'agendada']) || !empty($syncRowLocal->habilitada_por_user_id ?? null);
                if ($fueHabilitada && !empty($srUpdated) && $srUpdated != $srCreated) {
                    $fechaEnvioComprador = $srUpdated;
                } elseif (!empty($syncRowLocal->fecha_emision ?? null)) {
                    $fechaEnvioComprador = $syncRowLocal->fecha_emision;
                } elseif (!empty($srCreated)) {
                    $fechaEnvioComprador = $srCreated;
                }
            }
            $fechaRegistroCita = $citaActiva ? $citaActiva->created_at : null;
            $fechaCompletada = ($citaActiva && $citaActiva->estatus === 'finalizada') 
                ? ($citaActiva->fecha_completada ?? $citaActiva->updated_at) 
                : null;
            $completadaPorNombre = ($citaActiva && $citaActiva->estatus === 'finalizada') 
                ? ($citaActiva->completada_por_nombre ?? 'Recepción') 
                : null;

            return response()->json([
                'status' => 'Exitoso',
                'resumen' => (object)$datosResumen,
                'estatus_habilitacion' => $syncRowLocal ? $syncRowLocal->estatus_habilitacion : 'pendiente',
                'habilitada_info' => $habilitadaInfo,
                'fecha_envio_comprador' => $fechaEnvioComprador,
                'fecha_registro_cita' => $fechaRegistroCita,
                'fecha_completada' => $fechaCompletada,
                'completada_por_nombre' => $completadaPorNombre,
                'email_logs' => $emailLogs,
                'contactos_registrados' => $contactosRegistrados,
                'nombre_proveedor' => trim($odcData[0]->Nombre_Proveedor),
                'factura_proveedor' => ($citaActiva && $citaActiva->numero_factura) ? $citaActiva->numero_factura : 'Por facturar',
                'tipo_mercancia' => $citaActiva ? $citaActiva->tipo_mercancia : null,
                'tipo_vehiculo' => $citaActiva ? $citaActiva->tipo_vehiculo : null,
                'factura_path' => ($citaActiva && $citaActiva->factura_path) ? \Illuminate\Support\Facades\Storage::url($citaActiva->factura_path) : null,
                'fecha_orden' => $odcData[0]->fecha_odc,
                'fecha_recepcion' => $fechaRecepcionMostrar,
                'status_orden' => $statusOrden,
                'status_texto' => $statusTexto,
                'detalles' => $detalles,
                'sucursal_destino' => trim($odcData[0]->Muelle_Destino),
                'sucursal_nombre' => match(trim($odcData[0]->Muelle_Destino)) {
                    '0101' => '01 PISO DE VENTA HIPER SURAKI',
                    '0102' => '02 DEPOSITO GRAL HIPER SURAKI',
                    '0111' => '11 DEP PRODUCCION SURAPAN',
                    '0115' => '15 DEPOSITO INSUMOS GRAL SURAKI',
                    '0140' => '40 DEP CARNES Y PERECEDEROS',
                    '0141' => '41 DEP CARNES Y PERECEDEROS',
                    '0150' => '50 DEP PERECEDEROS',
                    '0160' => '60 DEP GENERAL ANDINKA',
                    '0161' => '61 DEPOSITO GENERAL ANDINKA',
                    '0171' => '71 DEP SUCURSALES',
                    '0180' => '80 GALPON CENTRAL AV ANDRES BELLO',
                    '01993' => '993 SEDE CC YUAN LIN',
                    default => 'Sucursal ' . $odcData[0]->Muelle_Destino
                },
                'tiempos' => [
                    'tiempo_base_minutos' => $tiempoBase,
                    'tiempo_optimo_minutos' => $tiempoOptimo,
                    'receptores_disponibles' => $receptoresDisponibles,
                    'carga_disponibles' => $cargaDisponibles,
                    'operarios_usados' => $numEquipos,
                ],
                'proveedor_email' => $proveedorEmail ?: ($datosResumen['Email_Proveedor'] ?? null),
                'proveedor_telefono' => $proveedorTelefono,
                'proveedor_asesor' => $proveedorAsesor,
                'contacto_id' => $contactoId,
                'contactos_registrados' => $contactosRegistrados ?? [],
                'emails_detectados_erp' => $datosResumen['emails_detectados_erp'] ?? [],
            ]);

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Error al conectar con ERP al buscar orden {$orden}: " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return $this->buscarOrdenSync($orden);
        }
    }

    /**
     * Recalcular tiempo con un número específico de operarios.
     */
    public function recalcularTiempo(Request $request, $orden, $forceDb = false)
    {
        if (!$forceDb && $this->esModoApi()) {
            try {
                $ordenLimpia = preg_replace('/^E/i', '', $orden);
                $ordenPad = str_pad($ordenLimpia, 9, '0', STR_PAD_LEFT);
                
                $row = DB::table('erp_ordenes_sync')
                    ->whereIn('numero_oc', [$orden, $ordenLimpia, $ordenPad])
                    ->first();
                    
                if (!$row) {
                    return response()->json(['error' => 'Orden no encontrada en sincronización'], 404);
                }
                
                $detalles = json_decode($row->detalles_json, true) ?: [];
                $totalCajas = 0;
                $sumaFactores = 0;
                $cantProductos = count($detalles);
                $minPorCiclo = [1 => 12, 2 => 16, 3 => 20, 4 => 25];

                foreach ($detalles as $item) {
                    $totalCajas += (float)($item['bultos'] ?? 0);
                    $sumaFactores += (int)($item['factor_carrito'] ?? 2);
                }

                $numEquipos = max((int)$request->input('operarios', 1), 1);
                $ciclos = ceil($totalCajas / 96);
                if ($ciclos < 1 && $totalCajas > 0) $ciclos = 1;
                
                $factorPromedio = $cantProductos > 0 ? round($sumaFactores / $cantProductos) : 2;
                $factorPromedio = max(1, min(4, $factorPromedio));
                $tiempoPorCiclo = $minPorCiclo[$factorPromedio];

                $tiempoOptimo = ceil(($ciclos * $tiempoPorCiclo / $numEquipos) + 15);

                return response()->json([
                    'tiempo_optimo_minutos' => $tiempoOptimo,
                    'operarios_usados' => $numEquipos,
                ]);
            } catch (\Exception $e) {
                return response()->json(['error' => 'Fallo al recalcular: ' . $e->getMessage()], 500);
            }
        }

        $numEquipos = max((int)$request->input('operarios', 1), 1);

        try {
            $ordenLimpia = preg_replace('/^E/i', '', $orden);
            $ordenPad = str_pad($ordenLimpia, 9, '0', STR_PAD_LEFT);

            // Determinar el documento real
            $odcData = DB::connection('sqlsrv')->select("SELECT c_DOCUMENTO FROM MA_ODC WITH (NOLOCK) WHERE c_DOCUMENTO IN (?, ?, ?)", [$orden, $ordenLimpia, $ordenPad]);
            if(count($odcData) === 0) {
                return response()->json(['error' => 'Orden no encontrada'], 404);
            }
            $documentoReal = $odcData[0]->c_DOCUMENTO;

            $detalles = DB::connection('sqlsrv')->select("
                SELECT 
                    CAST(D.n_CANTIDAD / NULLIF(P.n_cantibul, 0) AS DECIMAL(18, 2)) AS bultos,
                    CASE 
                        WHEN P.n_cantibul >= 24 THEN 1
                        WHEN P.n_cantibul BETWEEN 12 AND 23 THEN 2
                        WHEN P.n_cantibul BETWEEN 4 AND 11 THEN 3
                        ELSE 4 
                    END AS factor_carrito
                FROM [TR_ODC] D WITH (NOLOCK)
                INNER JOIN [MA_PRODUCTOS] P WITH (NOLOCK) ON D.c_CODARTICULO = P.c_CODIGO
                WHERE D.c_DOCUMENTO = ?
            ", [$documentoReal]);

            $totalCajas = 0;
            $sumaFactores = 0;
            $cantProductos = count($detalles);
            $minPorCiclo = [1 => 12, 2 => 16, 3 => 20, 4 => 25];

            foreach ($detalles as $item) {
                $totalCajas += (float)$item->bultos;
                $sumaFactores += $item->factor_carrito;
            }

            $ciclos = ceil($totalCajas / 96);
            if ($ciclos < 1 && $totalCajas > 0) $ciclos = 1;
            
            $factorPromedio = $cantProductos > 0 ? round($sumaFactores / $cantProductos) : 2;
            $factorPromedio = max(1, min(4, $factorPromedio));
            $tiempoPorCiclo = $minPorCiclo[$factorPromedio];

            $tiempoOptimo = ceil(($ciclos * $tiempoPorCiclo / $numEquipos) + 15);

            return response()->json([
                'tiempo_optimo_minutos' => $tiempoOptimo,
                'operarios_usados' => $numEquipos,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Mantener el endpoint legacy de detalles para compatibilidad.
     */
    public function buscarOrden($orden)
    {
        try {
            $detalles = DB::connection('sqlsrv')->select("
                SELECT 
                    D.c_CODARTICULO AS codigo,
                    LTRIM(RTRIM(REPLACE(REPLACE(D.c_descripcion, CHAR(13), ''), CHAR(10), ''))) AS producto,
                    D.n_CANTIDAD AS cantidad_unidades,
                    D.ns_CantidadEmpaque AS unidades_por_caja,
                    CEILING(D.n_CANTIDAD / ISNULL(NULLIF(D.ns_CantidadEmpaque, 0), 1)) AS bultos,
                    D.n_COSTO AS costo_unitario,
                    D.n_subtotal AS subtotal,
                    CASE 
                        WHEN D.ns_CantidadEmpaque >= 24 THEN 1
                        WHEN D.ns_CantidadEmpaque BETWEEN 12 AND 23 THEN 2
                        WHEN D.ns_CantidadEmpaque BETWEEN 4 AND 11 THEN 3
                        ELSE 4 
                    END AS factor_carrito
                FROM [TR_COMPRAS] D WITH (NOLOCK)
                INNER JOIN [MA_COMPRAS] C WITH (NOLOCK) 
                    ON D.c_DOCUMENTO = C.c_DOCUMENTO
                WHERE D.c_DOCUMENTO = ?
                ORDER BY D.c_CODARTICULO
            ", [$orden]);

            if (count($detalles) === 0) {
                return response()->json([
                    'status' => 'Vacío',
                    'detalles' => [],
                    'duracion_minutos' => 0,
                    'mensaje' => 'No se encontraron productos para esta orden.'
                ]);
            }

            $totalCajas = 0;
            $sumaFactores = 0;
            $cantProductos = count($detalles);
            $minPorCiclo = [1 => 12, 2 => 16, 3 => 20, 4 => 25];
            foreach ($detalles as $item) {
                $totalCajas += $item->bultos;
                $sumaFactores += $item->factor_carrito;
            }
            $ciclos = ceil($totalCajas / 96);
            $factorPromedio = $cantProductos > 0 ? round($sumaFactores / $cantProductos) : 2;
            $factorPromedio = max(1, min(4, $factorPromedio));
            $duracionEstimada = ceil(($ciclos * $minPorCiclo[$factorPromedio]) + 15);

            return response()->json([
                'status' => 'Exitoso',
                'detalles' => $detalles,
                'duracion_minutos' => $duracionEstimada,
                'mensaje' => "Descarga estimada en " . $duracionEstimada . " minutos."
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al conectar con ERP: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Obtener productos verificados para una ODC.
     */
    public function obtenerVerificacionesProducto($orden)
    {
        $ordenLimpia = preg_replace('/^E/i', '', $orden);
        $ordenPad = str_pad($ordenLimpia, 9, '0', STR_PAD_LEFT);
        $ordenConE = 'E' . $ordenPad;

        $verificados = DB::table('odc_product_verifications')
            ->whereIn('numero_oc', [$orden, $ordenLimpia, $ordenPad, $ordenConE])
            ->where('revisado', true)
            ->pluck('codigo_producto')
            ->toArray();

        return response()->json([
            'status' => 'Exitoso',
            'verificados' => $verificados
        ]);
    }

    /**
     * Marcar / desmarcar un producto como verificado en una ODC.
     */
    public function verificarProducto(Request $request, $orden)
    {
        $validated = $request->validate([
            'codigo_producto' => 'required|string',
            'revisado' => 'required|boolean',
        ]);

        $ordenLimpia = preg_replace('/^E/i', '', $orden);
        $ordenPad = str_pad($ordenLimpia, 9, '0', STR_PAD_LEFT);
        $ordenConE = 'E' . $ordenPad;

        $numOc = $orden;

        if ($validated['revisado']) {
            DB::table('odc_product_verifications')->updateOrInsert(
                ['numero_oc' => $numOc, 'codigo_producto' => $validated['codigo_producto']],
                [
                    'revisado' => true,
                    'user_id' => auth()->id(),
                    'user_name' => auth()->user() ? auth()->user()->name : 'Usuario',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        } else {
            DB::table('odc_product_verifications')
                ->whereIn('numero_oc', [$orden, $ordenLimpia, $ordenPad, $ordenConE])
                ->where('codigo_producto', $validated['codigo_producto'])
                ->delete();
        }

        $verificados = DB::table('odc_product_verifications')
            ->whereIn('numero_oc', [$orden, $ordenLimpia, $ordenPad, $ordenConE])
            ->where('revisado', true)
            ->pluck('codigo_producto')
            ->toArray();

        return response()->json([
            'status' => 'Exitoso',
            'verificados' => $verificados
        ]);
    }

    /**
     * Marcar / desmarcar TODOS los productos de una ODC en lote.
     */
    public function verificarTodosProductos(Request $request, $orden)
    {
        $validated = $request->validate([
            'codigos' => 'required|array',
            'revisado' => 'required|boolean',
        ]);

        $ordenLimpia = preg_replace('/^E/i', '', $orden);
        $ordenPad = str_pad($ordenLimpia, 9, '0', STR_PAD_LEFT);
        $ordenConE = 'E' . $ordenPad;
        $numOc = $orden;

        if ($validated['revisado']) {
            foreach ($validated['codigos'] as $codigo) {
                DB::table('odc_product_verifications')->updateOrInsert(
                    ['numero_oc' => $numOc, 'codigo_producto' => (string)$codigo],
                    [
                        'revisado' => true,
                        'user_id' => auth()->id(),
                        'user_name' => auth()->user() ? auth()->user()->name : 'Usuario',
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        } else {
            DB::table('odc_product_verifications')
                ->whereIn('numero_oc', [$orden, $ordenLimpia, $ordenPad, $ordenConE])
                ->whereIn('codigo_producto', $validated['codigos'])
                ->delete();
        }

        $verificados = DB::table('odc_product_verifications')
            ->whereIn('numero_oc', [$orden, $ordenLimpia, $ordenPad, $ordenConE])
            ->where('revisado', true)
            ->pluck('codigo_producto')
            ->toArray();

        return response()->json([
            'status' => 'Exitoso',
            'verificados' => $verificados
        ]);
    }
}
