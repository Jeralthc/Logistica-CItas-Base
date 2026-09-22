# ============================================================
# Setup de entorno local para logistica-citas
# Uso: . .\setup_env.ps1  (ejecutar con "dot-source")
# ============================================================
# Este script coloca PHP 8.3.30 al INICIO del PATH para que
# todos los comandos "php" en esta sesion de PowerShell usen
# la version correcta. NO afecta otras ventanas ni otros proyectos.
# ============================================================

$PHP83 = "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64"

if (Test-Path $PHP83) {
    # Remover cualquier otra version de PHP del PATH de esta sesion
    $paths = $env:PATH -split ";" | Where-Object { 
        $_ -notmatch "php" -or $_ -eq $PHP83 
    }
    
    # Insertar PHP 8.3 al inicio
    $env:PATH = "$PHP83;" + ($paths -join ";")
    
    Write-Host ""
    Write-Host "  ✅ PHP 8.3.30 activado para esta sesion" -ForegroundColor Green
    Write-Host "  📁 Proyecto: logistica-citas" -ForegroundColor Cyan
    Write-Host ""
    php -v | Select-Object -First 1
    Write-Host ""
} else {
    Write-Host "  ❌ ERROR: No se encontro PHP 8.3.30 en $PHP83" -ForegroundColor Red
}
