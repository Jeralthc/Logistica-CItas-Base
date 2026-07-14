<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            if (!Schema::hasColumn('appointments', 'numero_factura')) {
                $table->string('numero_factura')->nullable()->after('observaciones');
            }
            if (!Schema::hasColumn('appointments', 'peso_factura_ton')) {
                $table->decimal('peso_factura_ton', 8, 3)->nullable()->after('numero_factura');
            }
            if (!Schema::hasColumn('appointments', 'formato_carga')) {
                $table->string('formato_carga')->nullable()->after('peso_factura_ton');
            }
            if (!Schema::hasColumn('appointments', 'tipo_vehiculo')) {
                $table->string('tipo_vehiculo')->nullable()->after('formato_carga');
            }
            if (!Schema::hasColumn('appointments', 'tiempo_adicional')) {
                $table->integer('tiempo_adicional')->default(0)->after('tipo_vehiculo');
            }
            if (!Schema::hasColumn('appointments', 'categoria_rendimiento_id')) {
                $table->unsignedBigInteger('categoria_rendimiento_id')->nullable()->after('tiempo_adicional');
                $table->foreign('categoria_rendimiento_id')->references('id')->on('categorias_rendimiento')->nullOnDelete();
            }
            if (!Schema::hasColumn('appointments', 'habilitada_por_user_id')) {
                $table->unsignedBigInteger('habilitada_por_user_id')->nullable()->after('categoria_rendimiento_id');
            }
            if (!Schema::hasColumn('appointments', 'datos_proveedor_completos')) {
                $table->boolean('datos_proveedor_completos')->default(false)->after('habilitada_por_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $columns = [
                'numero_factura', 'peso_factura_ton', 'formato_carga',
                'tipo_vehiculo', 'tiempo_adicional', 'categoria_rendimiento_id',
                'habilitada_por_user_id', 'datos_proveedor_completos'
            ];
            if (Schema::hasColumn('appointments', 'categoria_rendimiento_id')) {
                $table->dropForeign(['categoria_rendimiento_id']);
            }
            foreach ($columns as $col) {
                if (Schema::hasColumn('appointments', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
