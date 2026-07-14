<?php
// safe_deploy.php - Panel de Emergencia y Recuperación Seguro
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

$baseDir = realpath(__DIR__ . '/..');

// 1. Obtener la clave secreta desde .env
$secretToken = 'Empresa BaseSecreto2026'; // Fallback
$envPath = $baseDir . '/.env';
if (file_exists($envPath)) {
    $envContent = file_get_contents($envPath);
    if (preg_match('/^ERP_API_TOKEN=(.*)$/m', $envContent, $matches)) {
        $secretToken = trim($matches[1], "\"' \r\n");
    }
}

// 2. Manejo de autenticación
if (isset($_POST['action']) && $_POST['action'] === 'login') {
    $pass = $_POST['password'] ?? '';
    if ($pass === $secretToken) {
        $_SESSION['authenticated_deploy'] = true;
        header('Location: safe_deploy.php');
        exit;
    } else {
        $error = "Contraseña de emergencia inválida.";
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['authenticated_deploy']);
    header('Location: safe_deploy.php');
    exit;
}

$authenticated = $_SESSION['authenticated_deploy'] ?? false;

// Si no está autenticado, mostrar formulario de login
if (!$authenticated) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Recuperación de Emergencia - Empresa Base</title>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
        <style>
            body {
                background: radial-gradient(circle at top, #1e293b, #0f172a);
                color: #f8fafc;
                font-family: 'Outfit', sans-serif;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                margin: 0;
            }
            .card {
                background: rgba(30, 41, 59, 0.7);
                backdrop-filter: blur(16px);
                border: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 24px;
                padding: 40px;
                width: 100%;
                max-width: 420px;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
                text-align: center;
            }
            h1 {
                font-size: 24px;
                font-weight: 800;
                margin-bottom: 8px;
                background: linear-gradient(to right, #ef4444, #f87171);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
            }
            p {
                color: #94a3b8;
                font-size: 14px;
                margin-bottom: 24px;
            }
            input[type="password"] {
                width: 100%;
                padding: 14px 20px;
                background: rgba(15, 23, 42, 0.6);
                border: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 14px;
                color: white;
                font-size: 16px;
                box-sizing: border-box;
                margin-bottom: 16px;
                outline: none;
                transition: border 0.3s;
            }
            input[type="password"]:focus {
                border-color: #ef4444;
            }
            button {
                width: 100%;
                padding: 14px;
                background: #ef4444;
                border: none;
                border-radius: 14px;
                color: white;
                font-weight: 600;
                font-size: 16px;
                cursor: pointer;
                transition: background 0.3s, transform 0.2s;
            }
            button:hover {
                background: #dc2626;
                transform: translateY(-1px);
            }
            .error {
                color: #f87171;
                font-size: 13px;
                margin-top: 12px;
                font-weight: 600;
            }
            .footer {
                margin-top: 32px;
                font-size: 11px;
                color: #475569;
            }
        </style>
    </head>
    <body>
        <div class="card">
            <div style="font-size: 48px; margin-bottom: 16px;">🚨</div>
            <h1>Panel de Emergencia</h1>
            <p>Ingresa la contraseña de seguridad (ERP_API_TOKEN) para acceder a las herramientas de restauración.</p>
            <form method="POST">
                <input type="hidden" name="action" value="login">
                <input type="password" name="password" placeholder="Contraseña de emergencia" required autocomplete="off">
                <button type="submit">Desbloquear Panel</button>
            </form>
            <?php if (isset($error)): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <div class="footer">Sistema de Despliegue Seguro - Empresa Base © 2026</div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// === Lógica del panel autenticado ===
$message = '';
$messageType = 'success';
$terminalOutput = '';

$backupDir = $baseDir . '/storage/app/backups';
$downFile = $baseDir . '/storage/framework/down';

// Acción: Activar Mantenimiento
if (isset($_GET['op']) && $_GET['op'] === 'maint_on') {
    $time = time();
    $data = [
        'time' => $time,
        'secret' => 'Empresa Base-admin',
        'status' => 503
    ];
    if (file_put_contents($downFile, json_encode($data))) {
        $message = "Mantenimiento ACTIVADO. Bypass secreto: /Empresa Base-admin";
    } else {
        $message = "Error al escribir el archivo de mantenimiento.";
        $messageType = 'error';
    }
}

// Acción: Desactivar Mantenimiento
if (isset($_GET['op']) && $_GET['op'] === 'maint_off') {
    if (file_exists($downFile)) {
        if (@unlink($downFile)) {
            $message = "Mantenimiento DESACTIVADO correctamente.";
        } else {
            $message = "Error al eliminar el archivo de mantenimiento.";
            $messageType = 'error';
        }
    } else {
        $message = "El sistema ya estaba activo.";
    }
}

// Acción: Limpiar Caché Manualmente (Sin Laravel)
if (isset($_GET['op']) && $_GET['op'] === 'clear_cache') {
    $deletedFiles = 0;
    
    // 1. Limpiar vistas
    $viewsDir = $baseDir . '/storage/framework/views';
    if (is_dir($viewsDir)) {
        foreach (glob($viewsDir . '/*.php') as $file) {
            @unlink($file);
            $deletedFiles++;
        }
    }
    
    // 2. Limpiar cache de bootstrap
    $bootstrapCacheDir = $baseDir . '/bootstrap/cache';
    if (is_dir($bootstrapCacheDir)) {
        foreach (glob($bootstrapCacheDir . '/*.php') as $file) {
            @unlink($file);
            $deletedFiles++;
        }
    }

    $message = "Cachés eliminadas con éxito. Se borraron {$deletedFiles} archivos de caché.";
}

// Acción: Restaurar Backup
if (isset($_POST['op']) && $_POST['op'] === 'restore_backup') {
    $filename = basename($_POST['filename'] ?? '');
    $zipPath = $backupDir . '/' . $filename;
    if (!file_exists($zipPath)) {
        $zipPath = $baseDir . '/' . $filename;
    }
    
    if (file_exists($zipPath)) {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) === true) {
            // Respaldar código actual antes de pisar
            $zipBackup = new ZipArchive();
            $autoBackupName = 'emergency_auto_backup_' . date('Y_m_d_His') . '.zip';
            
            if ($zipBackup->open($backupDir . '/' . $autoBackupName, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                // Hacer backup rápido de directorios clave
                foreach (['app', 'routes', 'resources', 'config', 'database'] as $folder) {
                    $folderPath = $baseDir . '/' . $folder;
                    if (is_dir($folderPath)) {
                        $files = new RecursiveIteratorIterator(
                            new RecursiveDirectoryIterator($folderPath, RecursiveDirectoryIterator::SKIP_DOTS),
                            RecursiveIteratorIterator::LEAVES_ONLY
                        );
                        foreach ($files as $file) {
                            if (!$file->isDir()) {
                                $filePath = $file->getRealPath();
                                $relativePath = substr($filePath, strlen($baseDir) + 1);
                                $zipBackup->addFile($filePath, str_replace('\\', '/', $relativePath));
                            }
                        }
                    }
                }
                $zipBackup->close();
                $terminalOutput .= "Backup previo de emergencia creado: {$autoBackupName}\n";
            }

            // Descomprimir
            $isCpanel = file_exists($baseDir . '/public_html');
            $extractedCount = 0;
            
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $fileInZip = $zip->getNameIndex($i);
                $normalized = str_replace('\\', '/', $fileInZip);
                
                // Omitir .env por seguridad
                if (basename($normalized) === '.env' || $normalized === '.env') {
                    continue;
                }
                
                if (substr($normalized, -1) === '/') {
                    continue;
                }
                
                // Redirigir public/ a public_html/
                $targetName = $normalized;
                if ($isCpanel && strpos($normalized, 'public/') === 0) {
                    $targetName = 'public_html/' . substr($normalized, 7);
                }
                
                $destPath = $baseDir . '/' . $targetName;
                $destDir = dirname($destPath);
                
                if (!is_dir($destDir)) {
                    @mkdir($destDir, 0755, true);
                }
                
                $content = $zip->getFromIndex($i);
                if ($content !== false) {
                    if (@file_put_contents($destPath, $content) !== false) {
                        $extractedCount++;
                    }
                }
            }
            $zip->close();
            
            $message = "Backup '{$filename}' restaurado exitosamente. Se extrajeron {$extractedCount} archivos.";
            $terminalOutput .= "Extracción finalizada. Por favor recarga el sitio web.";
        } else {
            $message = "No se pudo abrir el archivo ZIP.";
            $messageType = 'error';
        }
    } else {
        $message = "El archivo de respaldo no existe.";
        $messageType = 'error';
    }
}

