<?php

/**
 * GENERADOR DE ARCHIVO ZIP DE ACTUALIZACIÓN SEGURA
 * Genera PARCHE_FINAL_COMPRADORES.zip y ACTUALIZACION_CORRECCION_COMPRADORES.zip
 * Incluye controladores backend, rutas, fuentes Vue y el build compilado para safe_deploy.php
 */

$zipNames = [
    'PARCHE_FINAL_COMPRADORES.zip',
    'ACTUALIZACION_CORRECCION_COMPRADORES.zip'
];

$individualFiles = [
    'config/app.php',
    'app/Http/Controllers/CitaController.php',
    'app/Http/Controllers/LogisticaController.php',
    'app/Http/Controllers/MonitoringController.php',
    'app/Http/Controllers/SyncController.php',
    'routes/web.php',
    'routes/api.php',
    'resources/js/Layouts/AuthenticatedLayout.vue',
    'resources/js/Pages/Monitoreo.vue',
    'resources/js/Pages/ReservarCita.vue',
];

// Recolectar archivos compilados de public/build
$buildFiles = [];
$buildDir = __DIR__ . '/public/build';
if (is_dir($buildDir)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($buildDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isFile()) {
            $relativePath = 'public/build/' . str_replace('\\', '/', substr($item->getPathname(), strlen($buildDir) + 1));
            $buildFiles[] = $relativePath;
        }
    }
}

$allFilesToAdd = array_merge($individualFiles, $buildFiles);

$artifactDir = 'C:/Users/Sistemas Suraki/.gemini/antigravity-ide/brain/68ec6f38-73f4-412e-89c1-429bffaf5987';

foreach ($zipNames as $zipName) {
    $zipPath = __DIR__ . '/' . $zipName;
    if (file_exists($zipPath)) {
        @unlink($zipPath);
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
        $count = 0;
        foreach ($allFilesToAdd as $f) {
            $fullPath = __DIR__ . '/' . $f;
            if (file_exists($fullPath)) {
                $zip->addFile($fullPath, $f);
                $count++;
            }
        }
        $zip->close();
        $sizeKb = round(filesize($zipPath) / 1024, 2);
        echo "✅ ZIP Generado: {$zipName} ({$count} archivos, {$sizeKb} KB) en " . realpath($zipPath) . "\n";

        // Copiar al directorio de artefactos
        if (is_dir($artifactDir)) {
            $artifactPath = $artifactDir . '/' . $zipName;
            copy($zipPath, $artifactPath);
            echo "   -> Copiado a artefacto: {$artifactPath}\n";
        }
    } else {
        echo "❌ Error al crear {$zipName}\n";
    }
}

echo "\nProceso de empaquetado finalizado con éxito.\n";
