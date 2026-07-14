<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategoriasRendimientoSeeder extends Seeder
{
    public function run(): void
    {
        $categorias = [
            ['nombre' => 'Alimentos 1 (Viveres)', 'tiempo_fijo' => 20, 'velocidad_descarga' => 0.8],
            ['nombre' => 'Alimentos 2 (Golosinas, Confites)', 'tiempo_fijo' => 20, 'velocidad_descarga' => 0.4],
            ['nombre' => 'No Alimentos 1 (Cuidado del Hogar, Ropa)', 'tiempo_fijo' => 20, 'velocidad_descarga' => 0.8],
            ['nombre' => 'No Alimentos 2 (Cuidado Personal, Perfumeria)', 'tiempo_fijo' => 20, 'velocidad_descarga' => 0.4],
            ['nombre' => 'No Alimentos 3 (Desechables, Papel, Plasticos, Carton)', 'tiempo_fijo' => 20, 'velocidad_descarga' => 0.6],
            ['nombre' => 'No Alimentos 4 (Papeleria, Jugueteria)', 'tiempo_fijo' => 20, 'velocidad_descarga' => 0.05],
            ['nombre' => 'Perecederos 1 (Charcuteria)', 'tiempo_fijo' => 20, 'velocidad_descarga' => 0.05],
            ['nombre' => 'Perecederos 2 (Carniceria, Pescaderia)', 'tiempo_fijo' => 20, 'velocidad_descarga' => 0.06],
            ['nombre' => 'Perecederos 3 (Frutas y Verduras)', 'tiempo_fijo' => 20, 'velocidad_descarga' => 0.04],
            ['nombre' => 'Licores', 'tiempo_fijo' => 20, 'velocidad_descarga' => 0.06],
            ['nombre' => 'Farmacia', 'tiempo_fijo' => 20, 'velocidad_descarga' => 0.06],
            ['nombre' => 'Electronicos', 'tiempo_fijo' => 20, 'velocidad_descarga' => 0.05],
        ];

        foreach ($categorias as $cat) {
            DB::table('categorias_rendimiento')->updateOrInsert(
                ['nombre' => $cat['nombre']],
                array_merge($cat, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}
