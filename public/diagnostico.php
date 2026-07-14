<?php
// DIAGNÓSTICO COMPLETO - Sube a public_html/ y abre en navegador
// https://citsur.Empresa Base.net/diagnostico.php
ini_set('display_errors', 1);
$root = realpath(__DIR__ . '/..');
echo "<h1>🔍 Diagnóstico del Servidor</h1>";
echo "<p>Raíz: <code>$root</code></p>";

$critical = [
    'bootstrap/app.php',
    'bootstrap/providers.php',
    'config/app.php',
    'config/database.php',
    'routes/web.php',
    'routes/api.php',
    'routes/auth.php',
    'routes/console.php',
    'artisan',
    'composer.json',
    '.env',
    'vendor/autoload.php',
    'storage/framework/views',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/logs',
];

echo "<h2>Archivos y Carpetas Críticas</h2><table border='1' cellpadding='6'>";
echo "<tr><th>Archivo</th><th>Estado</th></tr>";
$missing = [];
foreach ($critical as $f) {
    $path = $root . '/' . $f;
    $exists = file_exists($path) || is_dir($path);
    $color = $exists ? 'green' : 'red';
    $status = $exists ? '✅ OK' : '❌ FALTA';
    echo "<tr><td><code>$f</code></td><td style='color:$color;font-weight:bold'>$status</td></tr>";
    if (!$exists) $missing[] = $f;
}
echo "</table>";

if (count($missing) > 0) {
    echo "<h2 style='color:red'>⚠️ Faltan " . count($missing) . " archivos/carpetas</h2>";
    echo "<p>Reporta esta lista para generar el ZIP correcto.</p>";
} else {
    echo "<h2 style='color:green'>✅ Todo OK</h2>";
}

// NO se autodestruye para que puedas revisarlo
echo "<p style='color:gray;font-size:11px'>Elimina este archivo manualmente cuando termines.</p>";
