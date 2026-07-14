<?php
// generar_zip_despliegue.php
// Ejecuta este script localmente para generar el archivo ZIP de bootstrap.

$zip = new ZipArchive();
$zipName = 'DESPLIEGUE_SISTEMA_SEGURO.zip';

if (file_exists($zipName)) {
    unlink($zipName);
}

if ($zip->open($zipName, ZipArchive::CREATE) === TRUE) {
    
    // Función helper para agregar un folder completo con mapeo opcional
    $addFolder = function($folderPath, $zipPrefix, $excludeList = []) use ($zip) {
        if (!is_dir($folderPath)) return;
        $dir = new RecursiveDirectoryIterator($folderPath, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($dir);

        foreach ($files as $file) {
            if (!$file->isDir()) {
                $realPath = $file->getRealPath();
                $relativePath = substr($realPath, strlen($folderPath) + 1);
                $relativePath = str_replace('\\', '/', $relativePath);
                
                // Ignorar archivos excluidos
                $skip = false;
                foreach ($excludeList as $exclude) {
                    if (strpos($relativePath, $exclude) !== false) {
                        $skip = true;
                        break;
                    }
                }
                if ($skip) continue;

                $zipPath = $zipPrefix . '/' . $relativePath;
                $zip->addFile($realPath, $zipPath);
            }
        }
    };

    // 1. App files (Controllers, Services, Providers)
    $zip->addFile(__DIR__ . '/app/Http/Controllers/DeployController.php', 'app/Http/Controllers/DeployController.php');
    $zip->addFile(__DIR__ . '/app/Services/SafeZipExtractor.php', 'app/Services/SafeZipExtractor.php');
    $zip->addFile(__DIR__ . '/app/Providers/AppServiceProvider.php', 'app/Providers/AppServiceProvider.php');

    // 2. Routes
    $zip->addFile(__DIR__ . '/routes/web.php', 'routes/web.php');

    // 3. Views (Dashboard modifications require Despliegue vue and rebuilt js assets)
    $zip->addFile(__DIR__ . '/resources/js/Pages/Despliegue.vue', 'resources/js/Pages/Despliegue.vue');
    $zip->addFile(__DIR__ . '/resources/js/Pages/Dashboard.vue', 'resources/js/Pages/Dashboard.vue');
    $zip->addFile(__DIR__ . '/resources/js/Layouts/AuthenticatedLayout.vue', 'resources/js/Layouts/AuthenticatedLayout.vue');
    $zip->addFile(__DIR__ . '/resources/views/errors/503.blade.php', 'resources/views/errors/503.blade.php');

    // 4. Public files (Recovery Script & Compiled Assets)
    // Se colocan en 'public_html' para que caigan directamente en la carpeta pública del servidor
    $zip->addFile(__DIR__ . '/public/safe_deploy.php', 'public_html/safe_deploy.php');
    $addFolder(__DIR__ . '/public/build', 'public_html/build');

    $zip->close();
    echo "\n--------------------------------------------------\n";
    echo "¡ZIP 'DESPLIEGUE_SISTEMA_SEGURO.zip' creado con éxito!\n";
    echo "Tamaño: " . round(filesize($zipName) / 1024 / 1024, 2) . " MB\n";
    echo "--------------------------------------------------\n";
} else {
    echo "Error al crear el archivo ZIP.\n";
}
