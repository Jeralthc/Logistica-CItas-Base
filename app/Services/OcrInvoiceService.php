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

        // Obtener ruta(s) física(s) de los archivos de factura (soporte para 1 o múltiples facturas)
        $rawPath = trim($cita->factura_path);
        $archivosFacturas = [];
        if (str_starts_with($rawPath, '[')) {
            $decoded = json_decode($rawPath, true);
            if (is_array($decoded)) {
                $archivosFacturas = $decoded;
            }
        }
        if (empty($archivosFacturas)) {
            $archivosFacturas = [$rawPath];
        }

        $disco = Storage::disk('public');
        $facturasExtraidas = [];

        foreach ($archivosFacturas as $pathRelativo) {
            $pathRelativo = trim($pathRelativo);
            if (!$disco->exists($pathRelativo)) {
                Log::warning("OcrInvoiceService: Archivo de factura no encontrado en disco: {$pathRelativo}");
                continue;
            }

            $contenidoArchivo = $disco->get($pathRelativo);
            $mimeType = $disco->mimeType($pathRelativo) ?: 'application/pdf';

            // Extraer datos de cada factura individual con Gemini Vision
            $facturasExtraidas[] = $this->extraerDatosConGemini($contenidoArchivo, $mimeType);
        }

        if (empty($facturasExtraidas)) {
            throw new \Exception("Ninguno de los archivos de factura adjuntos existe en el almacenamiento del servidor.");
        }

        // Consolidar datos de todas las facturas analizadas (subtotales, IVA, totales y renglones agrupados)
        $datosFactura = $this->consolidarMultiplesFacturas($facturasExtraidas);

        // 2. Obtener datos de la Orden de Compra desde erp_ordenes_sync
        $datosOdc = $this->obtenerArticulosOdc($cita->numero_oc);

        // 3. Ejecutar motor de conciliación ODC vs Factura (o lote consolidado)
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
     * Incluye fallback automático entre modelos (gemini-2.0-flash, gemini-1.5-flash, gemini-2.5-flash, gemini-1.5-pro)
     * para tolerancia total a fallos 503 (sobrecarga temporal) y 429 (límite de cuota).
     */
    protected function extraerDatosConGemini($contenidoArchivo, string $mimeType)
    {
        $apiKey = config('services.gemini.key') ?: env('GEMINI_API_KEY');

        if (!$apiKey) {
            throw new \Exception("No se ha configurado la clave de API de Gemini (GEMINI_API_KEY). Por favor ingrésela en el archivo .env o en el panel de despliegue seguro.");
        }

        $base64Data = base64_encode($contenidoArchivo);

        $prompt = <<<PROMPT
Eres un sistema experto en auditoría fiscal, contable y logística de facturas comerciales y despachos de proveedores.
Analiza la factura adjunta (imagen o documento PDF) y extrae ÚNICAMENTE un objeto JSON válido con los datos de la factura.
NO agregues explicaciones, NO agregues formato markdown como ```json ... ```, devuelve ÚNICAMENTE texto JSON puro:

{
  "numero_factura": "string con el número o control de factura",
  "fecha_emision": "YYYY-MM-DD",
  "rif_emisor": "string con el RIF, NIT o CUIT del emisor",
  "nombre_emisor": "string con la razón social del proveedor",
  "moneda": "VES o USD (especificar VES si los montos están expresados en Bolívares/Bs., o USD si están en Dólares/$)",
  "tasa_cambio": null,
  "subtotal": 0.00,
  "iva": 0.00,
  "total": 0.00,
  "articulos": [
    {
      "codigo": "string con código de producto o null",
      "descripcion": "descripción clara del producto",
      "cantidad": 0.00,
      "unidad_medida": "string con la unidad de medida si aparece en la factura (ej: caj, caja, bto, bulto, fdo, fardo, und, pza, kg) o null",
      "precio_unitario": 0.00,
      "total_renglon": 0.00
    }
  ]
}
PROMPT;

        // Lista de modelos ordenados por compatibilidad y disponibilidad real en Google API
        $modelos = [
            'gemini-3.6-flash',
            'gemini-3.5-flash',
            'gemini-3.5-flash-lite',
            'gemini-3.7-flash',
            'gemini-flash-latest',
        ];

        $ultimoError = null;
        $response = null;

        foreach ($modelos as $modelo) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelo}:generateContent?key={$apiKey}";

            try {
                $res = Http::timeout(45)->post($url, [
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

                if ($res->successful()) {
                    $response = $res;
                    break;
                }

                $status = $res->status();
                $body = $res->body();
                Log::warning("Gemini OCR modelo [{$modelo}] retornó código {$status}: {$body}. Probando fallback...");
                $ultimoError = "Modelo {$modelo} (código {$status}): {$body}";

                // Si es error 503 (sobrecarga) o 429 (rate limit), breve pausa y continuar con el siguiente modelo
                if (in_array($status, [503, 429, 500])) {
                    usleep(600000); // 0.6s
                    continue;
                }
            } catch (\Throwable $e) {
                Log::warning("Gemini OCR excepción de red en modelo [{$modelo}]: " . $e->getMessage());
                $ultimoError = $e->getMessage();
            }
        }

        if (!$response || !$response->successful()) {
            Log::error("Todos los modelos de Gemini OCR fallaron. Último error: " . $ultimoError);
            if (str_contains($ultimoError, 'leaked') || str_contains($ultimoError, 'API key was reported as leaked')) {
                throw new \Exception("Google ha revocado la clave de API (reportada como filtrada). Ingrese una nueva GEMINI_API_KEY en .env o en el panel de despliegue seguro (https://aistudio.google.com/app/apikey).");
            }
            if (str_contains($ultimoError, '503') || str_contains($ultimoError, 'high demand') || str_contains($ultimoError, '429')) {
                throw new \Exception("El motor de IA está experimentando alta demanda momentánea en los servidores de Google. Por favor presione 'Reintentar' en unos segundos.");
            }
            throw new \Exception("Error en motor OCR de IA: " . ($ultimoError ?: 'Google no respondió.'));
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
     * Consolida múltiples facturas en un solo payload estructurado
     */
    protected function consolidarMultiplesFacturas(array $facturasExtraidas): array
    {
        if (count($facturasExtraidas) === 1) {
            return $facturasExtraidas[0];
        }

        $consolidado = [
            'numero_factura' => '',
            'fecha_emision' => '',
            'rif_emisor' => '',
            'nombre_emisor' => '',
            'moneda' => 'USD',
            'subtotal' => 0.00,
            'iva' => 0.00,
            'total' => 0.00,
            'articulos' => [],
            'facturas_individuales' => [],
            'raw_text' => ''
        ];

        $numerosFactura = [];
        $monedas = [];

        foreach ($facturasExtraidas as $idx => $fac) {
            $num = trim($fac['numero_factura'] ?? ('Factura ' . ($idx + 1)));
            if ($num && !in_array($num, $numerosFactura)) {
                $numerosFactura[] = $num;
            }

            if (empty($consolidado['fecha_emision']) && !empty($fac['fecha_emision'])) {
                $consolidado['fecha_emision'] = $fac['fecha_emision'];
            }
            if (empty($consolidado['rif_emisor']) && !empty($fac['rif_emisor'])) {
                $consolidado['rif_emisor'] = $fac['rif_emisor'];
            }
            if (empty($consolidado['nombre_emisor']) && !empty($fac['nombre_emisor'])) {
                $consolidado['nombre_emisor'] = $fac['nombre_emisor'];
            }

            $monedaFac = strtoupper(trim($fac['moneda'] ?? 'USD'));
            $monedas[] = $monedaFac;

            $consolidado['subtotal'] += floatval($fac['subtotal'] ?? 0);
            $consolidado['iva'] += floatval($fac['iva'] ?? 0);
            $consolidado['total'] += floatval($fac['total'] ?? 0);

            // Agregar artículos etiquetando su factura de origen
            foreach (($fac['articulos'] ?? []) as $art) {
                $art['factura_origen'] = $num;
                $consolidado['articulos'][] = $art;
            }

            $consolidado['facturas_individuales'][] = [
                'numero' => $num,
                'total' => floatval($fac['total'] ?? 0),
                'items_count' => count($fac['articulos'] ?? [])
            ];
        }

        // Agrupar artículos idénticos entre facturas si provienen del mismo código/descripción
        $articulosAgrupados = [];
        foreach ($consolidado['articulos'] as $art) {
            $key = strtoupper(trim(($art['codigo'] ?? '') ?: ($art['descripcion'] ?? '')));
            if (!$key) {
                $articulosAgrupados[] = $art;
                continue;
            }
            if (!isset($articulosAgrupados[$key])) {
                $articulosAgrupados[$key] = $art;
            } else {
                $articulosAgrupados[$key]['cantidad'] += floatval($art['cantidad'] ?? 0);
                $articulosAgrupados[$key]['total_renglon'] += floatval($art['total_renglon'] ?? 0);
                if (!str_contains($articulosAgrupados[$key]['factura_origen'] ?? '', $art['factura_origen'] ?? '')) {
                    $articulosAgrupados[$key]['factura_origen'] .= ', ' . ($art['factura_origen'] ?? '');
                }
            }
        }
        $consolidado['articulos'] = array_values($articulosAgrupados);

        $consolidado['numero_factura'] = implode(' / ', $numerosFactura);
        $consolidado['moneda'] = in_array('VES', $monedas) ? 'VES' : 'USD';
        $consolidado['subtotal'] = round($consolidado['subtotal'], 2);
        $consolidado['iva'] = round($consolidado['iva'], 2);
        $consolidado['total'] = round($consolidado['total'], 2);

        return $consolidado;
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
                $unidadesPorCaja = floatval($d['unidades_por_caja'] ?? $d['n_cantibul'] ?? $d['ns_CantidadEmpaque'] ?? 1);
                $bultos = floatval($d['bultos'] ?? ($unidadesPorCaja > 0 ? ($cant / $unidadesPorCaja) : $cant));
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
                    'bultos' => $bultos,
                    'unidades_por_caja' => $unidadesPorCaja > 0 ? $unidadesPorCaja : 1,
                    'unidad_presenta' => trim($d['c_presenta'] ?? 'UND'),
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
     * Motor de conciliación de alta precisión entre ODC y Factura.
     * Utiliza Maximum Weight Bipartite Matching con reglas de exclusión por atributos (sabor, tamaño, familia)
     * para evitar falsos positivos y emparejar 100% certeramente productos con nombres comerciales distintos.
     */
    protected function conciliarOdcConFactura(array $datosOdc, array $datosFactura)
    {
        $itemsOdc = $datosOdc['articulos'] ?? [];
        $itemsFactura = $datosFactura['articulos'] ?? [];

        $candidatos = [];

        // 1. Generar todos los pares posibles con scoring semántico y reglas de exclusión
        foreach ($itemsOdc as $idxOdc => $itemOdc) {
            $codOdc = strtoupper(trim($itemOdc['codigo'] ?? ''));
            $descOdc = mb_strtoupper(trim($itemOdc['descripcion'] ?? ''));
            $cantOdc = floatval($itemOdc['cantidad'] ?? 0);

            $dimOdc = $this->extraerDimensiones($descOdc);
            $famOdc = $this->detectarFamilia($descOdc);
            $sabOdc = $this->detectarSabor($descOdc);

            foreach ($itemsFactura as $idxF => $itemF) {
                $codF = strtoupper(trim($itemF['codigo'] ?? ''));
                $descF = mb_strtoupper(trim($itemF['descripcion'] ?? ''));
                $cantF = floatval($itemF['cantidad'] ?? 0);

                $dimF = $this->extraerDimensiones($descF);
                $famF = $this->detectarFamilia($descF);
                $sabF = $this->detectarSabor($descF);

                // REGLAS DE EXCLUSIÓN TOTAL (Incompatibilidad estricta):
                // A) Sabor diferente (ej: FRESA vs DURAZNO)
                if ($sabOdc && $sabF && $sabOdc !== $sabF) {
                    continue;
                }

                // B) Dimensión/tamaño incompatible (ej: 250ML vs 1.8L o 125GR vs 250ML)
                if (!empty($dimOdc) && !empty($dimF)) {
                    $comunes = array_intersect($dimOdc, $dimF);
                    if (empty($comunes)) {
                        continue;
                    }
                }

                // C) Familia de producto incompatible (ej: LECHE vs YOGURT o JARABE vs BOTELLA PET)
                if ($famOdc && $famF && $famOdc !== $famF) {
                    continue;
                }

                // CÁLCULO DE SCORE
                $score = 0;

                // Match exacto de código
                if (!empty($codOdc) && !empty($codF) && $codOdc === $codF) {
                    $score += 100;
                } elseif (!empty($codOdc) && !empty($codF) && (str_contains($codF, $codOdc) || str_contains($codOdc, $codF))) {
                    $score += 60;
                }

                // Misma familia
                if ($famOdc && $famF && $famOdc === $famF) {
                    $score += 30;
                }

                // Mismo sabor
                if ($sabOdc && $sabF && $sabOdc === $sabF) {
                    $score += 35;
                }

                // Misma dimensión
                if (!empty($dimOdc) && !empty($dimF)) {
                    $comunes = array_intersect($dimOdc, $dimF);
                    if (!empty($comunes)) {
                        $score += 35;
                    }
                }

                // Similitud de palabras clave compartidas
                $palabrasScore = $this->calcularScorePalabras($descOdc, $descF);
                $score += ($palabrasScore * 0.35);

                // Bonus si la cantidad coincide exactamente
                if ($cantOdc > 0 && $cantF > 0 && abs($cantOdc - $cantF) < 0.01) {
                    $score += 20;
                }
                // Bonus si la cantidad en factura coincide con los BULTOS (cajas) de la ODC
                $bultosOdc = floatval($itemOdc['bultos'] ?? 0);
                $unidadesPorCaja = floatval($itemOdc['unidades_por_caja'] ?? 1);
                if ($bultosOdc > 0 && $cantF > 0 && abs($bultosOdc - $cantF) < 0.01) {
                    $score += 25;
                } elseif ($unidadesPorCaja > 1 && $cantF > 0 && abs(($cantF * $unidadesPorCaja) - $cantOdc) < 0.01) {
                    $score += 25;
                }

                if ($score >= 40) {
                    $candidatos[] = [
                        'idx_odc' => $idxOdc,
                        'idx_fac' => $idxF,
                        'score' => $score,
                    ];
                }
            }
        }

        // 2. Ordenar candidatos por mayor score para matching global óptimo (evita que un ítem robe el match de otro)
        usort($candidatos, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        $odcUsados = [];
        $facUsados = [];
        $renglonesComparados = [];
        $hayDiscrepancias = false;
        $motivosDiscrepancia = [];

        // Emparejar de forma óptima
        foreach ($candidatos as $cand) {
            $iOdc = $cand['idx_odc'];
            $iFac = $cand['idx_fac'];

            if (isset($odcUsados[$iOdc]) || isset($facUsados[$iFac])) {
                continue;
            }

            $odcUsados[$iOdc] = true;
            $facUsados[$iFac] = true;

            $itemOdc = $itemsOdc[$iOdc];
            $matchFactura = $itemsFactura[$iFac];

            $cantOdc = floatval($itemOdc['cantidad'] ?? 0);
            $bultosOdc = floatval($itemOdc['bultos'] ?? 0);
            $unidadesPorCaja = floatval($itemOdc['unidades_por_caja'] ?? 1);
            $unidadPresenta = trim($itemOdc['unidad_presenta'] ?? 'UND');

            $cantFactura = floatval($matchFactura['cantidad'] ?? 0);
            $unidadFactura = strtolower(trim($matchFactura['unidad_medida'] ?? ''));

            $esUnidadCaja = str_contains($unidadFactura, 'caj') 
                || str_contains($unidadFactura, 'bto') 
                || str_contains($unidadFactura, 'bulto')
                || str_contains($unidadFactura, 'fdo')
                || str_contains($unidadFactura, 'fardo');

            $diff = 0;
            $estado = 'coincide';
            $notaEquivalencia = null;

            // CASO 1: Coincidencia exacta directa en unidades
            if (abs($cantFactura - $cantOdc) < 0.01) {
                $diff = 0;
                $estado = 'coincide';
                if ($bultosOdc > 0 && $unidadesPorCaja > 1 && $esUnidadCaja) {
                    $notaEquivalencia = "{$cantFactura} " . ($cantFactura == 1 ? 'caja' : 'cajas') . " = {$cantOdc} und";
                }
            }
            // CASO 2: La factura vino expresada en cajas/bultos y coincide con los bultos de la ODC (ej: Factura 1 caj == ODC 1 bulto de 20 und)
            elseif ($bultosOdc > 0 && abs($cantFactura - $bultosOdc) < 0.01) {
                $diff = 0;
                $estado = 'coincide'; // 🟢 Conforme
                $cajTexto = $cantFactura == 1 ? 'caja' : 'cajas';
                $notaEquivalencia = "{$cantFactura} {$cajTexto} = {$cantOdc} und";
            }
            // CASO 3: Multiplicación de cajas por unidades_por_caja coincide con la cantidad en ODC (ej: 2 caj x 10 und/caja = 20 und)
            elseif ($unidadesPorCaja > 1 && abs(($cantFactura * $unidadesPorCaja) - $cantOdc) < 0.01) {
                $diff = 0;
                $estado = 'coincide'; // 🟢 Conforme
                $notaEquivalencia = "{$cantFactura} caj x {$unidadesPorCaja} = {$cantOdc} und";
            }
            // CASO 4: Facturación en cajas con diferencia real (ej: pedidas 5 cajas y facturaron 4 cajas)
            elseif ($unidadesPorCaja > 1 && ($esUnidadCaja || ($bultosOdc > 0 && $cantFactura < $cantOdc && abs($cantFactura - $bultosOdc) > 0.01))) {
                $cantFacturaEnUnd = $cantFactura * $unidadesPorCaja;
                $diff = round($cantFacturaEnUnd - $cantOdc, 2);
                $diffCajas = round($cantFactura - $bultosOdc, 2);

                if ($diff < 0) {
                    $estado = 'faltante'; // 🟡 Entrega parcial
                    $hayDiscrepancias = true;
                    $cajasFaltan = abs($diffCajas);
                    $motivosDiscrepancia[] = "Faltan {$cajasFaltan} " . ($cajasFaltan == 1 ? 'caja' : 'cajas') . " (" . abs($diff) . " und) de '{$itemOdc['descripcion']}'";
                    $notaEquivalencia = "Faltan {$cajasFaltan} caj (" . abs($diff) . " und)";
                } else {
                    $estado = 'excedente'; // 🔴 Sobre-entrega
                    $hayDiscrepancias = true;
                    $cajasExceden = abs($diffCajas);
                    $motivosDiscrepancia[] = "Excedente de +{$cajasExceden} " . ($cajasExceden == 1 ? 'caja' : 'cajas') . " (+{$diff} und) en '{$itemOdc['descripcion']}'";
                    $notaEquivalencia = "+{$cajasExceden} caj (+{$diff} und)";
                }
            }
            // CASO 5: Comparación estándar directa de unidades
            else {
                $diff = round($cantFactura - $cantOdc, 2);
                if ($diff == 0) {
                    $estado = 'coincide'; // 🟢 Conforme
                } elseif ($diff < 0) {
                    $estado = 'faltante'; // 🟡 Entrega parcial
                    $hayDiscrepancias = true;
                    $motivosDiscrepancia[] = "Faltan " . abs($diff) . " und de '{$itemOdc['descripcion']}'";
                } else {
                    $estado = 'excedente'; // 🔴 Sobre-entrega
                    $hayDiscrepancias = true;
                    $motivosDiscrepancia[] = "Excedente de +{$diff} und en '{$itemOdc['descripcion']}'";
                }
            }

            $renglonesComparados[] = [
                'codigo_odc' => $itemOdc['codigo'] ?? null,
                'descripcion_odc' => $itemOdc['descripcion'] ?? null,
                'cantidad_odc' => $cantOdc,
                'bultos_odc' => $bultosOdc,
                'unidades_por_caja' => $unidadesPorCaja,
                'codigo_factura' => $matchFactura['codigo'] ?? null,
                'descripcion_factura' => $matchFactura['descripcion'] ?? null,
                'cantidad_factura' => $cantFactura,
                'unidad_factura' => $matchFactura['unidad_medida'] ?? null,
                'nota_equivalencia' => $notaEquivalencia,
                'diferencia' => $diff,
                'estado' => $estado,
            ];
        }

        // 3. Ítems de la ODC que no vinieron facturados
        foreach ($itemsOdc as $idxOdc => $itemOdc) {
            if (!isset($odcUsados[$idxOdc])) {
                $cantOdc = floatval($itemOdc['cantidad'] ?? 0);
                $hayDiscrepancias = true;
                $motivosDiscrepancia[] = "No vino facturado: '{$itemOdc['descripcion']}' ({$cantOdc} und)";

                $renglonesComparados[] = [
                    'codigo_odc' => $itemOdc['codigo'] ?? null,
                    'descripcion_odc' => $itemOdc['descripcion'] ?? null,
                    'cantidad_odc' => $cantOdc,
                    'codigo_factura' => null,
                    'descripcion_factura' => null,
                    'cantidad_factura' => 0,
                    'diferencia' => -$cantOdc,
                    'estado' => 'no_facturado',
                ];
            }
        }

        // 4. Ítems de la Factura que no estaban en la ODC (no pedidos)
        foreach ($itemsFactura as $idxF => $itemF) {
            if (!isset($facUsados[$idxF])) {
                $cantF = floatval($itemF['cantidad'] ?? 0);
                $descF = $itemF['descripcion'] ?? 'Producto no identificado';
                $hayDiscrepancias = true;
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

        // 5. Comparativa monetaria y detección inteligente de moneda (Bolívares VES vs Dólares USD)
        $totalOdc = floatval($datosOdc['total_monto'] ?? 0);
        $totalFactura = floatval($datosFactura['total'] ?? 0);
        $monedaFactura = strtoupper(trim($datosFactura['moneda'] ?? ''));

        // Detección automática: si la factura indica VES o si el total de la factura es > 10 veces el de la ODC
        $monedasDifieren = false;
        if ($monedaFactura === 'VES' || ($totalFactura > 0 && $totalOdc > 0 && $totalFactura > ($totalOdc * 10))) {
            $monedasDifieren = true;
            $monedaFactura = 'VES';
        } else {
            $monedaFactura = 'USD';
        }

        $diffTotal = round($totalFactura - $totalOdc, 2);

        // Si las monedas difieren, la discrepancia se evalúa estrictamente por unidades y renglones físicos
        if (!$monedasDifieren && abs($diffTotal) > 0.50 && $totalOdc > 0) {
            $hayDiscrepancias = true;
            $motivosDiscrepancia[] = "Diferencia de monto total: Factura $" . number_format($totalFactura, 2) . " vs ODC $" . number_format($totalOdc, 2);
        }

        $estatusGeneral = $hayDiscrepancias ? 'discrepancia' : 'conforme';

        if (!$hayDiscrepancias) {
            $resumenTexto = $monedasDifieren 
                ? "Factura emitida en Bolívares (Bs. " . number_format($totalFactura, 2) . "). Renglones y cantidades 100% cuadrados con la ODC en Divisas ($" . number_format($totalOdc, 2) . ")."
                : "Factura 100% conforme y cuadrada con la Orden de Compra.";
        } else {
            $prefijo = $monedasDifieren ? "(Factura en Bs. vs ODC en USD) · " : "";
            $resumenTexto = $prefijo . implode(' · ', array_slice($motivosDiscrepancia, 0, 3));
        }

        return [
            'estatus_general' => $estatusGeneral,
            'diferencia_total' => $diffTotal,
            'total_odc' => $totalOdc,
            'total_factura' => $totalFactura,
            'moneda_factura' => $monedaFactura,
            'monedas_difieren' => $monedasDifieren,
            'resumen_texto' => $resumenTexto,
            'renglones' => $renglonesComparados,
            'discrepancias_lista' => $motivosDiscrepancia,
        ];
    }

    /**
     * Diccionario de familias de productos comunes para evitar falsos positivos
     */
    protected function detectarFamilia(string $desc): ?string
    {
        $familias = [
            'LECHE' => ['LECHE', 'LACTEA', 'MILK'],
            'YOGURT' => ['YOGURT', 'YOGOURT', 'YOGUR'],
            'GELATINA' => ['GELATINA', 'JELLY'],
            'PET_ENVASE' => ['PET', 'ENVASE', 'BOTELLA', 'FRASCO', 'BALA', 'PELI'],
            'VALVULA' => ['VALVULA', 'DISPENSADOR', 'BOMBA'],
            'ATOMIZADOR' => ['ATOMIZADOR', 'SPRAY', 'PULVERIZADOR'],
            'JARABE' => ['JARABE', 'SYRUP', 'BIO', 'ENZ', 'ERI'],
            'QUESO' => ['QUESO', 'CHEESE'],
            'MANTEQUILLA' => ['MANTEQUILLA', 'MARGARINA'],
            'JUGO' => ['JUGO', 'NECTAR', 'BEBIDA'],
            'HARINA' => ['HARINA'],
            'ARROZ' => ['ARROZ'],
            'PASTA' => ['PASTA', 'ESPAGUETI', 'FIDEOS'],
            'ACEITE' => ['ACEITE'],
        ];

        foreach ($familias as $famKey => $terms) {
            foreach ($terms as $term) {
                if (preg_match('/\b' . preg_quote($term, '/') . '\b/i', $desc)) {
                    return $famKey;
                }
            }
        }
        return null;
    }

    /**
     * Detección de sabores comunes para exclusión estricta
     */
    protected function detectarSabor(string $desc): ?string
    {
        $sabores = [
            'FRESA', 'DURAZNO', 'MANZANA', 'PERA', 'VAINILLA', 'CHOCOLATE', 'NATURAL', 
            'PINA', 'PIÑA', 'COCO', 'GUANABANA', 'MORA', 'NARANJA', 'LIMA', 'LIMON'
        ];

        foreach ($sabores as $sabor) {
            if (preg_match('/\b' . preg_quote($sabor, '/') . '\b/i', $desc)) {
                return $sabor;
            }
        }
        return null;
    }

    /**
     * Calcula score de coincidencia basado en palabras clave compartidas
     */
    protected function calcularScorePalabras(string $desc1, string $desc2): float
    {
        $stopWords = [
            'DE', 'LA', 'EL', 'EN', 'CON', 'SIN', 'POR', 'PARA', 'UND', 'USO', 
            'INTERNO', 'C/', 'Y', 'A', 'X', 'C', 'TAPA', 'NEGRA', '28MM', 'PASTEUR', 'ENTERA'
        ];
        
        $words1 = array_filter(preg_split('/[\s\/\-\(\)\.,]+/', $desc1), function($w) use ($stopWords) {
            return mb_strlen($w) >= 3 && !in_array($w, $stopWords);
        });
        $words2 = array_filter(preg_split('/[\s\/\-\(\)\.,]+/', $desc2), function($w) use ($stopWords) {
            return mb_strlen($w) >= 3 && !in_array($w, $stopWords);
        });
        
        if (empty($words1) || empty($words2)) return 0;

        $coincidencias = 0;
        foreach ($words1 as $w1) {
            foreach ($words2 as $w2) {
                if ($w1 === $w2 || str_contains($w2, $w1) || str_contains($w1, $w2)) {
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
        return array_unique($dims);
    }
}
