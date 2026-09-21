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
            $defaults = match ($u->role) {
                'admin' => ['recepcion', 'monitor_odc', 'operarios', 'reservar_cita', 'monitoreo', 'configuracion_erp', 'despliegue', 'usuarios', 'categorias'],
                'receptor' => ['recepcion', 'monitor_odc', 'operarios', 'reservar_cita'],
                'comprador' => ['recepcion', 'monitor_odc', 'reservar_cita', 'monitoreo'],
                'proveedor' => ['recepcion', 'reservar_cita'],
                default => ['recepcion', 'reservar_cita'],
            };

            if ($u->id == 1 || ($u->username ?? '') === 'Sistemas.Jeralthc') {
                $defaults = ['recepcion', 'monitor_odc', 'operarios', 'reservar_cita', 'monitoreo', 'configuracion_erp', 'despliegue', 'usuarios', 'categorias'];
            }

            DB::table('users')->where('id', $u->id)->update([
                'modulos_permitidos' => json_encode($defaults)
            ]);
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
