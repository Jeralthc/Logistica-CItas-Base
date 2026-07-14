<?php
// diagnostico.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Diagnóstico de Laravel en DirectAdmin</h1>";

// 1. Limpiar caché de bootstrap (causa #1 de errores 500 al mudar de Windows a Linux)
$cacheFiles = ['packages.php', 'services.php', 'config.php', 'routes-v7.php'];
foreach ($cacheFiles as $file) {
    $path = __DIR__ . '/../bootstrap/cache/' . $file;
    if (file_exists($path)) {
        unlink($path);
        echo "<p style='color:green'>Caché de bootstrap eliminado: $file</p>";
    }
}

// 2. Limpiar vistas cacheadas (rutas absolutas de C:\laragon que rompen en Linux)
$viewsPath = __DIR__ . '/../storage/framework/views/';
if (is_dir($viewsPath)) {
    $files = glob($viewsPath . '*.php');
    foreach ($files as $file) {
        unlink($file);
    }
    echo "<p style='color:green'>Vistas cacheadas eliminadas correctamente.</p>";
} else {
    echo "<p style='color:red'>No se encontró la carpeta de vistas o no hay permisos.</p>";
}

// 3. Verificar permisos de storage (debe ser escribible)
$storagePath = __DIR__ . '/../storage';
if (is_dir($storagePath)) {
    if (!is_writable($storagePath)) {
        chmod($storagePath, 0755);
        echo "<p style='color:orange'>Permisos de storage ajustados.</p>";
    } else {
        echo "<p style='color:green'>La carpeta storage tiene permisos correctos.</p>";
    }
}

// 4. Probar cargar el vendor (causa de error por versión de PHP)
echo "<h2>Prueba de arranque de Laravel:</h2>";
try {
    require __DIR__.'/../vendor/autoload.php';
    echo "<p style='color:green'>Vendor cargado correctamente.</p>";
    
    $app = require_once __DIR__.'/../bootstrap/app.php';
    echo "<p style='color:green'>Aplicación iniciada correctamente.</p>";
    
    echo "<h3>Versión de PHP del Servidor: " . phpversion() . "</h3>";
    echo "<h2 style='color:blue'>¡TODO LISTO! Ve a la página principal y recarga con Ctrl+F5.</h2>";
} catch (\Throwable $e) {
    echo "<h3 style='color:red'>ERROR FATAL:</h3>";
    echo "<pre>" . $e->getMessage() . "</pre>";
    echo "<b>Archivo:</b> " . $e->getFile() . " en línea " . $e->getLine();
}
