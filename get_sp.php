<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$result = \Illuminate\Support\Facades\DB::connection('sqlsrv')->select("SELECT OBJECT_DEFINITION(OBJECT_ID('sp_CalcularVolumenOC')) AS definition");
echo json_encode($result);
