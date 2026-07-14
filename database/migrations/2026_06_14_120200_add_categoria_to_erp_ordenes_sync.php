<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('erp_ordenes_sync', function (Blueprint $table) {
            if (!Schema::hasColumn('erp_ordenes_sync', 'categoria_sugerida')) {
                $table->string('categoria_sugerida')->nullable()->after('detalles_json');
            }
            if (!Schema::hasColumn('erp_ordenes_sync', 'peso_estimado_ton')) {
                $table->decimal('peso_estimado_ton', 8, 3)->nullable()->after('categoria_sugerida');
            }
            if (!Schema::hasColumn('erp_ordenes_sync', 'estatus_habilitacion')) {
                $table->string('estatus_habilitacion')->default('pendiente')->after('peso_estimado_ton');
            }
            if (!Schema::hasColumn('erp_ordenes_sync', 'habilitada_por_user_id')) {
                $table->unsignedBigInteger('habilitada_por_user_id')->nullable()->after('estatus_habilitacion');
            }
        });
    }

    public function down(): void
    {
        Schema::table('erp_ordenes_sync', function (Blueprint $table) {
            $columns = ['categoria_sugerida', 'peso_estimado_ton', 'estatus_habilitacion', 'habilitada_por_user_id'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('erp_ordenes_sync', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
