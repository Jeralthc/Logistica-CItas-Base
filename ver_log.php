<?php
$logPath = __DIR__ . '/../storage/logs/laravel.log';
if (file_exists($logPath)) {
    $content = file_get_contents($logPath);
    echo "<h1>Últimos errores de Laravel</h1>";
    echo "<pre style='background:#f4f4f4; padding:10px; border:1px solid #ccc;'>";
    
    // Solo mostrar las últimas 5000 letras para no saturar
    echo htmlspecialchars(substr($content, -5000));
    
    echo "</pre>";
} else {
    echo "<h1>No hay archivo laravel.log</h1>";
    echo "<p>Ruta buscada: $logPath</p>";
}
