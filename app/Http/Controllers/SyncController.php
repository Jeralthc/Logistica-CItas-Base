<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SyncController extends Controller
{
    public function recibir(Request $request)
    {
        // Validar token de seguridad
        $tokenEsperado = env('ERP_API_TOKEN');
        if ($tokenEsperado && $request->bearerToken() !== $tokenEsperado) {
            return response()->json(['error' => 'No autorizado'], 401);
        }

        $ordenes = $request->input('ordenes', []);

        try {
            DB::beginTransaction();

            $esPrimerChunk = $request->input('es_primer_chunk', true);

            if ($esPrimerChunk) {
                // Solo vaciamos la tabla vieja cuando empezamos a recibir el primer lote nuevo
                // IMPORTANTE: NO BORRAR LAS ÓRDENES HABILITADAS NI AGENDADAS PARA NO PERDER HISTORIAL NI ACCESO
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
                
                $rifVal = $resumen['Codigo_Proveedor'] 
                    ?? $resumen['c_rif'] 
                    ?? $resumen['c_CODPROVEEDOR'] 
                    ?? $resumen['c_codproveed'] 
                    ?? ($orden['rif_proveedor'] ?? null);
                $rifLimpio = $rifVal ? strtoupper(preg_replace('/[^A-Z0-9]/i', '', trim($rifVal))) : null;

                $insertData[] = [
                    'numero_oc' => $orden['numero_oc'],
                    'rif_proveedor' => $rifLimpio,
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

            // Insertamos (o actualizamos si ya existe)
            // IMPORTANTE: NO sobrescribir 'estatus_habilitacion' si ya fue habilitada
            foreach (array_chunk($insertData, 100) as $chunk) {
                DB::table('erp_ordenes_sync')->upsert($chunk, ['numero_oc'], [
                    'rif_proveedor',
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
