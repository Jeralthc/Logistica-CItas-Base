<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class OcrInvoiceService
{
    /**
     * Analiza la factura adjunta a una cita mediante OCR con IA y la concilia contra la ODC
     */
    public function analizarFacturaCita(int $appointmentId)
    {
        $cita = DB::table('appointments')->where('id', $appointmentId)->first();
        if (!$cita) {
            throw new \Exception("Cita #{$appointmentId} no encontrada.");
        }

        if (empty($cita->factura_path)) {
            throw new \Exception("La cita #{$appointmentId} no tiene una factura adjunta para analizar.");
        }

        // Obtener ruta física del archivo (si son múltiples, tomar la primera para análisis o el archivo principal)
        $pathRelativo = trim($cita->factura_path);
        if (str_starts_with($pathRelativo, '[')) {
            $decoded = json_decode($pathRelativo, true);
            $pathRelativo = $decoded[0] ?? $pathRelativo;
        }
        $disco = Storage::disk('public');

        if (!$disco->exists($pathRelativo)) {
            throw new \Exception("El archivo de la factura no existe en el almacenamiento: {$pathRelativo}");
        }

        $contenidoArchivo = $disco->get($pathRelativo);
        $mimeType = $disco->mimeType($pathRelativo) ?: 'application/pdf';

        // 1. Extraer datos de la factura con Gemini Vision
        $datosFactura = $this->extraerDatosConGemini($contenidoArchivo, $mimeType);

        // 2. Obtener datos de la Orden de Compra desde erp_ordenes_sync
        $datosOdc = $this->obtenerArticulosOdc($cita->numero_oc);

        // 3. Ejecutar motor de conciliación ODC vs Factura
        $conciliacion = $this->conciliarOdcConFactura($datosOdc, $datosFactura);

        // 4. Guardar o actualizar resultado en invoice_ocr_analyses
        $analysisId = DB::table('invoice_ocr_analyses')->updateOrInsert(
            ['appointment_id' => $appointmentId],
            [
                'numero_oc' => $cita->numero_oc,
                'numero_factura_extraido' => $datosFactura['numero_factura'] ?? $cita->numero_factura,
                'rif_emisor_extraido' => $datosFactura['rif_emisor'] ?? $cita->rif_proveedor,
                'nombre_emisor_extraido' => $datosFactura['nombre_emisor'] ?? $cita->proveedor,
                'fecha_emision_extraida' => !empty($datosFactura['fecha_emision']) ? Carbon::parse($datosFactura['fecha_emision'])->toDateString() : null,
                'subtotal_extraido' => $datosFactura['subtotal'] ?? 0,
                'iva_extraido' => $datosFactura['iva'] ?? 0,
                'total_factura_extraido' => $datosFactura['total'] ?? 0,
                'total_odc' => $datosOdc['total_monto'] ?? 0,
                'diferencia_total' => $conciliacion['diferencia_total'] ?? 0,
                'estatus_conciliacion' => $conciliacion['estatus_general'],
                'resumen_discrepancias' => mb_substr($conciliacion['resumen_texto'] ?? '', 0, 250),
                'datos_factura_json' => json_encode($datosFactura['articulos'] ?? []),
                'conciliacion_json' => json_encode($conciliacion['renglones'] ?? []),
                'raw_text' => $datosFactura['raw_text'] ?? null,
                'updated_at' => Carbon::now(),
                'created_at' => Carbon::now(),
            ]
        );

        return [
            'status' => 'success',
            'estatus_conciliacion' => $conciliacion['estatus_general'],
            'resumen_discrepancias' => $conciliacion['resumen_texto'],
            'datos_factura' => $datosFactura,
            'datos_odc' => $datosOdc,
            'conciliacion' => $conciliacion,
        ];
    }

    /**
     * Extrae información estructurada de la factura usando Google Gemini Vision
     */
    protected function extraerDatosConGemini($contenidoArchivo, string $mimeType)
    {
        $apiKey = config('services.gemini.key') ?: env('GEMINI_API_KEY') ?: 'AIzaSyCQJzs684K66o6leOS0c3rUtjHprkkDlrA';

        if (!$apiKey) {
            throw new \Exception("No se ha configurado la clave de API de Gemini.");
        }

        $base64Data = base64_encode($contenidoArchivo);

        $prompt = <<<PROMPT
Eres un sistema experto en auditoría fiscal, contable y logística de facturas comerciales.
Analiza la factura adjunta (imagen o documento PDF) y extrae ÚNICAMENTE un objeto JSON válido con los datos de la factura.
NO agregues explicaciones, NO agregues formato markdown como ```json ... ```, devuelve ÚNICAMENTE texto JSON puro:

{
  "numero_factura": "string con el número o control de factura",
  "fecha_emision": "YYYY-MM-DD",
  "rif_emisor": "string con el RIF, NIT o CUIT del emisor",
  "nombre_emisor": "string con la razón social del proveedor",
  "subtotal": 0.00,
  "iva": 0.00,
  "total": 0.00,
  "articulos": [
    {
      "codigo": "string con código de producto o null",
      "descripcion": "descripción clara del producto",
      "cantidad": 0.00,
      "precio_unitario": 0.00,
      "total_renglon": 0.00
    }
  ]
}
PROMPT;

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$apiKey}";

        $response = Http::timeout(45)->post($url, [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                        [
                            'inline_data' => [
                                'mime_type' => $mimeType,
                                'data' => $base64Data,
                            ]
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'response_mime_type' => 'application/json',
            ]
        ]);

        if (!$response->successful()) {
            Log::error("Error Gemini OCR API: " . $response->body());
            throw new \Exception("Error al comunicarse con el motor OCR de IA: " . $response->status() . " " . $response->body());
        }

        $json = $response->json();
        $textoGenerado = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';

        // Limpiar posible formato markdown residual
        $textoLimpio = trim(str_replace(['```json', '```'], '', $textoGenerado));

        $datosDecodificados = json_decode($textoLimpio, true);
        if (!$datosDecodificados) {
            Log::warning("Fallo al decodificar JSON de Gemini: " . $textoLimpio);
            throw new \Exception("La IA no devolvió un formato JSON estructurado válido para esta factura.");
        }

        $datosDecodificados['raw_text'] = $textoLimpio;
        return $datosDecodificados;
    }

    /**
     * Obtiene los artículos y montos de la ODC desde erp_ordenes_sync
     */
    protected function obtenerArticulosOdc(string $numeroOc)
    {
        $ordenLimpia = preg_replace('/[^0-9]/', '', $numeroOc);
        $ordenPad = str_pad($ordenLimpia, 9, '0', STR_PAD_LEFT);
        $ordenConE = 'E' . $ordenPad;

        $row = DB::table('erp_ordenes_sync')
            ->whereIn('numero_oc', [$numeroOc, $ordenLimpia, $ordenPad, $ordenConE])
            ->first();

        $articulos = [];
        $totalMonto = 0;

        if ($row) {
            $detalles = json_decode($row->detalles_json, true) ?: [];
            if (empty($detalles) && !empty($row->articulos_json)) {
                $detalles = json_decode($row->articulos_json, true) ?: [];
            }

            foreach ($detalles as $d) {
                $cant = floatval($d['cantidad_unidades'] ?? $d['n_CANTIDAD'] ?? $d['cantidad'] ?? $d['bultos'] ?? 0);
                $precio = floatval($d['costo_unitario'] ?? $d['n_PRECIO'] ?? $d['precio_unitario'] ?? $d['precio'] ?? 0);
                $total = floatval($d['subtotal'] ?? $d['n_TOTAL'] ?? ($cant * $precio));
                $totalMonto += $total;

                $desc = trim($d['producto'] ?? $d['c_DESCRIPCIO'] ?? $d['descripcion'] ?? '');
                if (empty($desc)) {
                    $desc = 'Artículo sin descripción';
                }

                $articulos[] = [
                    'codigo' => trim($d['codigo'] ?? $d['c_CODARTICULO'] ?? ''),
                    'descripcion' => $desc,
                    'cantidad' => $cant,
                    'precio_unitario' => $precio,
                    'total' => $total,
                ];
            }

            if ($totalMonto == 0 && !empty($row->monto_total)) {
                $totalMonto = floatval($row->monto_total);
            }
        }

        return [
            'numero_oc' => $numeroOc,
            'total_monto' => $totalMonto,
            'articulos' => $articulos,
        ];
    }

    /**
     * Motor de conciliación renglón por renglón entre ODC y Factura
     * Usa matching multi-estrategia para emparejar productos de distintos sistemas
     */
    protected function conciliarOdcConFactura(array $datosOdc, array $datosFactura)
    {
        $itemsOdc = $datosOdc['articulos'] ?? [];
        $itemsFactura = $datosFactura['articulos'] ?? [];

        $renglonesComparados = [];
        $itemsFacturaUsados = [];
        $hayDiscrepancias = false;
        $motivosDiscrepancia = [];

        // 1. Recorrer cada ítem de la ODC y buscar su match en la factura
        foreach ($itemsOdc as $idxOdc => $itemOdc) {
            $codOdc = strtoupper(trim($itemOdc['codigo'] ?? ''));
            $descOdc = mb_strtoupper(trim($itemOdc['descripcion'] ?? ''));
            $cantOdc = floatval($itemOdc['cantidad'] ?? 0);

            $matchFactura = null;
            $matchIndex = null;
            $mejorScore = 0;

            foreach ($itemsFactura as $idxF => $itemF) {
                if (in_array($idxF, $itemsFacturaUsados)) continue;
                
                $codF = strtoupper(trim($itemF['codigo'] ?? ''));
                $descF = mb_strtoupper(trim($itemF['descripcion'] ?? ''));
                $score = 0;

                // Estrategia 1: Código exacto (100 puntos)
                if (!empty($codOdc) && !empty($codF) && $codF === $codOdc) {
                    $score = 100;
                }

                // Estrategia 2: Código contenido en el otro (70 puntos)
                if ($score < 70 && !empty($codOdc) && !empty($codF)) {
                    if (str_contains($codF, $codOdc) || str_contains($codOdc, $codF)) {
                        $score = max($score, 70);
                    }
                }

                // Estrategia 3: similar_text en descripción
                if ($score < 60 && !empty($descOdc) && !empty($descF)) {
                    similar_text($descOdc, $descF, $pct);
                    if ($pct >= 45) {
                        $score = max($score, $pct);
                    }
                }

                // Estrategia 4: Coincidencia de palabras clave significativas
                if ($score < 50 && !empty($descOdc) && !empty($descF)) {
                    $kwScore = $this->calcularScorePalabras($descOdc, $descF);
                    if ($kwScore >= 35) {
                        $score = max($score, $kwScore);
                    }
                }

                // Estrategia 5: Match por dimensión (120ML, 500GR, etc.)
                if ($score < 40 && !empty($descOdc) && !empty($descF)) {
                    $dimOdc = $this->extraerDimensiones($descOdc);
                    $dimF = $this->extraerDimensiones($descF);
                    if (!empty($dimOdc) && !empty($dimF)) {
                        $dimComunes = array_intersect($dimOdc, $dimF);
                        if (count($dimComunes) > 0) {
                            $score = max($score, 35 + (count($dimComunes) * 10));
                        }
                    }
                }

                if ($score > $mejorScore && $score >= 35) {
                    $mejorScore = $score;
                    $matchFactura = $itemF;
                    $matchIndex = $idxF;
                    if ($score >= 100) break;
                }
            }

            if ($matchFactura) {
                $itemsFacturaUsados[] = $matchIndex;
                $cantFactura = floatval($matchFactura['cantidad'] ?? 0);
                $diff = round($cantFactura - $cantOdc, 2);

                if ($diff == 0) {
                    $estado = 'coincide';
                } elseif ($diff < 0) {
                    $estado = 'faltante';
                    $hayDiscrepancias = true;
                    $motivosDiscrepancia[] = "Faltan " . abs($diff) . " und de '{$itemOdc['descripcion']}'";
                } else {
                    $estado = 'excedente';
                    $hayDiscrepancias = true;
                    $motivosDiscrepancia[] = "Excedente de +{$diff} und en '{$itemOdc['descripcion']}'";
                }

                $renglonesComparados[] = [
                    'codigo_odc' => $itemOdc['codigo'],
                    'descripcion_odc' => $itemOdc['descripcion'],
                    'cantidad_odc' => $cantOdc,
                    'codigo_factura' => $matchFactura['codigo'] ?? null,
                    'descripcion_factura' => $matchFactura['descripcion'] ?? null,
                    'cantidad_factura' => $cantFactura,
                    'diferencia' => $diff,
                    'estado' => $estado,
                ];
            } else {
                $hayDiscrepancias = true;
                $motivosDiscrepancia[] = "No vino facturado: '{$itemOdc['descripcion']}' ({$cantOdc} und)";
                $renglonesComparados[] = [
                    'codigo_odc' => $itemOdc['codigo'],
                    'descripcion_odc' => $itemOdc['descripcion'],
                    'cantidad_odc' => $cantOdc,
                    'codigo_factura' => null,
                    'descripcion_factura' => null,
                    'cantidad_factura' => 0,
                    'diferencia' => -$cantOdc,
                    'estado' => 'no_facturado',
                ];
            }
        }

        // 2. Ítems en factura que no matchearon con nada de la ODC
        foreach ($itemsFactura as $idxF => $itemF) {
            if (!in_array($idxF, $itemsFacturaUsados)) {
                $hayDiscrepancias = true;
                $cantF = floatval($itemF['cantidad'] ?? 0);
                $descF = $itemF['descripcion'] ?? 'Producto no identificado';
                $motivosDiscrepancia[] = "Producto NO pedido en ODC: '{$descF}' ({$cantF} und)";

                $renglonesComparados[] = [
                    'codigo_odc' => null,
                    'descripcion_odc' => null,
                    'cantidad_odc' => 0,
                    'codigo_factura' => $itemF['codigo'] ?? null,
                    'descripcion_factura' => $descF,
                    'cantidad_factura' => $cantF,
                    'diferencia' => $cantF,
                    'estado' => 'no_solicitado',
                ];
            }
        }

        // 3. Comparar totales monetarios
        $totalOdc = floatval($datosOdc['total_monto'] ?? 0);
        $totalFactura = floatval($datosFactura['total'] ?? 0);
        $diffTotal = round($totalFactura - $totalOdc, 2);

        if (abs($diffTotal) > 0.50 && $totalOdc > 0) {
            $hayDiscrepancias = true;
            $motivosDiscrepancia[] = "Diferencia de monto total: Factura $" . number_format($totalFactura, 2) . " vs ODC $" . number_format($totalOdc, 2);
        }

        $estatusGeneral = $hayDiscrepancias ? 'discrepancia' : 'conforme';
        $resumenTexto = $hayDiscrepancias 
            ? implode(' · ', array_slice($motivosDiscrepancia, 0, 4))
            : 'Factura 100% conforme y cuadrada con la Orden de Compra.';

        return [
            'estatus_general' => $estatusGeneral,
            'diferencia_total' => $diffTotal,
            'total_odc' => $totalOdc,
            'total_factura' => $totalFactura,
            'resumen_texto' => $resumenTexto,
            'renglones' => $renglonesComparados,
            'discrepancias_lista' => $motivosDiscrepancia,
        ];
    }

    /**
     * Calcula score de coincidencia basado en palabras clave compartidas
     */
    protected function calcularScorePalabras(string $desc1, string $desc2): float
    {
        $stopWords = ['DE', 'LA', 'EL', 'EN', 'CON', 'SIN', 'POR', 'PARA', 'UND', 'USO', 'INTERNO', 'C/', 'Y', 'A', 'X'];
        
        $words1 = array_diff(preg_split('/[\s\/\-\(\)\.,]+/', $desc1), $stopWords, ['']);
        $words2 = array_diff(preg_split('/[\s\/\-\(\)\.,]+/', $desc2), $stopWords, ['']);
        
        if (empty($words1) || empty($words2)) return 0;

        $coincidencias = 0;
        foreach ($words1 as $w1) {
            if (mb_strlen($w1) < 3) continue;
            foreach ($words2 as $w2) {
                if (mb_strlen($w2) < 3) continue;
                if ($w1 === $w2 || (mb_strlen($w1) >= 4 && str_contains($w2, $w1)) || (mb_strlen($w2) >= 4 && str_contains($w1, $w2))) {
                    $coincidencias++;
                    break;
                }
            }
        }

        $totalPalabras = max(count($words1), count($words2));
        return ($coincidencias / $totalPalabras) * 100;
    }

    /**
     * Extrae dimensiones normalizadas (volumen, peso) de una descripción
     */
    protected function extraerDimensiones(string $desc): array
    {
        $dims = [];
        $desc = preg_replace('/(\d+)\s*(ML|CC|GR|KG|LT|L|OZ|UND|MT)\b/i', '$1$2', $desc);
        
        if (preg_match_all('/(\d+(?:\.\d+)?)\s*(ML|CC|GR|KG|LT|L|OZ|MT)\b/i', $desc, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $val = floatval($m[1]);
                $unit = strtoupper($m[2]);
                if ($unit === 'CC') $unit = 'ML';
                if ($unit === 'LT') $unit = 'L';
                if ($unit === 'L' && $val < 100) { $val *= 1000; $unit = 'ML'; }
                $dims[] = $val . $unit;
            }
        }
        return $dims;
    }
}
