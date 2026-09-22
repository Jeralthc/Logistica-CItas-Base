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
        try {
            $resultado = $this->ocrService->analizarFacturaCita((int)$id);
            return response()->json($resultado);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'error' => $e->getMessage()
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

        return response()->json([
            'status' => 'success',
            'estatus_conciliacion' => $analisis->estatus_conciliacion,
            'resumen_discrepancias' => $analisis->resumen_discrepancias,
            'total_factura' => $analisis->total_factura_extraido,
            'total_odc' => $analisis->total_odc,
            'diferencia_total' => $analisis->diferencia_total,
            'numero_factura' => $analisis->numero_factura_extraido,
            'renglones' => json_decode($analisis->conciliacion_json, true) ?: [],
            'fecha_analisis' => $analisis->updated_at,
        ]);
    }
}
