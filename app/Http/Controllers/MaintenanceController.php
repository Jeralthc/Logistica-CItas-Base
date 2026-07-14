<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class MaintenanceController extends Controller
{
    /**
     * Activa el modo de mantenimiento
     */
    public function activar(Request $request)
    {
        $validated = $request->validate([
            'end_time' => 'required|date|after:now',
        ]);

        // Guardar la fecha en storage para que la vista 503 pueda leerla
        Storage::disk('local')->put('maintenance_time.json', json_encode([
            'end_time' => $validated['end_time']
        ]));

        // Activar el modo de mantenimiento con el bypass secreto
        Artisan::call('down', [
            '--secret' => 'Empresa Base-admin'
        ]);

        // Retornar mensaje de éxito (esto será respondido antes de que el modo mantenimiento bloquee al propio admin si no ha usado el secreto aún)
        return response()->json([
            'message' => 'Modo de mantenimiento activado exitosamente.',
            'secret_url' => url('/Empresa Base-admin')
        ]);
    }

    /**
     * Desactiva el modo de mantenimiento
     */
    public function desactivar()
    {
        // Desactivar el modo de mantenimiento
        Artisan::call('up');

        // Eliminar el archivo de tiempo
        Storage::disk('local')->delete('maintenance_time.json');

        return response()->json([
            'message' => 'Modo de mantenimiento desactivado exitosamente.'
        ]);
    }
    
    /**
     * Verifica el estado actual del mantenimiento
     */
    public function estado()
    {
        $isDown = app()->isDownForMaintenance();
        $endTime = null;
        
        if (Storage::disk('local')->exists('maintenance_time.json')) {
            $data = json_decode(Storage::disk('local')->get('maintenance_time.json'), true);
            $endTime = $data['end_time'] ?? null;
        }

        return response()->json([
            'is_down' => $isDown,
            'end_time' => $endTime
        ]);
    }
}