// Acción: Subir ZIP de emergencia
if (isset($_FILES['emergency_zip'])) {
    $file = $_FILES['emergency_zip'];
    if ($file['error'] === UPLOAD_ERR_OK && pathinfo($file['name'], PATHINFO_EXTENSION) === 'zip') {
        $tempPath = $backupDir . '/emergency_upload_' . time() . '.zip';
        if (move_uploaded_file($file['tmp_name'], $tempPath)) {
            $zip = new ZipArchive();
            if ($zip->open($tempPath) === true) {
                // Hacer backup rápido de directorios clave
                $zipBackup = new ZipArchive();
                $autoBackupName = 'emergency_auto_backup_upload_' . date('Y_m_d_His') . '.zip';
                
                if ($zipBackup->open($backupDir . '/' . $autoBackupName, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                    foreach (['app', 'routes', 'resources', 'config', 'database'] as $folder) {
                        $folderPath = $baseDir . '/' . $folder;
                        if (is_dir($folderPath)) {
                            $files = new RecursiveIteratorIterator(
                                new RecursiveDirectoryIterator($folderPath, RecursiveDirectoryIterator::SKIP_DOTS),
                                RecursiveIteratorIterator::LEAVES_ONLY
                            );
                            foreach ($files as $f) {
                                if (!$f->isDir()) {
                                    $filePath = $f->getRealPath();
                                    $relativePath = substr($filePath, strlen($baseDir) + 1);
                                    $zipBackup->addFile($filePath, str_replace('\\', '/', $relativePath));
                                }
                            }
                        }
                    }
                    $zipBackup->close();
                }

                $isCpanel = file_exists($baseDir . '/public_html');
                $extractedCount = 0;
                
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $fileInZip = $zip->getNameIndex($i);
                    $normalized = str_replace('\\', '/', $fileInZip);
                    
                    if (basename($normalized) === '.env' || $normalized === '.env') {
                        continue;
                    }
                    
                    if (substr($normalized, -1) === '/') {
                        continue;
                    }
                    
                    $targetName = $normalized;
                    if ($isCpanel && strpos($normalized, 'public/') === 0) {
                        $targetName = 'public_html/' . substr($normalized, 7);
                    }
                    
                    $destPath = $baseDir . '/' . $targetName;
                    $destDir = dirname($destPath);
                    if (!is_dir($destDir)) {
                        @mkdir($destDir, 0755, true);
                    }
                    
                    $content = $zip->getFromIndex($i);
                    if ($content !== false) {
                        if (@file_put_contents($destPath, $content) !== false) {
                            $extractedCount++;
                        }
                    }
                }
                $zip->close();
                @unlink($tempPath); // eliminar temporal
                
                $message = "ZIP de emergencia aplicado con éxito. Se extrajeron {$extractedCount} archivos.";
            } else {
                $message = "No se pudo abrir el archivo ZIP subido.";
                $messageType = 'error';
            }
        } else {
            $message = "Fallo al guardar el archivo temporal subido.";
            $messageType = 'error';
        }
    } else {
        $message = "Error en la subida del archivo o no es un ZIP válido.";
        $messageType = 'error';
    }
}

