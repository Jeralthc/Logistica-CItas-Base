<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function setupProveedor(Request $request): Response
    {
        return Inertia::render('Auth/SetupProveedor', [
            'rif' => $request->query('rif', ''),
            'email' => $request->query('email', ''),
            'name' => $request->query('name', ''),
        ]);
    }

    public function storeSetupProveedor(Request $request): RedirectResponse
    {
        $request->validate([
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::where('username', $request->username)->first();

        if ($user) {
            // El usuario ya existe (tal vez creado a medias en otro paso), le asignamos la nueva clave
            $user->password = Hash::make($request->password);
            $user->save();
        } else {
            // Si por alguna razón no existe, lo creamos
            $user = User::create([
                'name' => $request->name ?? 'Proveedor Externo',
                'username' => $request->username,
                'email' => $request->email,
                'role' => 'proveedor',
                'password' => Hash::make($request->password),
            ]);
            event(new Registered($user));
        }

        Auth::login($user);

        return redirect()->route('dashboard');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'username' => [
                'required',
                'string',
                'max:255',
                'unique:'.User::class,
                function ($attribute, $value, $fail) use ($request) {
                    $cargo = $request->input('cargo');
                    if (in_array($cargo, ['comprador', 'receptor'])) {
                        if (!preg_match('/^[a-zA-Z0-9_]+\.[a-zA-Z0-9_]+$/', $value)) {
                            $fail('El usuario interno debe tener el formato Departamento.Nombre (Ej: Recepcion.Romulo).');
                        }
                    } elseif ($cargo === 'proveedor') {
                        if (!preg_match('/^[JVEGjveg]-?\d{8,9}-?\d?$/', $value)) {
                            $fail('El usuario para proveedores debe ser un RIF válido (Ej: J-12345678-9).');
                        }
                    }
                },
            ],
            'email' => 'nullable|string|lowercase|email|max:255',
            'cargo' => 'required|string|in:comprador,receptor,proveedor',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'role' => $request->cargo, // cargo is already validated as 'comprador' or 'receptor'
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        Auth::login($user);

        if ($user->role === 'comprador') {
            return redirect()->route('reservar-cita');
        }

        return redirect()->route('dashboard');
    }
}
