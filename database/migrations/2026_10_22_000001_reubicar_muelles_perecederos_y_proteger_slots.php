<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\CitaController;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Reubicar citas de perecederos activas asignadas erróneamente a muelles de secos (0101 / 0102)
        $citas = DB::table('appointments')
            ->whereIn('estatus', ['programada', 'en muelle'])
            ->get();

        foreach ($citas as $c) {
            $esPerecedero = CitaController::esPerecederosCita($c);
            $muelle = trim((string)$c->muelle_asignado);

            if ($esPerecedero && in_array($muelle, ['0101', '0102', '01', '02', '03', '04', '0180'])) {
                $tipoNorm = strtolower(($c->tipo_mercancia ?? '') . ' ' . ($c->proveedor ?? ''));
                $esCarne = str_contains($tipoNorm, 'carne') || str_contains($tipoNorm, 'pollo') || str_contains($tipoNorm, 'pescado') || str_contains($tipoNorm, 'congelado');
                $nuevoMuelle = $esCarne ? '0140' : '0141';

                DB::table('appointments')->where('id', $c->id)->update([
                    'muelle_asignado' => $nuevoMuelle,
                    'updated_at' => now(),
                ]);
            }
        }

        // 2. Re-enrutar órdenes de compra en erp_ordenes_sync que tengan destino 0101/0102 siendo perecederos
        $syncRows = DB::table('erp_ordenes_sync')->get();
        foreach ($syncRows as $row) {
            $cat = $row->categoria_sugerida ?? '';
            $destino = trim((string)$row->destino);
            if (in_array($cat, ['Perecederos 1', 'Perecederos 2', 'Perecederos 3 (Fruver)']) || str_contains(strtolower($cat), 'perecedero') || str_contains(strtolower($cat), 'fruver')) {
                if (in_array($destino, ['0101', '0102', '0103', '']) || empty($destino)) {
                    $nuevoDestino = ($cat === 'Perecederos 1') ? '0140' : '0141';
                    DB::table('erp_ordenes_sync')->where('id', $row->id)->update([
                        'destino' => $nuevoDestino,
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No destructivo
    }
};
