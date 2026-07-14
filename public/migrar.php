<?php
// Script para ejecutar migraciones desde el navegador en producción
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

try {
    Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    $output = Illuminate\Support\Facades\Artisan::output();
    echo "<h1>Migración exitosa</h1><pre>$output</pre>";
} catch (\Exception $e) {
    echo "<h1>Error en migración</h1><pre>" . $e->getMessage() . "</pre>";
}
