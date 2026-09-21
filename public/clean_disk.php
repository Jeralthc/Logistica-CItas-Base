<?php
/**
 * SCRIPT DE EMERGENCIA PARA LIBERAR DISCO EN 1 SEGUNDO
 * Acceder desde el navegador: https://citsur.suraki.net/clean_disk.php
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>=== LIMPIADOR DE EMERGENCIA DE ESPACIO EN DISCO ===</h2><pre>";

$liberado = 0;

// 1. Vaciar laravel.log
$logFile = __DIR__ . '/../storage/logs/laravel.log';
if (file_exists($logFile)) {
    $bytes = filesize($logFile);
    $fp = @fopen($logFile, 'w');
    if ($fp) {
        fclose($fp);
        echo "✅ Archivo 'storage/logs/laravel.log' vaciado (Se liberaron " . round($bytes / 1024 / 1024, 2) . " MB).\n";
        $liberado += $bytes;
    } else {
        echo "⚠️ No se pudo vaciar laravel.log directamente por permisos.\n";
    }
}

// 2. Borrar archivos .zip acumulados en la raíz y en el dominio
$searchPaths = [
    __DIR__ . '/../*.zip',
    __DIR__ . '/../../../*.zip',
];

foreach ($searchPaths as $pattern) {
    $zips = glob($pattern);
    if ($zips) {
        foreach ($zips as $zip) {
            if (is_file($zip)) {
                $sz = filesize($zip);
                if (@unlink($zip)) {
                    echo "✅ Eliminado archivo zip: " . basename($zip) . " (" . round($sz / 1024 / 1024, 2) . " MB)\n";
                    $liberado += $sz;
                }
            }
        }
    }
}

// 3. Limpiar sesiones antiguas en storage/framework/sessions
$sessionsPath = __DIR__ . '/../storage/framework/sessions';
if (is_dir($sessionsPath)) {
    $sessionFiles = glob($sessionsPath . '/*');
    $sessDel = 0;
    if ($sessionFiles) {
        foreach ($sessionFiles as $sf) {
            if (is_file($sf) && basename($sf) !== '.gitignore') {
                if (@unlink($sf)) {
                    $sessDel++;
                }
            }
        }
    }
    echo "✅ Eliminados {$sessDel} archivos de sesión acumulados.\n";
}

// 4. Limpiar vistas compiladas antiguas en storage/framework/views
$viewsPath = __DIR__ . '/../storage/framework/views';
if (is_dir($viewsPath)) {
    $viewFiles = glob($viewsPath . '/*');
    $viewsDel = 0;
    if ($viewFiles) {
        foreach ($viewFiles as $vf) {
            if (is_file($vf) && basename($vf) !== '.gitignore') {
                if (@unlink($vf)) {
                    $viewsDel++;
                }
            }
        }
    }
    echo "✅ Eliminadas {$viewsDel} vistas compiladas temporales.\n";
}

echo "\n🎉 ¡PROCESO DE EMERGENCIA FINALIZADO!";
echo "\nTotal espacio estimado liberado: " . round($liberado / 1024 / 1024, 2) . " MB\n";
echo "</pre>";
