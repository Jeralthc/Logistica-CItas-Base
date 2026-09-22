<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('appointments', 'qr_token')) {
                $table->string('qr_token', 64)->nullable()->unique()->after('id');
            }
            if (!Schema::hasColumn('appointments', 'estado_patio')) {
                $table->string('estado_patio', 30)->default('programada')->after('estatus');
            }
            if (!Schema::hasColumn('appointments', 'fecha_llegada_garita')) {
                $table->timestamp('fecha_llegada_garita')->nullable()->after('estado_patio');
            }
            if (!Schema::hasColumn('appointments', 'fecha_llamado_muelle')) {
                $table->timestamp('fecha_llamado_muelle')->nullable()->after('fecha_llegada_garita');
            }
            if (!Schema::hasColumn('appointments', 'fecha_inicio_descarga')) {
                $table->timestamp('fecha_inicio_descarga')->nullable()->after('fecha_llamado_muelle');
            }
            if (!Schema::hasColumn('appointments', 'fecha_salida')) {
                $table->timestamp('fecha_salida')->nullable()->after('fecha_completada');
            }
            if (!Schema::hasColumn('appointments', 'garita_user_id')) {
                $table->unsignedBigInteger('garita_user_id')->nullable()->after('fecha_salida');
            }
            if (!Schema::hasColumn('appointments', 'chofer_nombre')) {
                $table->string('chofer_nombre')->nullable()->after('garita_user_id');
            }
            if (!Schema::hasColumn('appointments', 'chofer_cedula')) {
                $table->string('chofer_cedula', 30)->nullable()->after('chofer_nombre');
            }
            if (!Schema::hasColumn('appointments', 'chofer_telefono')) {
                $table->string('chofer_telefono', 30)->nullable()->after('chofer_cedula');
            }
            if (!Schema::hasColumn('appointments', 'placa_vehiculo')) {
                $table->string('placa_vehiculo', 30)->nullable()->after('chofer_telefono');
            }
            if (!Schema::hasColumn('appointments', 'garita_observaciones')) {
                $table->text('garita_observaciones')->nullable()->after('placa_vehiculo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $cols = [
                'qr_token', 'estado_patio', 'fecha_llegada_garita', 'fecha_llamado_muelle',
                'fecha_inicio_descarga', 'fecha_salida', 'garita_user_id', 'chofer_nombre',
                'chofer_cedula', 'chofer_telefono', 'placa_vehiculo', 'garita_observaciones'
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('appointments', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};