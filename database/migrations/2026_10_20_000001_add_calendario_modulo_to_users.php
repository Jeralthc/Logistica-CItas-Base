<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $users = DB::table('users')->get();
        foreach ($users as $u) {
            // Para admin, comprador y receptor: asegurar que 'calendario' esté en modulos_permitidos
            if (in_array($u->role, ['admin', 'comprador', 'receptor'], true) || $u->id == 1 || ($u->username ?? '') === 'Sistemas.Jeralthc') {
                $modulos = [];
                if (!empty($u->modulos_permitidos)) {
                    $decoded = json_decode($u->modulos_permitidos, true);
                    if (is_array($decoded)) {
                        $modulos = $decoded;
                    }
                }

                if (empty($modulos)) {
                    $modulos = match ($u->role) {
                        'admin' => ['recepcion', 'monitor_odc', 'operarios', 'reservar_cita', 'monitoreo', 'configuracion_erp', 'despliegue', 'usuarios', 'categorias', 'calendario'],
                        'receptor' => ['recepcion', 'monitor_odc', 'operarios', 'reservar_cita', 'calendario'],
                        'comprador' => ['recepcion', 'monitor_odc', 'reservar_cita', 'monitoreo', 'calendario'],
                        default => ['recepcion', 'calendario'],
                    };
                } else {
                    if (!in_array('calendario', $modulos, true)) {
                        $modulos[] = 'calendario';
                    }
                }

                DB::table('users')->where('id', $u->id)->update([
                    'modulos_permitidos' => json_encode(array_values(array_unique($modulos)))
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op
    }
};
