<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:reset-sistema')]
#[Description('Limpia las citas, logs, notificaciones y restablece los usuarios y las ODCs del ERP a su estado inicial')]
class ResetSistema extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando restablecimiento del sistema...');

        try {
            \Illuminate\Support\Facades\DB::beginTransaction();

            // Desactivar restricciones de claves foráneas
            if (\Illuminate\Support\Facades\DB::getDriverName() === 'pgsql') {
                \Illuminate\Support\Facades\DB::statement('SET CONSTRAINTS ALL DEFERRED');
            } else {
                \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
            }

            $this->warn('- Limpiando citas (appointments)...');
            \Illuminate\Support\Facades\DB::table('appointments')->truncate();

            $this->warn('- Limpiando logs de citas (appointment_route_logs)...');
            \Illuminate\Support\Facades\DB::table('appointment_route_logs')->truncate();

            $this->warn('- Limpiando auditoría del sistema (system_audit_logs)...');
            \Illuminate\Support\Facades\DB::table('system_audit_logs')->truncate();

            $this->warn('- Limpiando notificaciones...');
            \Illuminate\Support\Facades\DB::table('notificaciones')->truncate();

            $this->warn('- Limpiando contactos de proveedores (proveedor_contactos)...');
            \Illuminate\Support\Facades\DB::table('proveedor_contactos')->truncate();

            $this->warn('- Limpiando colas de correos y trabajos (jobs, failed_jobs)...');
            if (\Illuminate\Support\Facades\Schema::hasTable('jobs')) {
                \Illuminate\Support\Facades\DB::table('jobs')->truncate();
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('failed_jobs')) {
                \Illuminate\Support\Facades\DB::table('failed_jobs')->truncate();
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('job_batches')) {
                \Illuminate\Support\Facades\DB::table('job_batches')->truncate();
            }

            $this->warn('- Restableciendo estado de órdenes en erp_ordenes_sync...');
            \Illuminate\Support\Facades\DB::table('erp_ordenes_sync')->update([
                'estatus_habilitacion' => 'pendiente',
                'habilitada_por_user_id' => null,
            ]);

            $this->warn('- Eliminando únicamente proveedores registrados dinámicamente...');
            $seededProviders = ['J070014733', 'E844767415', 'J505302930'];
            \Illuminate\Support\Facades\DB::table('users')
                ->where('role', 'proveedor')
                ->whereNotIn('username', $seededProviders)
                ->delete();

            $this->info('- Asegurando categorías de rendimiento...');
            $this->call('db:seed', ['--class' => 'CategoriasRendimientoSeeder']);

            if (\Illuminate\Support\Facades\DB::getDriverName() !== 'pgsql') {
                \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();
            }

            \Illuminate\Support\Facades\DB::commit();
            $this->info('✅ ¡Sistema restablecido con éxito! Todo está como nuevo.');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            $this->error('❌ Error al restablecer el sistema: ' . $e->getMessage());
        }
    }
}
