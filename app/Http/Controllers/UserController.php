<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Listar todos los usuarios y estadísticas.
     */
    public function index()
    {
        $users = User::with(['contactos:id,user_id,nombre,email,telefono'])
            ->select(['id', 'name', 'username', 'email', 'role', 'activo', 'rif', 'es_galpon', 'modulos_permitidos', 'created_at'])
            ->orderBy('id', 'desc')
            ->get();

        $metrics = [
            'total' => $users->count(),
            'activos' => $users->where('activo', true)->count(),
            'inactivos' => $users->where('activo', false)->count(),
            'admin' => $users->where('role', 'admin')->count(),
            'receptor' => $users->where('role', 'receptor')->count(),
            'comprador' => $users->where('role', 'comprador')->count(),
            'proveedor' => $users->where('role', 'proveedor')->count(),
        ];

        return response()->json([
            'status' => 'Exitoso',
            'users' => $users,
            'metrics' => $metrics,
        ]);
    }

    /**
     * Registrar un nuevo usuario.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'required|string|email|max:255',
            'role' => ['required', Rule::in(['admin', 'receptor', 'comprador', 'proveedor'])],
            'es_galpon' => 'nullable|boolean',
            'password' => 'required|string|min:6|confirmed',
            'contactos' => 'nullable|array',
            'emails_adicionales' => 'nullable|array',
        ], [
            'username.unique' => 'El RIF / Usuario ya se encuentra registrado.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres.',
        ]);

        $userData = [
            'name' => $validated['name'],
            'username' => trim($validated['username']),
            'email' => strtolower(trim($validated['email'])),
            'role' => $validated['role'],
            'es_galpon' => $request->boolean('es_galpon'),
            'password' => Hash::make($validated['password']),
            'activo' => true,
        ];

        if ($validated['role'] === 'proveedor') {
            $rifLimpio = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $validated['username']));
            $userData['rif'] = $rifLimpio ?: $validated['username'];
        }

        if ($request->has('modulos_permitidos') && (auth()->id() === 1 || auth()->user()?->username === 'Sistemas.Jeralthc')) {
            $modulosInput = $request->input('modulos_permitidos');
            if (is_array($modulosInput)) {
                $modulosValidos = ['recepcion', 'monitor_odc', 'operarios', 'reservar_cita', 'monitoreo', 'configuracion_erp', 'despliegue', 'usuarios', 'categorias'];
                $userData['modulos_permitidos'] = array_values(array_intersect($modulosInput, $modulosValidos));
            }
        }

        $user = User::create($userData);

        if ($user->role === 'proveedor') {
            $this->sincronizarContactosProveedor($user, $request);
        }

        $user->load('contactos:id,user_id,nombre,email,telefono');

        return response()->json([
            'status' => 'Exitoso',
            'mensaje' => "Usuario {$user->name} creado correctamente.",
            'user' => $user,
        ]);
    }

    /**
     * Actualizar datos de un usuario existente.
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', Rule::in(['admin', 'receptor', 'comprador', 'proveedor'])],
            'es_galpon' => 'nullable|boolean',
            'contactos' => 'nullable|array',
            'emails_adicionales' => 'nullable|array',
            'modulos_permitidos' => 'nullable',
        ], [
            'username.unique' => 'El RIF / Usuario ya pertenece a otro registro.',
        ]);

        $validated['es_galpon'] = $request->boolean('es_galpon');
        $validated['email'] = strtolower(trim($validated['email']));

        if ($validated['role'] === 'proveedor') {
            $rifLimpio = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $validated['username']));
            $validated['rif'] = $rifLimpio ?: $validated['username'];
        } else {
            $validated['rif'] = null;
        }

        // Permisos de módulos: Solo Superadmin (Jeralth / ID 1 / Sistemas.Jeralthc) puede asignarlos o modificarlos
        if ($request->has('modulos_permitidos')) {
            $currentUserId = auth()->id();
            $currentUsername = auth()->user()?->username;
            if ($currentUserId !== 1 && $currentUsername !== 'Sistemas.Jeralthc') {
                return response()->json([
                    'status' => 'Error',
                    'message' => 'Solo el Superadministrador (Jeralth) puede asignar o modificar los permisos de módulos.',
                ], 403);
            }

            $modulosInput = $request->input('modulos_permitidos');
            if (is_array($modulosInput)) {
                $modulosValidos = ['recepcion', 'monitor_odc', 'operarios', 'reservar_cita', 'monitoreo', 'configuracion_erp', 'despliegue', 'usuarios', 'categorias'];
                $validated['modulos_permitidos'] = array_values(array_intersect($modulosInput, $modulosValidos));
            } elseif ($modulosInput === null) {
                $validated['modulos_permitidos'] = null;
            }
        } else {
            unset($validated['modulos_permitidos']);
        }

        $user->update($validated);

        if ($user->role === 'proveedor') {
            $this->sincronizarContactosProveedor($user, $request);
        }

        $user->load('contactos:id,user_id,nombre,email,telefono');

        return response()->json([
            'status' => 'Exitoso',
            'mensaje' => "Datos del usuario {$user->name} actualizados.",
            'user' => $user,
        ]);
    }

    /**
     * Sincronizar los correos y contactos de un proveedor en proveedor_contactos.
     */
    private function sincronizarContactosProveedor(User $user, Request $request): void
    {
        $primaryEmail = strtolower(trim($user->email));

        // 1. Asegurar contacto principal
        \App\Models\ProveedorContacto::updateOrInsert(
            ['user_id' => $user->id, 'email' => $primaryEmail],
            [
                'nombre' => $user->name,
                'telefono' => '0000000000',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $contactosInput = $request->input('contactos', []);
        $emailsAdicionales = $request->input('emails_adicionales', []);

        $correosValidos = [$primaryEmail];

        // Procesar lista de contactos completos
        if (is_array($contactosInput)) {
            foreach ($contactosInput as $c) {
                if (is_string($c)) {
                    $em = strtolower(trim($c));
                    $nom = $user->name;
                    $tel = '0000000000';
                } elseif (is_array($c)) {
                    $em = strtolower(trim($c['email'] ?? ''));
                    $nom = trim($c['nombre'] ?? '') ?: $user->name;
                    $tel = trim($c['telefono'] ?? '') ?: '0000000000';
                } else {
                    continue;
                }

                if ($em !== '' && filter_var($em, FILTER_VALIDATE_EMAIL)) {
                    $correosValidos[] = $em;
                    \App\Models\ProveedorContacto::updateOrInsert(
                        ['user_id' => $user->id, 'email' => $em],
                        [
                            'nombre' => $nom,
                            'telefono' => $tel,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                }
            }
        }

        // Procesar emails adicionales sueltos si los hay
        if (is_array($emailsAdicionales)) {
            foreach ($emailsAdicionales as $em) {
                $em = strtolower(trim((string)$em));
                if ($em !== '' && filter_var($em, FILTER_VALIDATE_EMAIL)) {
                    $correosValidos[] = $em;
                    \App\Models\ProveedorContacto::updateOrInsert(
                        ['user_id' => $user->id, 'email' => $em],
                        [
                            'nombre' => $user->name,
                            'telefono' => '0000000000',
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                }
            }
        }

        $correosValidos = array_unique($correosValidos);

        // Eliminar contactos que hayan sido removidos explícitamente
        \App\Models\ProveedorContacto::where('user_id', $user->id)
            ->whereNotIn('email', $correosValidos)
            ->delete();
    }

    /**
     * Restablecer / cambiar contraseña de un usuario.
     */
    public function updatePassword(Request $request, User $user)
    {
        $validated = $request->validate([
            'password' => 'required|string|min:6|confirmed',
        ], [
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres.',
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'status' => 'Exitoso',
            'mensaje' => "Contraseña de {$user->name} actualizada exitosamente.",
        ]);
    }

    /**
     * Activar o desactivar cuenta de usuario.
     */
    public function toggleActivo(User $user)
    {
        if ($user->id === auth()->id()) {
            return response()->json([
                'status' => 'Error',
                'mensaje' => 'No puedes desactivar tu propia cuenta de administrador.',
            ], 400);
        }

        $user->activo = !$user->activo;
        $user->save();

        $estadoTexto = $user->activo ? 'activada' : 'desactivada';

        return response()->json([
            'status' => 'Exitoso',
            'mensaje' => "La cuenta de {$user->name} ha sido {$estadoTexto}.",
            'user' => $user,
        ]);
    }
}
