<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\OcrInvoiceService;

class OcrInvoiceController extends Controller
{
    protected OcrInvoiceService $ocrService;

    public function __construct(OcrInvoiceService $ocrService)
    {
        $this->ocrService = $ocrService;
    }

    /**
     * Dispara el análisis OCR de la factura adjunta a la cita y la concilia contra la ODC
     */
    public function analizar(Request $request, $id)
    {
        $user = $request->user();
        $esAdmin = $user && (
            $user->role === 'admin' ||
            strtolower($user->username ?? '') === 'sistemas.jeralthc' ||
            str_contains(strtolower($user->username ?? ''), 'sistemas') ||
            str_contains(strtolower($user->email ?? ''), 'sistemas') ||
            str_contains(strtolower($user->name ?? ''), 'sistemas')
        );

        try {
            $resultado = $this->ocrService->analizarFacturaCita((int)$id);
            return response()->json($resultado);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Error en análisis OCR para cita #{$id}: " . $e->getMessage(), [
                'usuario' => $user?->username ?? 'anónimo',
                'trace' => $e->getTraceAsString()
            ]);

            $mensajePublico = 'El servicio de conciliación automática no está disponible en este momento. Por favor realice la verificación de la factura de forma manual.';

            return response()->json([
                'status' => 'error',
                'error' => $esAdmin ? $e->getMessage() : $mensajePublico,
                'es_admin' => $esAdmin,
                'admin_detail' => $esAdmin ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Consulta la conciliación OCR ya guardada para una cita
     */
    public function obtener($id)
    {
        $analisis = DB::table('invoice_ocr_analyses')
            ->where('appointment_id', $id)
            ->first();

        if (!$analisis) {
            return response()->json([
                'status' => 'sin_analisis',
                'mensaje' => 'Esta cita aún no ha sido analizada con OCR.'
            ]);
        }

        $totalFac = floatval($analisis->total_factura_extraido ?? 0);
        $totalOdc = floatval($analisis->total_odc ?? 0);
        $monedasDifieren = ($totalFac > 0 && $totalOdc > 0 && $totalFac > ($totalOdc * 10));

        return response()->json([
            'status' => 'success',
            'estatus_conciliacion' => $analisis->estatus_conciliacion,
            'resumen_discrepancias' => $analisis->resumen_discrepancias,
            'total_factura' => $analisis->total_factura_extraido,
            'total_odc' => $analisis->total_odc,
            'diferencia_total' => $analisis->diferencia_total,
            'moneda_factura' => $monedasDifieren ? 'VES' : 'USD',
            'monedas_difieren' => $monedasDifieren,
            'numero_factura' => $analisis->numero_factura_extraido,
            'renglones' => json_decode($analisis->conciliacion_json, true) ?: [],
            'fecha_analisis' => $analisis->updated_at,
        ]);
    }
}
