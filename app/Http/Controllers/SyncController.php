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
                
                $insertData[] = [
                    'numero_oc' => $orden['numero_oc'],
                    'fecha_emision' => $orden['fecha_emision'] ?? null,
                    'fecha_recepcion' => $orden['fecha_recepcion'] ?? null,
                    'proveedor' => $orden['proveedor'] ?? null,
                    'destino' => $orden['destino'] ?? null,
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
            // IMPORTANTE: NO actualizar 'estatus_habilitacion' ni 'resumen_json' si ya existe para no borrar el RIF insertado
            foreach (array_chunk($insertData, 100) as $chunk) {
                DB::table('erp_ordenes_sync')->upsert($chunk, ['numero_oc'], [
                    'fecha_emision', 
                    'fecha_recepcion', 
                    'proveedor', 
                    'destino', 
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
