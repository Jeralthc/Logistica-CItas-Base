<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'username' => 'required|string',
        ], [
            'username.required' => 'Por favor ingresa tu Usuario, Correo o RIF.',
        ]);

        $input = trim($request->username);
        $inputClean = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $input));

        // Buscar al usuario de forma flexible: por username, email o rif (insensible a mayúsculas/minúsculas y guiones)
        $user = \App\Models\User::where('username', $input)
            ->orWhere('email', $input)
            ->orWhereRaw('LOWER(username) = ?', [strtolower($input)])
            ->orWhereRaw('LOWER(email) = ?', [strtolower($input)])
            ->orWhere('rif', $input)
            ->orWhere('rif', $inputClean)
            ->orWhere('username', $inputClean)
            ->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'username' => ['No encontramos ninguna cuenta asociada a este Usuario, Correo o RIF.'],
            ]);
        }

        // Si el correo es placeholder sintético (@proveedor.suraki.net)
        if (!$user->email || str_contains(strtolower($user->email), '@proveedor.suraki.net')) {
            $contactoValido = \App\Models\ProveedorContacto::where('user_id', $user->id)
                ->where('email', 'not like', '%@proveedor.suraki.net')
                ->whereNotNull('email')
                ->first();

            if ($contactoValido && filter_var($contactoValido->email, FILTER_VALIDATE_EMAIL)) {
                $user->email = $contactoValido->email;
                $user->save();
            } else {
                throw ValidationException::withMessages([
                    'username' => ['Este usuario no tiene un correo electrónico válido registrado para recibir el enlace. Por favor contacte al departamento de Compras o Soporte.'],
                ]);
            }
        }

        try {
            $status = Password::sendResetLink(
                ['email' => $user->email]
            );

            if ($status == Password::RESET_LINK_SENT) {
                // Enmascarar el correo para mostrar confirmación amigable
                $parts = explode('@', $user->email);
                $namePart = $parts[0];
                $domainPart = $parts[1] ?? '';
                $masked = substr($namePart, 0, 2) . str_repeat('*', max(3, strlen($namePart) - 3)) . substr($namePart, -1) . '@' . $domainPart;

                return back()->with('status', "Hemos enviado el enlace de recuperación a tu correo ({$masked}). Revisa tu bandeja de entrada o carpeta de spam.");
            }

            throw ValidationException::withMessages([
                'username' => [trans($status)],
            ]);
        } catch (ValidationException $ve) {
            throw $ve;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Error enviando enlace de restablecimiento: ' . $e->getMessage());
            throw ValidationException::withMessages([
                'username' => ['Ocurrió un error al enviar el correo: ' . $e->getMessage()],
            ]);
        }
    }
}
