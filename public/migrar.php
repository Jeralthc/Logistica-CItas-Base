<?php
// Script para ejecutar migraciones desde el navegador en producción
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (isset($_GET['diag'])) {
    $citas = DB::table('appointments')->where('numero_oc', 'like', '%33598%')->orWhere('numero_oc', 'like', '%32826%')->get();
    $emails = DB::table('email_logs')->where('numero_oc', 'like', '%33598%')->orWhere('numero_oc', 'like', '%32826%')->get();
    $allEmails = DB::table('email_logs')->orderBy('id', 'desc')->limit(10)->get();
    $syncs = DB::table('erp_ordenes_sync')->where('numero_oc', 'like', '%33598%')->orWhere('numero_oc', 'like', '%32826%')->get();
    header('Content-Type: application/json');
    echo json_encode([
        'citas' => $citas,
        'emails' => $emails,
        'allEmails' => $allEmails,
        'syncs' => $syncs
    ], JSON_PRETTY_PRINT);
    exit;
}

try {
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
