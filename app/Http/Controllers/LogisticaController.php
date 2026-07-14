<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Operario;

class LogisticaController extends Controller
{
    /**
     * Traer todas las órdenes DPE para el monitor.
     */
    public function ordenesPendientes($forceDb = false)
    {
        if (!$forceDb && env('ERP_CONNECTION_MODE') === 'api') {
            try {
                $ordenesSync = DB::table('erp_ordenes_sync')
                    ->where('fecha_emision', '>=', '2026-06-01')
                    ->orderBy('fecha_emision', 'desc')
                    ->limit(2000)
                    ->get(['numero_oc', 'resumen_json', 'estatus_habilitacion']);
                
                $citasFacturas = DB::table('appointments')
                    ->whereNotIn('estatus', ['cancelada', 'anulada'])
                    ->get(['numero_oc', 'numero_factura', 'factura_path'])
                    ->keyBy('numero_oc');

                $ordenesFinales = [];
                foreach ($ordenesSync as $row) {
                    if (empty(trim($row->resumen_json))) continue;
                    
                    $obj = json_decode($row->resumen_json, true);
                    if ($obj && is_array($obj)) {
                        $obj['estatus_habilitacion'] = $row->estatus_habilitacion;
                        
                        $numOc = $row->numero_oc;
                        $obj['numero_factura'] = isset($citasFacturas[$numOc]) ? $citasFacturas[$numOc]->numero_factura : null;
                        $obj['factura_url'] = (isset($citasFacturas[$numOc]) && $citasFacturas[$numOc]->factura_path)
                            ? \Illuminate\Support\Facades\Storage::url($citasFacturas[$numOc]->factura_path)
                            : null;
                        
                        $ordenesFinales[] = $obj;
                    }
                }
                
                return response()->json([
                    'status' => 'Exitoso',
                    'ordenes' => $ordenesFinales
                ]);
            } catch (\Throwable $e) {
                return response()->json(['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], 500);
            }
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
                FROM MA_ODC 
                INNER JOIN TR_ODC ON MA_ODC.c_DOCUMENTO = TR_ODC.c_DOCUMENTO 
                INNER JOIN MA_PRODUCTOS ON TR_ODC.c_CODARTICULO = MA_PRODUCTOS.C_CODIGO
                LEFT JOIN MA_GRUPOS ON MA_PRODUCTOS.c_grupo = MA_GRUPOS.c_codigo
                LEFT JOIN MA_SUBGRUPOS ON MA_PRODUCTOS.c_subgrupo = MA_SUBGRUPOS.c_codigo
                WHERE LTRIM(RTRIM(UPPER(MA_ODC.c_status))) IN ('DPE', 'DCO')
                  AND LTRIM(RTRIM(UPPER(MA_ODC.C_DESPACHAR))) IN ('0101', '0102')
                  AND MA_ODC.d_FECHA >= '2026-06-01'
                GROUP BY MA_ODC.c_DOCUMENTO, MA_ODC.d_FECHA, MA_ODC.d_fecha_recepcion, MA_ODC.c_DESCRIPCION, CAST(MA_ODC.c_OBSERVACION AS VARCHAR(MAX)), MA_ODC.C_DESPACHAR
                ORDER BY MA_ODC.d_FECHA DESC
            ";
            
            $ordenes = DB::connection('sqlsrv')->select($sql);
            
            $citasFacturas = DB::table('appointments')
                ->whereNotIn('estatus', ['cancelada', 'anulada'])
                ->get(['numero_oc', 'numero_factura', 'factura_path'])
                ->keyBy('numero_oc');

            foreach ($ordenes as $o) {
                $numOc = trim($o->numero_oc);
                $o->numero_factura = isset($citasFacturas[$numOc]) ? $citasFacturas[$numOc]->numero_factura : null;
                $o->factura_url = (isset($citasFacturas[$numOc]) && $citasFacturas[$numOc]->factura_path)
                    ? \Illuminate\Support\Facades\Storage::url($citasFacturas[$numOc]->factura_path)
                    : null;
            }

            return response()->json(['status' => 'Exitoso', 'ordenes' => $ordenes]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Buscar orden completa: resumen + productos + factura + fechas + tiempo óptimo.
     */
    public function buscarOrdenCompleta($orden, $forceDb = false)
    {
        if (!$forceDb && env('ERP_CONNECTION_MODE') === 'api') {
            try {
                $ordenLimpia = preg_replace('/^E/i', '', $orden);
                $ordenPad = str_pad($ordenLimpia, 9, '0', STR_PAD_LEFT);
                
                $row = DB::table('erp_ordenes_sync')
                    ->whereIn('numero_oc', [$orden, $ordenLimpia, $ordenPad])
                    ->first();
                    
                if (!$row) {
                    return response()->json(['error' => 'La Orden de Compra no existe en el ERP o no ha sido sincronizada.'], 404);
                }
                
                $detalles = json_decode($row->detalles_json, true) ?: [];
                $resumen = json_decode($row->resumen_json, true) ?: [];
                
                $citaActiva = DB::table('appointments')
                    ->where('numero_oc', $row->numero_oc)
                    ->whereNotIn('estatus', ['cancelada', 'anulada'])
                    ->orderBy('created_at', 'desc')
                    ->first();

                $rif = $resumen['Codigo_Proveedor'] ?? null;
                $proveedorEmail = $resumen['Email_Proveedor'] ?? null;
                $proveedorTelefono = $resumen['Telefono_Proveedor'] ?? '';
                $proveedorAsesor = '';
                $contactoId = null;

                if ($rif) {
                    $user = DB::table('users')->where('username', $rif)->first();
                    if ($user) {
                        $proveedorEmail = $user->email;
                        $proveedorAsesor = $user->name;
                        
                        $contacto = DB::table('proveedor_contactos')
                            ->where('user_id', $user->id)
                            ->orderBy('id', 'desc')
                            ->first();
                        if ($contacto) {
                            $proveedorEmail = $contacto->email ?: $proveedorEmail;
                            $proveedorTelefono = $contacto->telefono ?: $proveedorTelefono;
                            $proveedorAsesor = $contacto->nombre ?: $proveedorAsesor;
                            $contactoId = $contacto->id;
                        }
                    }
                }

                // Return same structure as DB mode
                return response()->json(array_merge([
                    'status' => 'Exitoso',
                    'orden_original' => $row->numero_oc,
                    'detalles' => $detalles,
                    'tipo_mercancia' => $citaActiva ? $citaActiva->tipo_mercancia : null,
                    'tipo_vehiculo' => $citaActiva ? $citaActiva->tipo_vehiculo : null,
                    'factura_path' => ($citaActiva && $citaActiva->factura_path) ? \Illuminate\Support\Facades\Storage::url($citaActiva->factura_path) : null,
                    'factura_proveedor' => ($citaActiva && $citaActiva->numero_factura) ? $citaActiva->numero_factura : 'Por facturar',
                    'proveedor_email' => $proveedorEmail,
                    'proveedor_telefono' => $proveedorTelefono,
                    'proveedor_asesor' => $proveedorAsesor,
                    'contacto_id' => $contactoId,
                ], $resumen));
            } catch (\Exception $e) {
                return response()->json(['error' => 'Fallo al leer orden sincronizada: ' . $e->getMessage()], 500);
            }
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
                    P.c_email AS Email_Proveedor,
                    P.CS_COMPRADOR AS Comprador_Interno,
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
            $datosResumen['Telefono_Proveedor'] = trim($odcData[0]->Telefono_Proveedor ?? 'No registrado');
            $datosResumen['Comprador_Interno'] = trim($odcData[0]->Comprador_Interno ?? 'General');
            $datosResumen['Observacion'] = trim($odcData[0]->observacion_odc ?? 'Ninguna');

            
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
            $contactoId = null;

            if ($rif) {
                $user = DB::table('users')->where('username', $rif)->first();
                if ($user) {
                    $proveedorEmail = $user->email;
                    $proveedorAsesor = $user->name;
                    
                    $contacto = DB::table('proveedor_contactos')
                        ->where('user_id', $user->id)
                        ->orderBy('id', 'desc')
                        ->first();
                    if ($contacto) {
                        $proveedorEmail = $contacto->email ?: $proveedorEmail;
                        $proveedorTelefono = $contacto->telefono ?: $proveedorTelefono;
                        $proveedorAsesor = $contacto->nombre ?: $proveedorAsesor;
                        $contactoId = $contacto->id;
                    }
                }
            }

            return response()->json([
                'status' => 'Exitoso',
                'resumen' => (object)$datosResumen,
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
                    '0101' => '01 PISO DE VENTA TU EMPRESA',
                    '0102' => '02 DEPOSITO GRAL TU EMPRESA',
                    '0111' => '11 DEP PRODUCCION SURAPAN',
                    '0115' => '15 DEPOSITO INSUMOS GRAL Empresa Base',
                    '0161' => '61 DEPOSITO GENERAL ANDINKA',
                    '0180' => '80 GALPON CENTRAL AV ANDRES BELLO',
                    default => 'Sucursal ' . $odcData[0]->Muelle_Destino
                },
                'tiempos' => [
                    'tiempo_base_minutos' => $tiempoBase,
                    'tiempo_optimo_minutos' => $tiempoOptimo,
                    'receptores_disponibles' => $receptoresDisponibles,
                    'carga_disponibles' => $cargaDisponibles,
                    'operarios_usados' => $numEquipos,
                ],
                'proveedor_email' => $proveedorEmail,
                'proveedor_telefono' => $proveedorTelefono,
                'proveedor_asesor' => $proveedorAsesor,
                'contacto_id' => $contactoId,
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al conectar con ERP: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Recalcular tiempo con un número específico de operarios.
     */
    public function recalcularTiempo(Request $request, $orden, $forceDb = false)
    {
        if (!$forceDb && env('ERP_CONNECTION_MODE') === 'api') {
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
            $odcData = DB::connection('sqlsrv')->select("SELECT c_DOCUMENTO FROM MA_ODC WHERE c_DOCUMENTO IN (?, ?, ?)", [$orden, $ordenLimpia, $ordenPad]);
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
}
