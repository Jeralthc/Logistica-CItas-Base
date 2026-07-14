<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ErpApiController extends Controller
{
    /**
     * Traer todas las órdenes pendientes del ERP local.
     */
    public function getOrdenesPendientes(Request $request)
    {


        try {
            $logistica = new LogisticaController();
            return $logistica->ordenesPendientes(false);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], 500);
        }
    }

    public function getOrden(Request $request, $orden)
    {


        try {
            $logistica = new LogisticaController();
            return $logistica->buscarOrdenCompleta($orden, false);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function recalcularTiempo(Request $request, $orden)
    {


        try {
            $logistica = new LogisticaController();
            return $logistica->recalcularTiempo($request, $orden, false);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
