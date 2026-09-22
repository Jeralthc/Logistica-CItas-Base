<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class KpiLogisticaController extends Controller
{
    /**
     * Dashboard Ejecutivo de KPIs y Métricas Logísticas Internacionales
     */
    public function index(Request $request)
    {
        $rango = $request->get('rango', 'mes'); // hoy, semana, mes, todo

        $query = DB::table('appointments');

        if ($rango === 'hoy') {
            $query->whereDate('fecha_cita', Carbon::today());
        } elseif ($rango === 'semana') {
            $query->whereBetween('fecha_cita', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
        } elseif ($rango === 'mes') {
            $query->whereMonth('fecha_cita', Carbon::now()->month)
                  ->whereYear('fecha_cita', Carbon::now()->year);
        }

        $citas = $query->get();

        $totalCitas = $citas->count();
        $completadas = $citas->where('estatus', 'finalizada')->count();
        $canceladas = $citas->where('estatus', 'cancelada')->count();
        $pendientes = $citas->where('estatus', 'programada')->count();

        // Cálculo de OTIF (On-Time In-Full)
        // Cita a tiempo: completada y con fecha de recepción registrada
        $tasaCumplimiento = $totalCitas > 0 ? round(($completadas / $totalCitas) * 100, 1) : 100;

        // Tiempos promedio de estadía (Dwell Time)
        // Tiempo promedio en muelle (en minutos)
        $duracionPromedio = $completadas > 0 ? round($citas->where('estatus', 'finalizada')->avg('duracion_minutos') ?: 45) : 45;

        // Ranking / Scorecard de Proveedores
        $proveedores = $citas->groupBy('proveedor')->map(function ($items, $prov) {
            $total = $items->count();
            $comp = $items->where('estatus', 'finalizada')->count();
            $tasa = $total > 0 ? round(($comp / $total) * 100) : 0;
            $estrellas = $tasa >= 90 ? 5 : ($tasa >= 75 ? 4 : ($tasa >= 50 ? 3 : 2));

            return [
                'proveedor' => $prov,
                'rif' => $items->first()->rif_proveedor ?? 'N/A',
                'citas_totales' => $total,
                'citas_completadas' => $comp,
                'tasa_efectividad' => $tasa,
                'calificacion' => $estrellas,
            ];
        })->values()->sortByDesc('citas_totales')->take(15)->values();

        // Ocupación por muelle
        $ocupacionMuelles = $citas->groupBy('muelle_asignado')->map(function ($items, $muelle) {
            return [
                'muelle' => $muelle ?: 'Sin Muelle',
                'total' => $items->count(),
                'completadas' => $items->where('estatus', 'finalizada')->count(),
            ];
        })->values();

        return Inertia::render('KpisLogistica', [
            'metricas' => [
                'total_citas' => $totalCitas,
                'completadas' => $completadas,
                'pendientes' => $pendientes,
                'canceladas' => $canceladas,
                'otif_porcentaje' => $tasaCumplimiento,
                'dwell_time_promedio' => $duracionPromedio,
            ],
            'proveedoresRanking' => $proveedores,
            'ocupacionMuelles' => $ocupacionMuelles,
            'rangoSeleccionado' => $rango,
        ]);
    }
}