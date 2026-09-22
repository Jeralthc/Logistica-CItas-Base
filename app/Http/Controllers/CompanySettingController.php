<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Carbon\Carbon;

class CompanySettingController extends Controller
{
    /**
     * Vista de personalización White-Label y Almacenes
     */
    public function index()
    {
        $settings = Schema::hasTable('company_settings')
            ? DB::table('company_settings')->first()
            : null;

        $almacenes = Schema::hasTable('warehouses')
            ? DB::table('warehouses')->orderBy('nombre')->get()
            : [];

        return Inertia::render('ConfiguracionEmpresa', [
            'settings' => $settings,
            'almacenes' => $almacenes,
        ]);
    }

    /**
     * Guarda la identidad corporativa y configuración regional
     */
    public function guardarAjustes(Request $request)
    {
        $request->validate([
            'nombre_empresa' => 'required|string|max:150',
            'rif_empresa' => 'nullable|string|max:30',
            'color_primario' => 'required|string|max:20',
            'zona_horaria' => 'required|string|max:50',
            'moneda' => 'required|string|max:10',
            'email_contacto' => 'nullable|email|max:100',
            'telefono_contacto' => 'nullable|string|max:50',
        ]);

        $existe = DB::table('company_settings')->first();

        if ($existe) {
            DB::table('company_settings')->where('id', $existe->id)->update([
                'nombre_empresa' => $request->get('nombre_empresa'),
                'rif_empresa' => $request->get('rif_empresa'),
                'color_primario' => $request->get('color_primario'),
                'zona_horaria' => $request->get('zona_horaria'),
                'moneda' => $request->get('moneda'),
                'email_contacto' => $request->get('email_contacto'),
                'telefono_contacto' => $request->get('telefono_contacto'),
                'updated_at' => Carbon::now(),
            ]);
        } else {
            DB::table('company_settings')->insert([
                'nombre_empresa' => $request->get('nombre_empresa'),
                'rif_empresa' => $request->get('rif_empresa'),
                'color_primario' => $request->get('color_primario'),
                'zona_horaria' => $request->get('zona_horaria'),
                'moneda' => $request->get('moneda'),
                'email_contacto' => $request->get('email_contacto'),
                'telefono_contacto' => $request->get('telefono_contacto'),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Configuración corporativa guardada con éxito.'
        ]);
    }

    /**
     * Crea un nuevo almacén o centro de distribución
     */
    public function crearAlmacen(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'codigo' => 'required|string|max:30|unique:warehouses,codigo',
            'direccion' => 'nullable|string|max:255',
            'muelles_totales' => 'required|integer|min:1|max:50',
        ]);

        DB::table('warehouses')->insert([
            'nombre' => $request->get('nombre'),
            'codigo' => strtoupper($request->get('codigo')),
            'direccion' => $request->get('direccion'),
            'muelles_totales' => $request->get('muelles_totales'),
            'activo' => true,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Almacén registrado exitosamente.'
        ]);
    }
}