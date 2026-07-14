<?php
// Sube a public_html/ y abre: https://citsur.Empresa Base.net/fix_routes.php
$root = realpath(__DIR__ . '/..');
$routesDir = $root . '/routes';
if (!is_dir($routesDir)) mkdir($routesDir, 0755, true);

// === api.php ===
file_put_contents($routesDir . '/api.php', '<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LogisticaController;
use App\Http\Controllers\ErpApiController;
use App\Http\Controllers\OperarioController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\CitaController;
use App\Http\Controllers\AuditController;

/*
|--------------------------------------------------------------------------
| Rutas de Pruebas (Legacy)
|--------------------------------------------------------------------------
*/
Route::get(\'/prueba-sql/{orden}\', function ($orden) {
    try {
        $resultado = DB::connection(\'sqlsrv\')->select("EXEC sp_CalcularVolumenOC ?", [$orden]);
        return response()->json([\'status\' => \'Exitoso\', \'datos\' => $resultado]);
    } catch (\Exception $e) {
        return response()->json([\'error\' => $e->getMessage()], 500);
    }
});

/*
|--------------------------------------------------------------------------
| Rutas Oficiales del Sistema Empresa Base
|--------------------------------------------------------------------------
*/

Route::middleware(\'web\')->group(function () {
    // Búsqueda completa de orden (factura, fechas, tiempos, productos)
    Route::get(\'/orden-completa/{orden}\', [LogisticaController::class, \'buscarOrdenCompleta\']);
    Route::post(\'/orden-recalcular/{orden}\', [LogisticaController::class, \'recalcularTiempo\']);
    
    // Legacy
    Route::get(\'/orden-detalles/{orden}\', [LogisticaController::class, \'buscarOrden\']);

    // Operarios
    Route::get(\'/operarios\', [OperarioController::class, \'index\']);
    Route::post(\'/operarios\', [OperarioController::class, \'store\']);
    Route::put(\'/operarios/{operario}\', [OperarioController::class, \'update\']);
    Route::post(\'/operarios/{operario}/toggle\', [OperarioController::class, \'toggleDisponible\']);
    Route::delete(\'/operarios/{operario}\', [OperarioController::class, \'destroy\']);

    // Notificaciones
    Route::get(\'/notificaciones\', [NotificacionController::class, \'index\']);
    Route::post(\'/notificaciones/{notificacion}/leer\', [NotificacionController::class, \'marcarLeida\']);
    Route::post(\'/notificaciones/leer-todas\', [NotificacionController::class, \'marcarTodasLeidas\']);
    Route::post(\'/notificaciones/sincronizar\', [NotificacionController::class, \'sincronizar\']);

    // Citas / Reservaciones
    Route::get(\'/citas/slots\', [CitaController::class, \'slotsDisponibles\']);
    Route::post(\'/citas/reservar\', [CitaController::class, \'reservar\']);
    Route::post(\'/citas/registrar-proveedor\', [CitaController::class, \'registrarProveedor\']);
    Route::get(\'/citas\', [CitaController::class, \'listar\']);
    Route::post(\'/citas/{id}/reprogramar\', [CitaController::class, \'reprogramar\']);
    Route::post(\'/citas/{id}/cancelar\', [CitaController::class, \'cancelar\']);

    // Configuración ERP
    Route::get(\'/configuracion-erp\', [\App\Http\Controllers\ConfigController::class, \'getErpConfig\']);
    Route::post(\'/configuracion-erp\', [\App\Http\Controllers\ConfigController::class, \'updateErpConfig\']);

    // Auditoría Global
    Route::get(\'/auditoria/logs\', [AuditController::class, \'getLogs\']);
});

// Rutas del Agente API (Server-to-Server) - Sin middleware web
Route::prefix(\'erp\')->group(function () {
    Route::get(\'/ordenes-pendientes\', [ErpApiController::class, \'getOrdenesPendientes\']);
    Route::get(\'/buscar-orden/{orden}\', [ErpApiController::class, \'getOrden\']);
    Route::post(\'/recalcular-tiempo/{orden}\', [ErpApiController::class, \'recalcularTiempo\']);
});

Route::post(\'/sync/recibir\', [App\Http\Controllers\SyncController::class, \'recibir\']);
');

// === web.php ===
file_put_contents($routesDir . '/web.php', '<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get(\'/\', function () {
    return Inertia::render(\'Welcome\', [
        \'canLogin\' => Route::has(\'login\'),
        \'canRegister\' => Route::has(\'register\'),
        \'laravelVersion\' => Application::VERSION,
        \'phpVersion\' => PHP_VERSION,
    ]);
});

Route::get(\'/dashboard\', function () {
    return Inertia::render(\'Dashboard\');
})->middleware([\'auth\', \'verified\', \'role:receptor,admin,proveedor\'])->name(\'dashboard\');

Route::get(\'/operarios\', function () {
    return Inertia::render(\'Operarios\');
})->middleware([\'auth\', \'verified\', \'role:receptor,admin\'])->name(\'operarios\');

Route::get(\'/reservar-cita\', function () {
    return Inertia::render(\'ReservarCita\');
})->name(\'reservar-cita\');

Route::get(\'/monitor-odc\', function () {
    return Inertia::render(\'MonitorOdc\');
})->middleware([\'auth\', \'verified\', \'role:receptor,admin\'])->name(\'monitor-odc\');

Route::get(\'/auditoria\', [\App\Http\Controllers\AuditController::class, \'index\'])
    ->middleware([\'auth\', \'verified\', \'role:admin\'])
    ->name(\'auditoria\');

Route::get(\'/configuracion-erp\', function () {
    return Inertia::render(\'ConfiguracionERP\');
})->middleware([\'auth\', \'verified\', \'role:admin\'])->name(\'configuracion-erp\');

Route::middleware(\'auth\')->group(function () {
    Route::get(\'/profile\', [ProfileController::class, \'edit\'])->name(\'profile.edit\');
    Route::patch(\'/profile\', [ProfileController::class, \'update\'])->name(\'profile.update\');
    Route::delete(\'/profile\', [ProfileController::class, \'destroy\'])->name(\'profile.destroy\');

    // Rutas de Mantenimiento (Solo admin)
    Route::middleware(\'role:admin\')->group(function () {
        Route::get(\'/api/mantenimiento/estado\', [\App\Http\Controllers\MaintenanceController::class, \'estado\']);
        Route::post(\'/api/mantenimiento/activar\', [\App\Http\Controllers\MaintenanceController::class, \'activar\']);
        Route::post(\'/api/mantenimiento/desactivar\', [\App\Http\Controllers\MaintenanceController::class, \'desactivar\']);
    });
});

Route::get(\'/instalar-bd\', function () {
    try {
        if (\Illuminate\Support\Facades\Schema::hasTable(\'users\')) {
            return \'ACCESO DENEGADO\';
        }
        \Illuminate\Support\Facades\Artisan::call(\'migrate\', [
            \'--force\' => true,
            \'--seed\' => true
        ]);
        return \'¡Instalación de Base de Datos completada con éxito! Ya puedes entrar al sistema.\';
    } catch (\Exception $e) {
        return \'Error en la instalación: \' . $e->getMessage();
    }
});

require __DIR__.\'/auth.php\';
');

// === auth.php ===
file_put_contents($routesDir . '/auth.php', '<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware(\'guest\')->group(function () {
    Route::get(\'register\', [RegisteredUserController::class, \'create\'])
        ->name(\'register\');

    Route::post(\'register\', [RegisteredUserController::class, \'store\']);

    Route::get(\'login\', [AuthenticatedSessionController::class, \'create\'])
        ->name(\'login\');

    Route::post(\'login\', [AuthenticatedSessionController::class, \'store\']);

    Route::get(\'forgot-password\', [PasswordResetLinkController::class, \'create\'])
        ->name(\'password.request\');

    Route::post(\'forgot-password\', [PasswordResetLinkController::class, \'store\'])
        ->name(\'password.email\');

    Route::get(\'reset-password/{token}\', [NewPasswordController::class, \'create\'])
        ->name(\'password.reset\');

    Route::post(\'reset-password\', [NewPasswordController::class, \'store\'])
        ->name(\'password.store\');
});

Route::middleware(\'auth\')->group(function () {
    Route::get(\'verify-email\', EmailVerificationPromptController::class)
        ->name(\'verification.notice\');

    Route::get(\'verify-email/{id}/{hash}\', VerifyEmailController::class)
        ->middleware([\'signed\', \'throttle:6,1\'])
        ->name(\'verification.verify\');

    Route::post(\'email/verification-notification\', [EmailVerificationNotificationController::class, \'store\'])
        ->middleware(\'throttle:6,1\')
        ->name(\'verification.send\');

    Route::get(\'confirm-password\', [ConfirmablePasswordController::class, \'show\'])
        ->name(\'password.confirm\');

    Route::post(\'confirm-password\', [ConfirmablePasswordController::class, \'store\']);

    Route::put(\'password\', [PasswordController::class, \'update\'])->name(\'password.update\');

    Route::post(\'logout\', [AuthenticatedSessionController::class, \'destroy\'])
        ->name(\'logout\');
});
');

// === console.php ===
file_put_contents($routesDir . '/console.php', '<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command(\'inspire\', function () {
    $this->comment(Inspiring::quote());
})->purpose(\'Display an inspiring quote\');
');

echo "<h2 style='color:green'>✅ Las 4 rutas fueron creadas exitosamente</h2>";
echo "<ul>";
echo "<li>routes/api.php</li>";
echo "<li>routes/web.php</li>";
echo "<li>routes/auth.php</li>";
echo "<li>routes/console.php</li>";
echo "</ul>";
echo "<p><strong>Recarga tu página principal ahora.</strong></p>";
unlink(__FILE__);
echo "<p style='color:gray;font-size:11px'>Script eliminado automáticamente.</p>";
