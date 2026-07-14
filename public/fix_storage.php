<?php
// Script de reparación: Crear carpetas de storage necesarias para Laravel
// Subir a public_html/ y abrir en el navegador: https://citsur.Empresa Base.net/fix_storage.php
// Se autodestruye después de ejecutarse.

$base = __DIR__ . '/../storage';

$dirs = [
    $base . '/app/public',
    $base . '/app/private',
    $base . '/framework/cache/data',
    $base . '/framework/sessions',
    $base . '/framework/testing',
    $base . '/framework/views',
    $base . '/logs',
];

echo "<h2>Reparando estructura de Storage...</h2><ul>";

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        echo "<li style='color:green'>✅ Creada: " . str_replace($base, 'storage', $dir) . "</li>";
    } else {
        echo "<li style='color:gray'>⏩ Ya existe: " . str_replace($base, 'storage', $dir) . "</li>";
    }
}

// Crear .gitignore en cada una
foreach ([$base . '/app/public', $base . '/framework/cache/data', $base . '/framework/sessions', $base . '/framework/views', $base . '/logs'] as $d) {
    $gi = $d . '/.gitignore';
    if (!file_exists($gi)) {
        file_put_contents($gi, "*\n!.gitignore\n");
    }
}

echo "</ul><h3 style='color:green'>✅ ¡Storage reparado! Recarga tu página principal.</h3>";

// Autodestruir
unlink(__FILE__);
echo "<p style='color:gray; font-size:12px'>Este script se ha eliminado automáticamente.</p>";
