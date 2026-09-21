$source = "c:\laragon\www\logistica-citas"
$targetDir = "c:\laragon\www\logistica-citas\temp_zip_build"
$zipPath = "c:\laragon\www\logistica-citas\V138_Fix_Emergency_Disk_Cleaner.zip"

If (Test-Path $targetDir) { Remove-Item -Recurse -Force $targetDir }
If (Test-Path $zipPath) { Remove-Item -Force $zipPath }

New-Item -ItemType Directory -Force -Path $targetDir | Out-Null

$files = @(
    "app\Http\Controllers\LogisticaController.php",
    "app\Http\Controllers\UserController.php",
    "app\Http\Controllers\MonitoringController.php",
    "app\Http\Controllers\CitaController.php",
    "app\Http\Controllers\AuditController.php",
    "app\Http\Middleware\HandleInertiaRequests.php",
    "app\Http\Requests\Auth\LoginRequest.php",
    "app\Models\User.php",
    "app\Models\EmailLog.php",
    "config\webpush.php",
    "routes\web.php",
    "routes\api.php",
    "resources\js\Pages\Dashboard.vue",
    "resources\js\Pages\ReservarCita.vue",
    "resources\js\Pages\Usuarios.vue",
    "resources\js\Pages\MonitorOdc.vue",
    "resources\js\Pages\Monitoreo.vue",
    "resources\js\Pages\Auditoria.vue",
    "resources\js\Layouts\AuthenticatedLayout.vue",
    "app\Mail\NotificacionReactivacionOdc.php",
    "app\Console\Commands\NotificarOdcsHabilitadas.php",
    "resources\views\emails\odc_habilitada.blade.php",
    "resources\views\emails\odc_habilitada_registrado.blade.php",
    "resources\views\emails\odc_notificacion_disponible.blade.php",
    "database\migrations\2026_08_04_140000_add_rif_proveedor_to_erp_ordenes_sync.php",
    "database\migrations\2026_08_04_150000_create_email_logs_table.php",
    "public\test_habilitar_debug.php",
    "public\clear_cache.php"
)

foreach ($file in $files) {
    $srcFile = Join-Path $source $file
    if (Test-Path $srcFile) {
        $destFile = Join-Path $targetDir $file
        $destDir = Split-Path $destFile -Parent
        if (!(Test-Path $destDir)) {
            New-Item -ItemType Directory -Force -Path $destDir | Out-Null
        }
        Copy-Item -Path $srcFile -Destination $destFile -Force
    }
}

# Add compiled public/build folder
$srcBuild = Join-Path $source "public\build"
if (Test-Path $srcBuild) {
    $destBuild = Join-Path $targetDir "public\build"
    New-Item -ItemType Directory -Force -Path $destBuild | Out-Null
    Copy-Item -Path "$srcBuild\*" -Destination $destBuild -Recurse -Force
}

if (Get-Command tar -ErrorAction SilentlyContinue) {
    Set-Location $targetDir
    tar -a -c -f $zipPath *
    Set-Location $source
} else {
    Compress-Archive -Path "$targetDir\*" -DestinationPath $zipPath -Force
}
Remove-Item -Recurse -Force $targetDir -ErrorAction SilentlyContinue
Write-Host "ZIP created at $zipPath"
