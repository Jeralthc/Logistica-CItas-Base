<?php
// Script para ejecutar migraciones desde el navegador en producción
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (isset($_GET['diag'])) {
    $citas = DB::table('appointments')->where('numero_oc', 'like', '%34164%')->orWhere('numero_oc', 'like', '%34148%')->orWhereDate('fecha_cita', '2026-10-02')->get();
    $emails = DB::table('email_logs')->where('numero_oc', 'like', '%34164%')->orWhere('numero_oc', 'like', '%34148%')->get();
    $allEmails = DB::table('email_logs')->orderBy('id', 'desc')->limit(5)->get();
    $syncs = DB::table('erp_ordenes_sync')->where('numero_oc', 'like', '%34164%')->orWhere('numero_oc', 'like', '%34148%')->get();
    header('Content-Type: application/json');
    echo json_encode([
        'citas' => $citas,
        'emails' => $emails,
        'allEmails' => $allEmails,
        'syncs' => $syncs
    ], JSON_PRETTY_PRINT);
    exit;
} elseif (isset($_GET['check_odcs'])) {
    $syncs = DB::table('erp_ordenes_sync')->whereIn('numero_oc', ['35217', '000035217', '35158', '000035158', '35152', '000035152', '35103', '000035103'])->get();
    header('Content-Type: application/json');
    echo json_encode($syncs, JSON_PRETTY_PRINT);
    exit;
} elseif (isset($_GET['check_oct27'])) {
    $citas27 = DB::table('appointments')->whereDate('fecha_cita', '2026-10-27')->get();
    $polarCitas = DB::table('appointments')->where('proveedor', 'like', '%POLAR%')->orWhere('proveedor', 'like', '%SAN SIMON%')->get();
    $syncPolar = DB::table('erp_ordenes_sync')->where('proveedor', 'like', '%POLAR%')->orderBy('updated_at', 'desc')->limit(10)->get();
    $cita34971 = DB::table('appointments')->where('numero_oc', 'like', '%34971%')->get();
    header('Content-Type: application/json');
    echo json_encode([
        'citas_27' => $citas27,
        'polar_citas' => $polarCitas,
        'cita_34971' => $cita34971,
        'sync_polar' => $syncPolar
    ], JSON_PRETTY_PRINT);
    exit;
} elseif (isset($_GET['get_logs'])) {
    $logPath = storage_path('logs/laravel.log');
    $lines = [];
    if (file_exists($logPath)) {
        $content = file_get_contents($logPath);
        $allLines = explode("\n", $content);
        $lines = array_slice($allLines, -150);
    }
    header('Content-Type: text/plain');
    echo implode("\n", $lines);
    exit;
}

try {
    // Limpieza de citas de prueba residuales
    DB::table('appointments')->where('numero_oc', 'like', 'TEST-%')->delete();

    Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    $output = Illuminate\Support\Facades\Artisan::output();

    if (isset($_GET['seed']) && $_GET['seed'] === 'test') {
        Illuminate\Support\Facades\Artisan::call('db:seed', [
            '--class' => 'Database\\Seeders\\TestProviderAndOrdersSeeder',
            '--force' => true,
        ]);
        $output .= "\n" . Illuminate\Support\Facades\Artisan::output();
    }

    echo "<h1>Operación exitosa</h1><pre>$output</pre>";
} catch (\Exception $e) {
    echo "<h1>Error en ejecución</h1><pre>" . $e->getMessage() . "</pre>";
}
