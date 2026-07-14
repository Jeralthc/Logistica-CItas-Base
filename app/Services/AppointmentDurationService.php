<?php

namespace App\Services;

use App\Models\CategoriaRendimiento;
use Illuminate\Support\Facades\Log;

class AppointmentDurationService
{
    /**
     * Calcula la duración de muelle en minutos enteros.
     * T_total = T_fijo + (C_ton / V_descarga) + T_adicional
     *
     * @param string|int|null $categoria  ID o Nombre de la categoría
     * @param float  $pesoToneladas    Peso real de la carga en toneladas
     * @param string $formatoCarga     'paletizada' o 'suelta'
     * @param int    $tiempoAdicional  Minutos extra (espera, doble revisión, etc.)
     * @return int   Duración total en minutos (mínimo 30)
     */
    public static function calcular(
        $categoria = null,
        float $pesoToneladas = 0,
        string $formatoCarga = 'suelta',
        int $tiempoAdicional = 0
    ): int {
        // Valores por defecto
        $tFijo = 20;
        $vDescarga = 0.06;
        $categoriaId = null;

        // Si es paletizada, intentamos buscar "Carga Paletizada General", si no, usamos la categoría seleccionada
        if (strtolower($formatoCarga) === 'paletizada') {
            $cat = CategoriaRendimiento::where('nombre', 'Carga Paletizada General')->first();
            if (!$cat && $categoria) {
                $cat = is_numeric($categoria)
                    ? CategoriaRendimiento::find($categoria)
                    : CategoriaRendimiento::where('nombre', $categoria)->first();
            }
        } else if ($categoria) {
            // Buscar por ID o por Nombre
            if (is_numeric($categoria)) {
                $cat = CategoriaRendimiento::find($categoria);
            } else {
                $cat = CategoriaRendimiento::where('nombre', $categoria)->first();
            }
        } else {
            $cat = null;
        }

        if ($cat) {
            $tFijo = $cat->tiempo_fijo;
            $vDescarga = $cat->velocidad_descarga;
            $categoriaId = $cat->id;
        } else {
            Log::warning("AppointmentDurationService: Categoría no encontrada o no proporcionada. Usando fallback.");
        }

        $tiempoDescarga = $vDescarga > 0 ? ($pesoToneladas / $vDescarga) : 0;
        $total = (int) ceil($tFijo + $tiempoDescarga + $tiempoAdicional);

        return max(30, $total); // Nunca menos de 30 minutos
    }

    /**
     * Detecta la categoría de rendimiento basándose en el JSON de resumen del ERP.
     */
    public static function detectarCategoria(array $resumenErp): string
    {
        // El ERP Sync devuelve banderas 1 o 0
        if (!empty($resumenErp['es_fruver']) && $resumenErp['es_fruver'] == 1) {
            return 'Perecederos 3 (Frutas y Verduras)';
        }
        if (!empty($resumenErp['es_perecederos']) && $resumenErp['es_perecederos'] == 1) {
            return 'Perecederos 1 (Charcuteria)';
        }
        if (!empty($resumenErp['es_secos']) && $resumenErp['es_secos'] == 1) {
            return 'Alimentos 1 (Viveres)';
        }

        // Fallback
        return 'Alimentos 1 (Viveres)';
    }

    /**
     * Estima el peso en toneladas a partir del resumen del ERP.
     */
    public static function estimarPesoToneladas(array $resumenErp): float
    {
        $kgTotales = 0;

        // 1. Kilos (Usar total_kg si existe, sino sumar parciales)
        if (!empty($resumenErp['total_kg']) && (float)$resumenErp['total_kg'] > 0) {
            $kgTotales += (float) $resumenErp['total_kg'];
        } else {
            $camposKg = [
                'total_kg_frutas', 'total_kg_verduras', 'total_kg_hortalizas',
                'total_kg_carnes', 'total_kg_charcuteria', 'total_kg_pescaderia', 'total_kg_congelados'
            ];
            foreach ($camposKg as $campo) {
                if (!empty($resumenErp[$campo])) {
                    $kgTotales += (float) $resumenErp[$campo];
                }
            }
        }

        // 2. Unidades (Usar total_und si existe, sino sumar parciales, asumiendo 1kg por unidad)
        if (!empty($resumenErp['total_und']) && (float)$resumenErp['total_und'] > 0) {
            $kgTotales += (float) $resumenErp['total_und'] * 1;
        } else {
            $camposUnd = [
                'total_und_frutas', 'total_und_verduras', 'total_und_hortalizas',
                'total_und_carnes', 'total_und_charcuteria', 'total_und_pescaderia', 'total_und_congelados'
            ];
            foreach ($camposUnd as $campo) {
                if (!empty($resumenErp[$campo])) {
                    $kgTotales += (float) $resumenErp[$campo] * 1;
                }
            }
        }

        // 3. Bultos (Estimación gruesa de 15kg por bulto si no hay pesos exactos)
        // Nota: Solo sumamos bultos si son la unica medida disponible, o si es mercancia seca (viveres)
        // Pero para no duplicar con total_kg que a veces incluye el peso de los bultos, 
        // sumamos bultos solo si $kgTotales es 0.
        if ($kgTotales == 0 && !empty($resumenErp['total_bultos'])) {
            $kgTotales += (float) $resumenErp['total_bultos'] * 15;
        }

        return round($kgTotales / 1000, 3); // Convertir a toneladas
    }
}
