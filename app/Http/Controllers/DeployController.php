<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use App\Services\SafeZipExtractor;
use App\Services\AuditLogger;

class DeployController extends Controller
{
    /**
     * Obtiene la información de diagnóstico del sistema
     */
    public function info()
    {
        // 1. Datos básicos
        $phpVersion = phpversion();
        $laravelVersion = app()->version();
        $environment = app()->environment();
        $debugMode = config('app.debug') ? 'Activado (Desarrollo)' : 'Desactivado (Producción)';

        // 2. Estado de la base de datos
        $dbConnected = false;
        $dbDriver = config('database.default');
        $dbName = 'Desconocido';
        $dbHost = 'Desconocido';
        $dbError = null;

        try {
            DB::connection()->getPdo();
            $dbConnected = true;
            $dbName = DB::connection()->getDatabaseName();
            
            // Intentar extraer el host
            $config = config("database.connections.{$dbDriver}");
            $dbHost = $config['host'] ?? 'Local/Socket';
        } catch (\Exception $e) {
            $dbError = $e->getMessage();
        }

        // 3. Estado de migraciones (Artisan migrate:status)
        $migrationStatus = "No disponible (Sin conexión a Base de Datos)";
        if ($dbConnected) {
            try {
                Artisan::call('migrate:status');
                $migrationStatus = Artisan::output();
            } catch (\Exception $e) {
                $migrationStatus = "Error al leer migraciones: " . $e->getMessage();
            }
        }

        // 4. Directorio público y Vite manifest
        $publicDir = File::exists(base_path('public_html')) ? 'public_html' : 'public';
        $manifestPath = base_path($publicDir . '/build/manifest.json');
        $manifestExists = File::exists($manifestPath);
        $manifestTime = $manifestExists ? date('Y-m-d H:i:s', File::lastModified($manifestPath)) : null;

        // 5. Backups disponibles
        $backups = [];
        $backupDir = storage_path('app/backups');
        if (File::exists($backupDir)) {
            $files = File::files($backupDir);
            foreach ($files as $file) {
                if (strtolower($file->getExtension()) === 'zip') {
                    $backups[] = [
                        'filename' => $file->getFilename(),
                        'size' => round($file->getSize() / 1024 / 1024, 2) . ' MB',
                        'created_at' => date('Y-m-d H:i:s', $file->getMTime()),
                        'timestamp' => $file->getMTime(),
                        'source' => 'backup'
                    ];
                }
            }
        }

        // También escanear directorio raíz para zips
        $rootDir = base_path();
        if (File::exists($rootDir)) {
            $files = File::files($rootDir);
            foreach ($files as $file) {
                $filename = $file->getFilename();
                if (strtolower($file->getExtension()) === 'zip' && 
                    (str_starts_with($filename, 'V') || 
                     str_starts_with($filename, 'ACTUALIZACION') || 
                     str_starts_with($filename, 'DESPLIEGUE') ||
                     str_starts_with($filename, 'actualizacion') ||
                     str_starts_with($filename, 'despliegue'))) {
                    $backups[] = [
                        'filename' => $filename,
                        'size' => round($file->getSize() / 1024 / 1024, 2) . ' MB',
                        'created_at' => date('Y-m-d H:i:s', $file->getMTime()),
                        'timestamp' => $file->getMTime(),
                        'source' => 'root'
                    ];
                }
            }
        }

        // Ordenar por fecha desc
        usort($backups, function ($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });

        // 6. Estado del Mantenimiento
        $maintenanceActive = app()->isDownForMaintenance();

        return response()->json([
            'php_version' => $phpVersion,
            'laravel_version' => $laravelVersion,
            'environment' => $environment,
            'debug_mode' => $debugMode,
            'db' => [
                'connected' => $dbConnected,
                'driver' => $dbDriver,
                'name' => $dbName,
                'host' => $dbHost,
                'error' => $dbError
            ],
            'migrations' => $migrationStatus,
            'public_dir' => $publicDir,
            'manifest' => [
                'exists' => $manifestExists,
                'last_modified' => $manifestTime,
            ],
            'backups' => $backups,
            'maintenance' => $maintenanceActive
        ]);
    }