// 3. Recopilar estado para la UI
$laravelStatus = 'Funcional';
$laravelColor = '#10b981';
try {
    if (!file_exists($baseDir . '/vendor/autoload.php')) {
        throw new \Exception("Falta autoload.php");
    }
    // Intentar ver si compila sin errores
    // No podemos hacer include del bootstrap/app aquí porque chocaría si está roto
} catch (\Throwable $e) {
    $laravelStatus = 'Incompleto (Vendor ausente)';
    $laravelColor = '#f59e0b';
}

$isMaintMode = file_exists($downFile);

// Obtener listado de backups
$backups = [];
if (is_dir($backupDir)) {
    foreach (glob($backupDir . '/*.zip') as $file) {
        $backups[] = [
            'filename' => basename($file),
            'size' => round(filesize($file) / 1024 / 1024, 2) . ' MB',
            'date' => date('Y-m-d H:i:s', filemtime($file)),
            'timestamp' => filemtime($file),
            'source' => 'backup'
        ];
    }
}
// También escanear directorio raíz para zips
foreach (glob($baseDir . '/*.zip') as $file) {
    $filename = basename($file);
    if (str_starts_with($filename, 'V') || 
        str_starts_with($filename, 'ACTUALIZACION') || 
        str_starts_with($filename, 'DESPLIEGUE') ||
        str_starts_with($filename, 'actualizacion') ||
        str_starts_with($filename, 'despliegue')) {
        $backups[] = [
            'filename' => $filename,
            'size' => round(filesize($file) / 1024 / 1024, 2) . ' MB',
            'date' => date('Y-m-d H:i:s', filemtime($file)),
            'timestamp' => filemtime($file),
            'source' => 'root'
        ];
    }
}
usort($backups, function ($a, $b) {
    return $b['timestamp'] - $a['timestamp'];
});
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consola de Emergencia - Logística Empresa Base</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;700;900&family=Fira+Code:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: #0b0f19;
            color: #e2e8f0;
            font-family: 'Outfit', sans-serif;
            margin: 0;
            padding: 24px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #1e293b;
            padding-bottom: 16px;
            margin-bottom: 24px;
        }
        h1 {
            font-size: 26px;
            font-weight: 900;
            margin: 0;
            background: linear-gradient(to right, #ef4444, #f59e0b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .logout-btn {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.3s;
        }
        .logout-btn:hover {
            background: #ef4444;
            color: white;
        }
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 14px;
            font-weight: 600;
        }
        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #34d399;
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
        }
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }
        @media(max-width: 768px) {
            .grid {
                grid-template-columns: 1fr;
            }
        }
        .card {
            background: #151f32;
            border: 1px solid #1e293b;
            border-radius: 18px;
            padding: 24px;
        }
        .card-title {
            font-size: 16px;
            font-weight: 700;
            margin-top: 0;
            margin-bottom: 16px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 14px;
        }
        .status-item:last-child {
            margin-bottom: 0;
        }
        .status-val {
            font-weight: 700;
        }
        .btn-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .btn {
            padding: 10px 16px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #ef4444;
            color: white;
        }
        .btn-primary:hover {
            background: #dc2626;
        }
        .btn-secondary {
            background: #334155;
            color: white;
        }
        .btn-secondary:hover {
            background: #475569;
        }
        .btn-success {
            background: #10b981;
            color: white;
        }
        .btn-success:hover {
            background: #059669;
        }
        .btn-sm {
            padding: 6px 12px;
            font-size: 11px;
            border-radius: 6px;
        }
        .backup-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .backup-table th {
            text-align: left;
            padding: 10px;
            color: #64748b;
            border-bottom: 1px solid #1e293b;
        }
        .backup-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #1e293b;
            word-break: break-all;
        }
        .backup-table tr:last-child td {
            border-bottom: none;
        }
        .terminal {
            background: #020617;
            border: 1px solid #1e293b;
            border-radius: 12px;
            padding: 16px;
            font-family: 'Fira Code', monospace;
            font-size: 12px;
            color: #38bdf8;
            max-height: 200px;
            overflow-y: auto;
            white-space: pre-wrap;
            margin-top: 24px;
        }
        .upload-box {
            border: 2px dashed #1e293b;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            margin-top: 16px;
            background: rgba(15, 23, 42, 0.4);
        }
        input[type="file"] {
            display: none;
        }
        .file-label {
            background: #1e293b;
            border: 1px solid #334155;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 700;
            display: inline-block;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div>
                <h1>Consola de Recuperación de Emergencia</h1>
                <p style="margin: 4px 0 0 0; font-size: 12px; color: #64748b;">Herramientas del Núcleo del Servidor (Fuera de Laravel)</p>
            </div>
            <a href="safe_deploy.php?action=logout" class="logout-btn">Salir de Consola</a>
        </header>

        <?php if ($message !== ''): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="grid">
            <!-- Columna 1: Estado del Sistema y Controles Rápidos -->
            <div class="card">
                <h3 class="card-title">Estado del Entorno</h3>
                
                <div class="status-item">
                    <span>Estado del Core PHP:</span>
                    <span class="status-val" style="color: #10b981;">Online</span>
                </div>
                <div class="status-item">
                    <span>Pre-Arranque de Laravel:</span>
                    <span class="status-val" style="color: <?php echo $laravelColor; ?>;"><?php echo $laravelStatus; ?></span>
                </div>
                <div class="status-item">
                    <span>Modo Mantenimiento:</span>
                    <span class="status-val" style="color: <?php echo $isMaintMode ? '#f87171' : '#10b981'; ?>;">
                        <?php echo $isMaintMode ? 'ACTIVO (Modo Búnker)' : 'INACTIVO (Público)'; ?>
                    </span>
                </div>
                <div class="status-item">
                    <span>Mapeo cPanel (public_html):</span>
                    <span class="status-val"><?php echo file_exists($baseDir . '/public_html') ? 'Detectado' : 'No Detectado (Estándar)'; ?></span>
                </div>

                <h3 class="card-title" style="margin-top: 24px; margin-bottom: 12px;">Controles Rápidos</h3>
                <div class="btn-group">
                    <?php if ($isMaintMode): ?>
                        <a href="safe_deploy.php?op=maint_off" class="btn btn-success">Apagar Mantenimiento (Up)</a>
                    <?php else: ?>
                        <a href="safe_deploy.php?op=maint_on" class="btn btn-primary">Encender Mantenimiento (Down)</a>
                    <?php endif; ?>
                    <a href="safe_deploy.php?op=clear_cache" class="btn btn-secondary">Limpiar Caché (Borrado Físico)</a>
                </div>

                <h3 class="card-title" style="margin-top: 24px; margin-bottom: 12px;">Cargar Parche ZIP Directo</h3>
                <div class="upload-box">
                    <form method="POST" enctype="multipart/form-data">
                        <label for="emergency_zip" class="file-label">Seleccionar ZIP</label>
                        <input type="file" id="emergency_zip" name="emergency_zip" accept=".zip" required onchange="this.form.submit()">
                        <p style="margin: 0; font-size: 11px; color: #475569;">Cargará y extraerá el ZIP directamente en la raíz de la web, guardando un backup de emergencia automático previo.</p>
                    </form>
                </div>
            </div>

            <!-- Columna 2: Restaurar Copias de Seguridad -->
            <div class="card">
                <h3 class="card-title">Copias de Seguridad (Backups)</h3>
                <?php if (empty($backups)): ?>
                    <p style="color: #475569; font-style: italic;">No se encontraron backups en storage/app/backups/</p>
                <?php else: ?>
                    <div style="max-height: 420px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; padding-right: 6px;">
                        <?php foreach ($backups as $bk): ?>
                            <div style="background: rgba(30, 41, 59, 0.4); border: 1px solid #1e293b; padding: 12px; border-radius: 12px; display: flex; flex-direction: column; gap: 8px; justify-content: space-between;">
                                <div style="min-width: 0; flex: 1;">
                                    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-bottom: 4px;">
                                        <span style="font-weight: 700; color: #f8fafc; font-family: monospace; font-size: 11px; word-break: break-all;"><?php echo htmlspecialchars($bk['filename']); ?></span>
                                        <?php if (($bk['source'] ?? '') === 'root'): ?>
                                            <span style="font-size: 9px; font-weight: 950; background-color: rgba(239, 68, 68, 0.2); color: #ef4444; padding: 2px 6px; border-radius: 4px; text-transform: uppercase;">Parche</span>
                                        <?php else: ?>
                                            <span style="font-size: 9px; font-weight: 950; background-color: rgba(148, 163, 184, 0.15); color: #94a3b8; padding: 2px 6px; border-radius: 4px; text-transform: uppercase;">Respaldo</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="display: flex; gap: 12px; font-size: 10px; color: #64748b; font-weight: 500;">
                                        <span>📅 <?php echo $bk['date']; ?></span>
                                        <span>💾 <?php echo $bk['size']; ?></span>
                                    </div>
                                </div>
                                <div style="display: flex; justify-content: flex-end;">
                                    <form method="POST" style="display:inline; width: 100%;" onsubmit="return confirm('¿Estás seguro de que deseas restaurar esta copia de seguridad? Se pisará el código actual.');">
                                        <input type="hidden" name="op" value="restore_backup">
                                        <input type="hidden" name="filename" value="<?php echo htmlspecialchars($bk['filename']); ?>">
                                        <button type="submit" class="btn btn-primary btn-sm" style="padding: 6px 12px; font-size: 11px; width: 100%;">Restaurar</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($terminalOutput !== ''): ?>
            <div class="terminal"><?php echo htmlspecialchars($terminalOutput); ?></div>
        <?php endif; ?>
    </div>
</body>
</html>
