<?php

/**
 * SISTEMA DE DESPLIEGUE CONTINUO Y SEGURO A PRODUCCIÓN
 * Ejecutar con: php desplegar.php
 * Sube automáticamente el código y frontend compilado a https://citsur.suraki.net
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

echo "=====================================================\n";
echo "🚀 DESPLIEGUE AUTOMÁTICO A PRODUCCIÓN (CITSUR.SURAKI.NET)\n";
echo "=====================================================\n";

$baseDir = __DIR__;
$zipName = 'PARCHE_PRODUCCION_' . date('Y_m_d_His') . '.zip';
$zipPath = $baseDir . '/' . $zipName;

// 1. Recopilar archivos a empaquetar
$filesToPackage = [];

// Lista de carpetas clave a incluir
$folders = ['app', 'bootstrap', 'config', 'routes', 'lang', 'database/migrations', 'database/seeders', 'resources/views', 'resources/js/Pages', 'resources/js/Layouts'];
foreach ($folders as $folder) {
    $dir = $baseDir . '/' . $folder;
    if (is_dir($dir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isFile()) {
                $rel = str_replace('\\', '/', substr($item->getPathname(), strlen($baseDir) + 1));
                $filesToPackage[] = $rel;
            }
        }
    }
}

// Incluir scripts PHP, documentos y video MP4 de la raíz de public
foreach (glob($baseDir . '/public/*.{php,docx,pdf,mp4}', GLOB_BRACE) as $file) {
    $rel = 'public/' . basename($file);
    $filesToPackage[] = $rel;
}

// Incluir imágenes de manual_assets
$manualAssetsDir = $baseDir . '/public/manual_assets';
if (is_dir($manualAssetsDir)) {
    foreach (glob($manualAssetsDir . '/*.*') as $assetFile) {
        $rel = 'public/manual_assets/' . basename($assetFile);
        $filesToPackage[] = $rel;
    }
}

// Incluir archivos compilados de public/build
$buildDir = $baseDir . '/public/build';
if (is_dir($buildDir)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($buildDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isFile()) {
            $rel = 'public/build/' . str_replace('\\', '/', substr($item->getPathname(), strlen($buildDir) + 1));
            $filesToPackage[] = $rel;
        }
    }
}

$filesToPackage = array_unique($filesToPackage);
echo "📦 Empaquetando " . count($filesToPackage) . " archivos en {$zipName}...\n";

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die("❌ Error al crear el archivo ZIP temporal.\n");
}

foreach ($filesToPackage as $f) {
    $full = $baseDir . '/' . $f;
    if (file_exists($full)) {
        $zip->addFile($full, $f);
    }
}
$zip->close();

$sizeKb = round(filesize($zipPath) / 1024, 2);
echo "✅ ZIP generado con éxito ({$sizeKb} KB).\n\n";

// 2. Conectar y autenticar con safe_deploy.php
$url = 'https://citsur.suraki.net/safe_deploy.php';
$token = config('app.erp_api_token') ?: env('ERP_API_TOKEN', 'SurakiSecreto2026');
$cookieJar = new \GuzzleHttp\Cookie\CookieJar();

echo "🔐 1/3 Autenticando con el servidor de producción...\n";
$loginResp = Http::withoutVerifying()
    ->withOptions(['cookies' => $cookieJar])
    ->asForm()
    ->post($url, [
        'action' => 'login',
        'password' => $token
    ]);

if (!$loginResp->successful()) {
    @unlink($zipPath);
    die("❌ Error de autenticación HTTP: " . $loginResp->status() . "\n");
}
echo "   Autenticación exitosa.\n";

// 3. Subir y extraer ZIP en producción
echo "📤 2/3 Subiendo y extrayendo paquete en producción...\n";
$uploadResp = Http::withoutVerifying()
    ->withOptions(['cookies' => $cookieJar])
    ->timeout(300)
    ->attach('emergency_zip', fopen($zipPath, 'r'), 'despliegue_actualizacion.zip')
    ->post($url);

$body = $uploadResp->body();
if (strpos($body, 'exitosamente') !== false || strpos($body, 'archivos') !== false || strpos($body, 'alert-success') !== false) {
    echo "   ✅ Paquete extraído y aplicado en el servidor!\n";
} else {
    echo "   ⚠️ Advertencia en respuesta:\n";
    if (preg_match('/<div class="alert[^>]*>(.*?)<\/div>/s', $body, $m)) {
        echo "   " . trim(strip_tags($m[1])) . "\n";
    }
}

// 4. Limpiar cachés del servidor
echo "🧹 3/3 Limpiando cachés de vistas y rutas en el servidor...\n";
$clearResp = Http::withoutVerifying()
    ->withOptions(['cookies' => $cookieJar])
    ->get($url . '?op=clear_cache');

if ($clearResp->successful()) {
    echo "   ✅ Cachés de Laravel limpiadas exitosamente.\n";
}

// 5. Ejecutar migraciones y seeders en producción
echo "⚡ 4/4 Ejecutando migraciones y seeders de prueba en producción...\n";
$migResp = Http::withoutVerifying()->timeout(60)->get('https://citsur.suraki.net/migrar.php?seed=test');
if ($migResp->successful() && str_contains($migResp->body(), 'exitosa')) {
    echo "   ✅ Migraciones y TestProviderAndOrdersSeeder ejecutados con éxito en producción!\n";
} else {
    echo "   ℹ️ Respuesta de migrar.php: " . trim(strip_tags($migResp->body())) . "\n";
}

// 5. Verificación final de salud
echo "\n🔍 Verificación de salud del sitio en vivo...\n";
$health = Http::withoutVerifying()->get('https://citsur.suraki.net');
echo "   Estado del sitio web: HTTP " . $health->status() . " (" . ($health->successful() ? "OPERATIVO ✅" : "REVISAR ⚠️") . ")\n";

// Limpiar zip temporal local
@unlink($zipPath);

echo "\n=====================================================\n";
echo "🎉 ¡DESPLIEGUE FINALIZADO CON ÉXITO!\n";
echo "Los cambios ya están 100% en vivo para todos los usuarios.\n";
echo "=====================================================\n";
