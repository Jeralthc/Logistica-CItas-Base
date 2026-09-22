<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class EpodController extends Controller
{
    /**
     * Guarda el Acta Digital de Recepción (e-POD) con firma del chofer y fotos
     */
    public function guardarEpod(Request $request, $id)
    {
        $request->validate([
            'chofer_firma' => 'required|string', // base64 PNG
            'chofer_nombre' => 'nullable|string|max:100',
            'chofer_cedula' => 'nullable|string|max:30',
            'estado_mercancia' => 'required|in:conforme,discrepancia,danada',
            'observaciones_recepcion' => 'nullable|string|max:1000',
            'fotos' => 'nullable|array', // array de strings base64 o URLs
        ]);

        $cita = DB::table('appointments')->where('id', $id)->first();
        if (!$cita) {
            return response()->json(['error' => 'Cita no encontrada.'], 404);
        }

        $recepcionistaNombre = Auth::user() ? Auth::user()->name : 'Recepcionista';

        // Guardar o actualizar registro e-POD
        DB::table('epod_receptions')->updateOrInsert(
            ['appointment_id' => $id],
            [
                'chofer_firma_base64' => $request->get('chofer_firma'),
                'chofer_nombre' => $request->get('chofer_nombre', $cita->chofer_nombre),
                'chofer_cedula' => $request->get('chofer_cedula', $cita->chofer_cedula),
                'recepcionista_nombre' => $recepcionistaNombre,
                'recepcionista_user_id' => Auth::id(),
                'estado_mercancia' => $request->get('estado_mercancia'),
                'observaciones_recepcion' => $request->get('observaciones_recepcion'),
                'fotos_evidencia' => json_encode($request->get('fotos', [])),
                'fecha_firma' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]
        );

        // Si la cita aún no estaba finalizada, la finaliza automáticamente
        if ($cita->estatus !== 'finalizada') {
            DB::table('appointments')->where('id', $id)->update([
                'estatus' => 'finalizada',
                'estado_patio' => 'finalizada',
                'fecha_completada' => Carbon::now(),
                'completada_por_nombre' => $recepcionistaNombre,
                'completada_por_user_id' => Auth::id(),
                'updated_at' => Carbon::now(),
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Acta digital de entrega (e-POD) firmada y guardada exitosamente.',
        ]);
    }

    /**
     * Consulta el detalle del e-POD de una cita
     */
    public function obtenerEpod($id)
    {
        $epod = DB::table('epod_receptions')->where('appointment_id', $id)->first();
        $cita = DB::table('appointments')->where('id', $id)->first();

        if (!$cita) {
            return response()->json(['error' => 'Cita no encontrada.'], 404);
        }

        return response()->json([
            'status' => 'success',
            'cita' => $cita,
            'epod' => $epod,
            'fotos' => $epod && $epod->fotos_evidencia ? json_decode($epod->fotos_evidencia) : []
        ]);
    }
}