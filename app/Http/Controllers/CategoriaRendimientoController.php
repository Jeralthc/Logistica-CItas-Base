<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CategoriaRendimiento;

class CategoriaRendimientoController extends Controller
{
    /**
     * List all categories
     */
    public function index()
    {
        $categorias = CategoriaRendimiento::orderBy('nombre')->get();
        return response()->json(['categorias' => $categorias]);
    }

    /**
     * Update the parameters for a given category
     */
    public function update(Request $request, $id)
    {
        // Require admin role is handled by middleware
        
        $validated = $request->validate([
            'tiempo_fijo' => 'required|integer|min:0',
            'velocidad_descarga' => 'required|numeric|min:0'
        ]);

        $categoria = CategoriaRendimiento::findOrFail($id);
        
        $categoria->tiempo_fijo = $validated['tiempo_fijo'];
        $categoria->velocidad_descarga = $validated['velocidad_descarga'];
        $categoria->save();

        return response()->json([
            'message' => 'Categoría actualizada exitosamente',
            'categoria' => $categoria
        ]);
    }
}
