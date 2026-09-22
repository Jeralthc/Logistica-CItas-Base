# Script de sincronizacion automatica desde c:\laragon\www\logistica-citas hacia D:\Logistica-CItas-Base
param (
    [string]$MensajeCommit = "feat: sincronizacion automatica con ultimas mejoras de logistica-citas"
)

Write-Host "=====================================================" -ForegroundColor Cyan
Write-Host "SINCRONIZANDO CON D:\Logistica-CItas-Base..." -ForegroundColor Cyan
Write-Host "=====================================================" -ForegroundColor Cyan

$sourcePath = "c:\laragon\www\logistica-citas"
$targetPath = "D:\Logistica-CItas-Base"
$tempZip = Join-Path $sourcePath "sync_temp_export.zip"

if (-not (Test-Path $targetPath)) {
    Write-Host "Error: La ruta $targetPath no existe." -ForegroundColor Red
    exit 1
}

# 1. Crear exportacion limpia de git HEAD
Write-Host "1/4 Exportando archivos limpios de Git..." -ForegroundColor Yellow
git -C $sourcePath archive -o $tempZip HEAD

if (-not (Test-Path $tempZip)) {
    Write-Host "Error al generar el paquete de sincronizacion." -ForegroundColor Red
    exit 1
}

# 2. Descomprimir en D:\Logistica-CItas-Base
Write-Host "2/4 Aplicando cambios en $targetPath..." -ForegroundColor Yellow
Expand-Archive -Path $tempZip -DestinationPath $targetPath -Force
Remove-Item $tempZip -Force

# Limpiar archivos residuales no deseados en D:
if (Test-Path "$targetPath\app-DJoqI_HR.js") { Remove-Item "$targetPath\app-DJoqI_HR.js" -Force }
if (Test-Path "$targetPath\temp_deploy") { Remove-Item "$targetPath\temp_deploy" -Recurse -Force }
if (Test-Path "$targetPath\query") { Remove-Item "$targetPath\query" -Force }

# 3. Commit en D:
Write-Host "3/4 Creando commit en el repositorio Base..." -ForegroundColor Yellow
git -C $targetPath add -A
$status = git -C $targetPath status --porcelain
if ($status) {
    git -C $targetPath commit -m $MensajeCommit
    
    # 4. Push a GitHub
    Write-Host "4/4 Subiendo cambios a GitHub (Logistica-CItas-Base)..." -ForegroundColor Yellow
    git -C $targetPath push origin main
    Write-Host "Sincronizacion y push completados exitosamente!" -ForegroundColor Green
} else {
    Write-Host "No hay cambios nuevos que sincronizar. El repositorio Base ya esta al dia." -ForegroundColor Green
}

Write-Host "=====================================================" -ForegroundColor Cyan
