<?php

namespace App\Services;

use ZipArchive;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class SafeZipExtractor
{
    /**
     * Realiza un respaldo completo del código actual (excluyendo vendor, node_modules y storage)
     *
     * @param string|null $prefix
     * @return string Ruta del archivo backup generado
     */
    public static function backupCurrentCode($prefix = 'auto_backup_before_deploy')
    {
        $backupDir = storage_path('app/backups');
        if (!File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $zipName = $prefix . '_' . date('Y_m_d_His') . '.zip';
        $zipPath = $backupDir . '/' . $zipName;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \Exception("No se pudo crear el archivo zip de respaldo: " . $zipPath);
        }

        $foldersToBackup = ['app', 'routes', 'resources', 'config', 'database', 'public'];
        // Si existe public_html, lo añadimos
        if (File::exists(base_path('public_html'))) {
            $foldersToBackup[] = 'public_html';
        }

        foreach ($foldersToBackup as $folder) {
            $folderPath = base_path($folder);
            if (!File::exists($folderPath)) {
                continue;
            }

            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($folderPath, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $name => $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen(base_path()) + 1);
                    $relativePath = str_replace('\\', '/', $relativePath);
                    
                    // Omitir archivos zip temporales y caches locales pesadas si existen
                    if (strpos($relativePath, 'storage/') === 0 || strpos($relativePath, 'node_modules/') === 0 || strpos($relativePath, 'vendor/') === 0) {
                        continue;
                    }

                    $zip->addFile($filePath, $relativePath);
                }
            }
        }

        // Respaldar también .env si existe, pero dentro de una carpeta segura en el zip
        if (File::exists(base_path('.env'))) {
            $zip->addFile(base_path('.env'), 'env_backup_dont_extract.env');
        }

        $zip->close();
        return $zipPath;
    }

    /**
     * Extrae un archivo ZIP de actualización sobre el directorio raíz.
     *
     * @param string $zipFilePath Ruta al archivo ZIP a extraer.
     * @return array Resumen de operaciones y archivos modificados.
     */
    public static function extract($zipFilePath)
    {
        if (!class_exists(ZipArchive::class)) {
            throw new \Exception("La extensión PHP ZipArchive no está habilitada en este servidor.");
        }

        $zip = new ZipArchive();
        if ($zip->open($zipFilePath) !== true) {
            throw new \Exception("No se pudo abrir el archivo ZIP de actualización.");
        }

        $isCpanel = File::exists(base_path('public_html'));
        $filesExtracted = [];
        $filesSkipped = [];
        $errors = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            
            // Normalizar rutas a estilo unix
            $normalizedName = str_replace('\\', '/', $filename);

            // 1. SEGURIDAD EXTREMA: Omitir cualquier intento de pisar el .env principal
            if (basename($normalizedName) === '.env' || $normalizedName === '.env' || strpos($normalizedName, 'env_backup_dont_extract') !== false) {
                $filesSkipped[] = [
                    'file' => $filename,
                    'reason' => 'Bloqueado por seguridad (.env)'
                ];
                continue;
            }

            // Omitir directorios puros del índice (se crearán automáticamente al escribir archivos)
            if (substr($normalizedName, -1) === '/') {
                continue;
            }

            // 2. CORRECCIÓN DE RUTAS CPANEL: Traducir "public/" a "public_html/" si corresponde
            $targetPathName = $normalizedName;
            if ($isCpanel && strpos($normalizedName, 'public/') === 0) {
                $targetPathName = 'public_html/' . substr($normalizedName, 7);
            }

            $destination = base_path($targetPathName);

            // Crear directorio destino si no existe
            $destinationDir = dirname($destination);
            if (!File::exists($destinationDir)) {
                File::makeDirectory($destinationDir, 0755, true);
            }

            // Leer contenido del zip
            $content = $zip->getFromIndex($i);
            if ($content === false) {
                $errors[] = "Error al leer el archivo {$filename} del ZIP.";
                continue;
            }

            // Escribir archivo al destino
            if (File::put($destination, $content) !== false) {
                $filesExtracted[] = $targetPathName;
            } else {
                $errors[] = "No se pudo escribir el archivo en {$destination}.";
            }
        }

        $zip->close();

        return [
            'extracted_count' => count($filesExtracted),
            'skipped_count' => count($filesSkipped),
            'extracted' => $filesExtracted,
            'skipped' => $filesSkipped,
            'errors' => $errors
        ];
    }
}
