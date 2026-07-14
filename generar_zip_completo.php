<?php
$zip = new ZipArchive();
$zipName = 'actualizacion_completa.zip';

if ($zip->open($zipName, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
    
    // Función para añadir directorios completos con un prefijo personalizado en el ZIP
    $addFolderToZip = function($folderPath, $zipPrefix) use ($zip) {
        if (!is_dir($folderPath)) return;
        $dir = new RecursiveDirectoryIterator($folderPath, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($dir);

        foreach ($files as $file) {
            if (!$file->isDir()) {
                $realPath = $file->getRealPath();
                $relativePath = substr($realPath, strlen($folderPath) + 1);
                $relativePath = str_replace('\\', '/', $relativePath);
                
                $zipPath = $zipPrefix . '/' . $relativePath;
                $zip->addFile($realPath, $zipPath);
            }
        }
    };

    // Añadir backend (app)
    $addFolderToZip(__DIR__ . '/app', 'app');
    
    // Añadir migraciones (database)
    $addFolderToZip(__DIR__ . '/database/migrations', 'database/migrations');
    
    // Añadir Frontend compilado. Lo ponemos dentro de 'public_html' para que al extraer en DirectAdmin caiga en el lugar correcto.
    $addFolderToZip(__DIR__ . '/public/build', 'public_html/build');

    // Añadir fix_views.php
    if (file_exists(__DIR__ . '/public/fix_views.php')) {
        $zip->addFile(__DIR__ . '/public/fix_views.php', 'public_html/fix_views.php');
    }

    $zip->close();
    echo "¡actualizacion_completa.zip creado con éxito!\n";
} else {
    echo "Falló la creación del ZIP.\n";
}
