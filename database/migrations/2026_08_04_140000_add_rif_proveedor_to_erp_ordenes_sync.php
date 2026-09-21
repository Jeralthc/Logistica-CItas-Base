<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('erp_ordenes_sync', function (Blueprint $table) {
            if (!Schema::hasColumn('erp_ordenes_sync', 'rif_proveedor')) {
                $table->string('rif_proveedor')->nullable()->after('proveedor')->index();
            }
        });

        // Poblar rif_proveedor desde resumen_json existente para órdenes ya habilitadas
        $habilitadas = \Illuminate\Support\Facades\DB::table('erp_ordenes_sync')
            ->where('estatus_habilitacion', 'habilitada')
            ->whereNull('rif_proveedor')
            ->get();

        foreach ($habilitadas as $row) {
            $resumen = json_decode($row->resumen_json, true);
            $rif = $resumen['Codigo_Proveedor'] ?? $resumen['c_RIF'] ?? $resumen['Cod_Proveedor'] ?? null;
            if ($rif) {
                \Illuminate\Support\Facades\DB::table('erp_ordenes_sync')
                    ->where('numero_oc', $row->numero_oc)
                    ->update(['rif_proveedor' => $rif]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('erp_ordenes_sync', function (Blueprint $table) {
            if (Schema::hasColumn('erp_ordenes_sync', 'rif_proveedor')) {
                $table->dropColumn('rif_proveedor');
            }
        });
    }
};
