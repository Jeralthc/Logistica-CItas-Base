<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\LogisticaController;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncErpData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'erp:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Extrae órdenes de compra pendientes del ERP local y las envía a la web mediante la API.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $lockFile = storage_path('framework/erp_sync.lock');
        $fp = @fopen($lockFile, 'c+');
        if (!$fp || !flock($fp, LOCK_EX | LOCK_NB)) {
            $this->warn('Ya existe una sincronización ERP en ejecución. Omitiendo esta ejecución para evitar sobrecarga.');
            return 0;
        }

        $apiUrl = env('ERP_API_URL');
        $apiToken = env('ERP_API_TOKEN');

        if (!$apiUrl) {
            $this->error('Error: ERP_API_URL no está configurada. Debes especificar la URL de tu sitio web (Ej: https://citsur.suraki.net/api).');
            if ($fp) {
                flock($fp, LOCK_UN);
                fclose($fp);
            }
            return 1;
        }

        $inicioSync = microtime(true);
        $this->info("Iniciando sincronización ERP con la web: {$apiUrl}");

        try {
            $logistica = new LogisticaController();

            // 1. Traer órdenes pendientes (forzando conexión a base de datos local)
            $responsePendientes = $logistica->ordenesPendientes(true);
            $dataPendientes = json_decode($responsePendientes->getContent(), true);

            if (!isset($dataPendientes['status']) || $dataPendientes['status'] !== 'Exitoso' || !isset($dataPendientes['ordenes'])) {
                $this->error("No se pudieron obtener las órdenes pendientes del ERP.");
                return 1;
            }

            $ordenes = $dataPendientes['ordenes'];
            $totalOrdenes = count($ordenes);
            $this->info("Se encontraron {$totalOrdenes} órdenes pendientes. Obteniendo detalles...");

            $payloadOrdenes = [];

            // 2. Extraer detalles para cada orden
            foreach ($ordenes as $index => $orden) {
                $numeroOc = $orden['numero_oc'];
                
                $responseDetalles = $logistica->buscarOrdenCompleta($numeroOc, true);
                $dataDetalles = json_decode($responseDetalles->getContent(), true);

                if (isset($dataDetalles['status']) && $dataDetalles['status'] === 'Exitoso') {
                    $payloadOrdenes[] = [
                        'numero_oc' => $numeroOc,
                        'fecha_emision' => $orden['fecha_emision'] ?? null,
                        'fecha_recepcion' => $orden['fecha_recepcion'] ?? null,
                        'proveedor' => $orden['proveedor'] ?? null,
                        'destino' => $orden['destino'] ?? null,
                        // Guardamos TODA la estructura de buscarOrdenCompleta (excepto detalles) dentro de resumen_json
                        'resumen' => array_merge($orden, array_diff_key($dataDetalles, ['detalles' => 1])),
                        'detalles' => $dataDetalles['detalles'] ?? [],
                    ];
                } else {
                    $this->warn("Advertencia: No se pudieron obtener detalles completos para OC {$numeroOc}");
                }
            }

            // 3. Enviar Payload a la Web en Lotes (Chunks) para no saturar memoria ni límite de POST
            $chunks = array_chunk($payloadOrdenes, 500);
            $totalChunks = count($chunks);
            
            $this->info("Enviando " . count($payloadOrdenes) . " órdenes en {$totalChunks} lotes a la web...");
            
            $endpoint = rtrim($apiUrl, '/') . '/sync/recibir';
            
            foreach ($chunks as $index => $chunk) {
                $numeroLote = $index + 1;
                $this->line("Enviando lote {$numeroLote} de {$totalChunks}...");

                $httpRequest = Http::timeout(120);
                if ($apiToken) {
                    $httpRequest = $httpRequest->withToken($apiToken);
                }

                $response = $httpRequest->post($endpoint, [
                    'ordenes' => $chunk,
                    'es_primer_chunk' => ($numeroLote === 1)
                ]);

                if ($response->successful()) {
                    $this->info("Lote {$numeroLote} enviado con éxito.");
                } else {
                    $this->error("Error al enviar lote {$numeroLote}: HTTP " . $response->status() . " - " . $response->body());
                    Log::error("ERP Sync Error Lote {$numeroLote}: " . $response->body());
                    return 1; // Detener si falla un lote
                }
            }
            
            $duracionSeg = round(microtime(true) - $inicioSync, 2);
            $this->info("¡Sincronización Total Exitosa en {$duracionSeg}s!");
            Log::info("ERP Sync: Sincronización exitosa con la web en {$totalChunks} lotes ({$duracionSeg}s).");

        } catch (\Exception $e) {
            $this->error("Excepción durante la sincronización: " . $e->getMessage());
            Log::error("ERP Sync Exception: " . $e->getMessage());
            return 1;
        } finally {
            if ($fp) {
                flock($fp, LOCK_UN);
                fclose($fp);
            }
        }

        return 0;
    }
}
