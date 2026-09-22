<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Carbon\Carbon;

class GaritaController extends Controller
{
    /**
     * Muestra la pantalla principal de Garita y Control de Patio (YMS)
     */
    public function index(Request $request)
    {
        $fecha = $request->get('fecha', Carbon::today()->toDateString());

        // Obtener citas del día con datos de chofer y patio
        $citas = DB::table('appointments')
            ->whereDate('fecha_cita', $fecha)
            ->select([
                'id',
                'numero_oc',
                'proveedor',
                'rif_proveedor',
                'fecha_cita',
                'muelle_asignado',
                'estatus',
                'estado_patio',
                'qr_token',
                'fecha_llegada_garita',
                'fecha_llamado_muelle',
                'fecha_inicio_descarga',
                'fecha_completada',
                'fecha_salida',
                'chofer_nombre',
                'chofer_cedula',
                'chofer_telefono',
                'placa_vehiculo',
                'garita_observaciones',
                'tipo_vehiculo',
                'formato_carga'
            ])
            ->orderBy('fecha_cita', 'asc')
            ->get();

        // Estadísticas rápidas para el dashboard de garita
        $metricas = [
            'total_hoy' => $citas->count(),
            'esperando_llegada' => $citas->where('estado_patio', 'programada')->count(),
            'en_patio' => $citas->whereIn('estado_patio', ['en_garita', 'en_patio'])->count(),
            'en_muelle' => $citas->where('estado_patio', 'en_muelle')->count(),
            'finalizadas' => $citas->whereIn('estado_patio', ['finalizada', 'salida'])->count(),
        ];

        return Inertia::render('Garita', [
            'citas' => $citas,
            'metricas' => $metricas,
            'fechaFiltro' => $fecha,
        ]);
    }

    /**
     * Valida un código QR escaneado en la garita
     */
    public function validarQr(Request $request)
    {
        $codigo = trim($request->get('codigo'));

        if (!$codigo) {
            return response()->json(['error' => 'Código no proporcionado'], 400);
        }

        $cita = DB::table('appointments')
            ->where('qr_token', $codigo)
            ->orWhere('numero_oc', $codigo)
            ->orWhere('id', is_numeric($codigo) ? (int)$codigo : 0)
            ->first();

        if (!$cita) {
            return response()->json(['error' => 'No se encontró ninguna cita asociada a este código QR o número de ODC'], 404);
        }

        return response()->json([
            'status' => 'success',
            'cita' => $cita
        ]);
    }

    /**
     * Registra el Check-in de llegada en Garita
     */
    public function registrarEntrada(Request $request, $id)
    {
        $request->validate([
            'chofer_nombre' => 'nullable|string|max:100',
            'chofer_cedula' => 'nullable|string|max:30',
            'chofer_telefono' => 'nullable|string|max:30',
            'placa_vehiculo' => 'nullable|string|max:30',
            'garita_observaciones' => 'nullable|string|max:500',
        ]);

        $cita = DB::table('appointments')->where('id', $id)->first();
        if (!$cita) {
            return response()->json(['error' => 'Cita no encontrada'], 404);
        }

        DB::table('appointments')->where('id', $id)->update([
            'estado_patio' => 'en_patio',
            'fecha_llegada_garita' => Carbon::now(),
            'chofer_nombre' => $request->get('chofer_nombre', $cita->chofer_nombre),
            'chofer_cedula' => $request->get('chofer_cedula', $cita->chofer_cedula),
            'chofer_telefono' => $request->get('chofer_telefono', $cita->chofer_telefono),
            'placa_vehiculo' => $request->get('placa_vehiculo', $cita->placa_vehiculo),
            'garita_observaciones' => $request->get('garita_observaciones'),
            'garita_user_id' => Auth::id(),
            'updated_at' => Carbon::now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Check-in en garita registrado con éxito. Camión en espera en patio.'
        ]);
    }

    /**
     * Llama al camión del patio hacia un muelle específico
     */
    public function llamarMuelle(Request $request, $id)
    {
        $request->validate([
            'muelle' => 'required|string',
        ]);

        DB::table('appointments')->where('id', $id)->update([
            'estado_patio' => 'en_muelle',
            'muelle_asignado' => $request->get('muelle'),
            'fecha_llamado_muelle' => Carbon::now(),
            'fecha_inicio_descarga' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Camión asignado y llamado al ' . $request->get('muelle') . ' para descarga.'
        ]);
    }

    /**
     * Registra la salida física del camión de las instalaciones
     */
    public function registrarSalida(Request $request, $id)
    {
        DB::table('appointments')->where('id', $id)->update([
            'estado_patio' => 'salida',
            'fecha_salida' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Salida de camión registrada. Proceso de patio concluido.'
        ]);
    }
}