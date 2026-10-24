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
        // 1. Corregir tipo_mercancia en citas existentes para ODC 000034164 (Distribuciones Principal Mérida)
        DB::table('appointments')
            ->whereIn('numero_oc', ['000034164', '34164', 'E000034164'])
            ->update([
                'tipo_mercancia' => 'Alimentos 1 (Viveres)',
                'updated_at' => now(),
            ]);

        // 2. Corregir en erp_ordenes_sync si existe
        DB::table('erp_ordenes_sync')
            ->whereIn('numero_oc', ['000034164', '34164', 'E000034164'])
            ->update([
                'categoria_sugerida' => 'Alimentos 1 (Viveres)',
                'updated_at' => now(),
            ]);

        // 3. Corregir cualquier cita en Depósito General (0102) o Hiper (0101) que haya quedado erróneamente como Perecederos
        DB::table('appointments')
            ->whereIn('muelle_asignado', ['0102', '0101'])
            ->where(function($q) {
                $q->where('tipo_mercancia', 'like', '%Pereceder%')
                  ->orWhere('tipo_mercancia', 'like', '%Charcuter%');
            })
            ->update([
                'tipo_mercancia' => 'Alimentos 1 (Viveres)',
                'updated_at' => now(),
            ]);

        // 4. Corregir cualquier orden sincronizada asignada a 0102 o 0101 que tenga sugerida Perecederos
        DB::table('erp_ordenes_sync')
            ->whereIn('destino', ['0102', '0101'])
            ->where(function($q) {
                $q->where('categoria_sugerida', 'like', '%Pereceder%')
                  ->orWhere('categoria_sugerida', 'like', '%Charcuter%');
            })
            ->update([
                'categoria_sugerida' => 'Alimentos 1 (Viveres)',
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No revert necesario
    }
};
