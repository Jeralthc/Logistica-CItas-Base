<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $loginInput = $this->input('username');
        $password = $this->input('password');
        $remember = $this->boolean('remember');
        $loginClean = strtoupper(preg_replace('/[^A-Z0-9]/i', '', trim($loginInput)));

        $authenticated = Auth::attempt(['username' => $loginInput, 'password' => $password], $remember)
                      || Auth::attempt(['email' => $loginInput, 'password' => $password], $remember)
                      || Auth::attempt(['rif' => $loginInput, 'password' => $password], $remember)
                      || Auth::attempt(['rif' => $loginClean, 'password' => $password], $remember);

        if (!$authenticated && !empty($loginClean)) {
            // Intentar auto-provisionar proveedor si tiene ODC habilitada
            $hasSync = \Illuminate\Support\Facades\DB::table('erp_ordenes_sync')
                ->where('estatus_habilitacion', 'habilitada')
                ->where(function($q) use ($loginClean) {
                    $q->where('rif_proveedor', 'like', "%{$loginClean}%")
                      ->orWhere('resumen_json', 'like', "%{$loginClean}%");
                })->first();

            if ($hasSync) {
                $existingUser = \App\Models\User::where('username', $loginClean)->orWhere('rif', $loginClean)->first();
                if (!$existingUser) {
                    \App\Models\User::create([
                        'name' => $hasSync->proveedor ?: 'Proveedor ' . $loginClean,
                        'username' => $loginClean,
                        'rif' => $loginClean,
                        'email' => strtolower($loginClean) . '@proveedor.suraki.net',
                        'role' => 'proveedor',
                        'password' => \Illuminate\Support\Facades\Hash::make($password ?: $loginClean),
                    ]);
                    $authenticated = Auth::attempt(['username' => $loginClean, 'password' => $password], $remember)
                                  || Auth::attempt(['username' => $loginClean, 'password' => $loginClean], $remember);
                }
            }
        }

        if (! $authenticated) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'username' => 'Estas credenciales no coinciden con nuestros registros.',
            ]);
        }

        // Verificar si la cuenta está activa
        $user = Auth::user();
        if ($user && isset($user->activo) && !$user->activo) {
            Auth::logout();
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'username' => 'Su cuenta se encuentra desactivada. Contacte al administrador del sistema.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'username' => 'Demasiados intentos de acceso. Por favor intente nuevamente en ' . $seconds . ' segundos.',
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('username')).'|'.$this->ip());
    }
}
