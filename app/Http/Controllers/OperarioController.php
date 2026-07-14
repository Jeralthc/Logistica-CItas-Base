<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Operario;

class OperarioController extends Controller
{
    public function index()
    {
        $operarios = Operario::orderBy('tipo')->orderBy('nombre')->get();

        $resumen = [
            'receptores_total' => Operario::where('tipo', 'receptor')->count(),
            'receptores_disponibles' => Operario::where('tipo', 'receptor')->where('disponible', true)->count(),
            'carga_total' => Operario::where('tipo', 'carga')->count(),
            'carga_disponibles' => Operario::where('tipo', 'carga')->where('disponible', true)->count(),
        ];

        return response()->json([
            'operarios' => $operarios,
            'resumen' => $resumen,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'cedula' => 'required|string|max:20|unique:operarios,cedula',
            'tipo' => 'required|in:receptor,carga',
            'turno' => 'sometimes|string|in:diurno,nocturno',
        ]);

        $operario = Operario::create($validated);

        return response()->json([
            'message' => 'Operario registrado exitosamente.',
            'operario' => $operario,
        ], 201);
    }

    public function update(Request $request, Operario $operario)
    {
        $validated = $request->validate([
            'nombre' => 'sometimes|string|max:255',
            'cedula' => 'sometimes|string|max:20|unique:operarios,cedula,' . $operario->id,
            'tipo' => 'sometimes|in:receptor,carga',
            'disponible' => 'sometimes|boolean',
            'turno' => 'sometimes|string|in:diurno,nocturno',
        ]);

        $operario->update($validated);

        return response()->json([
            'message' => 'Operario actualizado.',
            'operario' => $operario,
        ]);
    }

    public function toggleDisponible(Operario $operario)
    {
        $operario->update(['disponible' => !$operario->disponible]);

        return response()->json([
            'message' => $operario->disponible ? 'Operario disponible.' : 'Operario no disponible.',
            'operario' => $operario,
        ]);
    }

    public function destroy(Operario $operario)
    {
        $operario->delete();

        return response()->json(['message' => 'Operario eliminado.']);
    }
}