    /**
     * Ejecuta php artisan migrate --force
     */
    public function runMigrations()
    {
        try {
            Artisan::call('migrate', ['--force' => true]);
            $output = Artisan::output();

            AuditLogger::log(
                module: 'Despliegue',
                action: 'Ejecución de Migraciones',
                motive: 'Ejecución manual desde el panel de control'
            );

            return response()->json([
                'success' => true,
                'output' => $output
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'output' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Limpia la caché completa del sistema
     */
    public function clearCache()
    {
        try {
            $outputs = [];
            
            Artisan::call('config:clear');
            $outputs[] = "Config: " . Artisan::output();
            
            Artisan::call('cache:clear');
            $outputs[] = "Cache: " . Artisan::output();
            
            Artisan::call('view:clear');
            $outputs[] = "Views: " . Artisan::output();
            
            Artisan::call('route:clear');
            $outputs[] = "Routes: " . Artisan::output();

            AuditLogger::log(
                module: 'Despliegue',
                action: 'Limpieza de Caché',
                motive: 'Limpieza manual desde el panel de control'
            );

            return response()->json([
                'success' => true,
                'output' => implode("\n", $outputs)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'output' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ejecuta optimización (artisan optimize)
     */
    public function optimize()
    {
        try {
            Artisan::call('optimize');
            $output = Artisan::output();

            AuditLogger::log(
                module: 'Despliegue',
                action: 'Optimización del Sistema',
                motive: 'Optimización manual desde el panel de control'
            );

            return response()->json([
                'success' => true,
                'output' => $output
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'output' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crea un backup manual del código actual
     */
    public function createBackup()
    {
        try {
            $zipPath = SafeZipExtractor::backupCurrentCode('manual_backup');
            $filename = basename($zipPath);

            AuditLogger::log(
                module: 'Despliegue',
                action: 'Creación de Backup',
                motive: 'Creación manual de copia de seguridad del código fuente'
            );

            return response()->json([
                'success' => true,
                'message' => "Copia de seguridad creada correctamente: {$filename}"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Fallo al crear la copia de seguridad: " . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restaura una copia de seguridad específica
     */
    public function restoreBackup(Request $request)
    {
        $validated = $request->validate([
            'filename' => 'required|string'
        ]);

        $filename = $validated['filename'];
        $zipPath = storage_path('app/backups/' . $filename);
        if (!File::exists($zipPath)) {
            $zipPath = base_path($filename);
        }

        if (!File::exists($zipPath)) {
            return response()->json([
                'success' => false,
                'message' => 'El archivo de respaldo o actualización no existe.'
            ], 404);
        }

        try {
            // 1. Hacer un backup automático de "emergencia" antes de restaurar
            SafeZipExtractor::backupCurrentCode('backup_before_restore_' . basename($filename, '.zip'));

            // 2. Extraer el zip sobre la raíz
            $result = SafeZipExtractor::extract($zipPath);

            // 3. Ejecutar migraciones por seguridad
            Artisan::call('migrate', ['--force' => true]);
            
            // 4. Limpiar cachés
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
            Artisan::call('view:clear');
            Artisan::call('route:clear');

            AuditLogger::log(
                module: 'Despliegue',
                action: 'Restauración de Backup',
                motive: "Restauración manual del backup: {$filename}"
            );

            return response()->json([
                'success' => true,
                'message' => 'Copia de seguridad restaurada correctamente.',
                'details' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error durante la restauración: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Recibe un ZIP de actualización, hace backup automático y lo extrae
     */
    public function uploadZip(Request $request)
    {
        $request->validate([
            'zip_file' => 'required|file|mimes:zip|max:51200', // max 50MB
        ]);

        $file = $request->file('zip_file');
        
        // Crear carpeta temporal si no existe
        $tempDir = storage_path('app/temp');
        if (!File::exists($tempDir)) {
            File::makeDirectory($tempDir, 0755, true);
        }

        $tempName = 'upload_' . time() . '.zip';
        $file->move($tempDir, $tempName);
        $fullPath = $tempDir . '/' . $tempName;

        try {
            // 1. Generar backup del código actual por seguridad
            $backupPath = SafeZipExtractor::backupCurrentCode('auto_backup_before_deploy');
            $backupFilename = basename($backupPath);

            // 2. Extraer actualización
            $result = SafeZipExtractor::extract($fullPath);

            // 3. Ejecutar migraciones si existen nuevas
            $migrateOutput = '';
            try {
                Artisan::call('migrate', ['--force' => true]);
                $migrateOutput = Artisan::output();
            } catch (\Exception $me) {
                $migrateOutput = "Error al migrar: " . $me->getMessage();
            }

            // 4. Limpiar cachés
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
            Artisan::call('view:clear');
            Artisan::call('route:clear');

            // Limpiar el archivo subido
            File::delete($fullPath);

            AuditLogger::log(
                module: 'Despliegue',
                action: 'Carga de Actualización ZIP',
                motive: "Actualización de sistema mediante archivo zip. Backup automático creado: {$backupFilename}"
            );

            return response()->json([
                'success' => true,
                'message' => 'Actualización cargada y aplicada de forma segura.',
                'backup_created' => $backupFilename,
                'extraction' => $result,
                'migration_output' => $migrateOutput
            ]);
        } catch (\Exception $e) {
            // Limpiar archivo subido si falla
            if (File::exists($fullPath)) {
                File::delete($fullPath);
            }

            return response()->json([
                'success' => false,
                'message' => 'Error durante el despliegue del ZIP: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Permite descargar un backup específico
     */
    public function downloadBackup($filename)
    {
        // Limpiar nombre de archivo para evitar Path Traversal
        $filename = basename($filename);
        $zipPath = storage_path('app/backups/' . $filename);

        if (!File::exists($zipPath)) {
            abort(404, 'Archivo de respaldo no encontrado.');
        }

        return response()->download($zipPath);
    }
}
