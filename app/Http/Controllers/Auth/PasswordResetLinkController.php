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
        ]);

        $user = \App\Models\User::where('username', $request->username)->first();

        if (!$user || !$user->email) {
            throw ValidationException::withMessages([
                'username' => ['No podemos encontrar un usuario con este Usuario/RIF o no tiene un correo asignado.'],
            ]);
        }

        // We will send the password reset link to this user using their email
        $status = Password::sendResetLink(
            ['email' => $user->email]
        );

        if ($status == Password::RESET_LINK_SENT) {
            return back()->with('status', 'Te hemos enviado el enlace de recuperación a tu correo electrónico.');
        }

        throw ValidationException::withMessages([
            'username' => [trans($status)],
        ]);
    }
}
