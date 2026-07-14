<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if (file_exists(base_path('public_html'))) {
            $this->app->usePublicPath(base_path('public_html'));
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Registrar Logins en la bitácora
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Login::class, function ($event) {
            \App\Services\AuditLogger::log(
                module: 'Autenticación',
                action: 'Inicio de Sesión',
                motive: 'Ingreso al sistema',
                auditableType: \App\Models\User::class,
                auditableId: $event->user->id
            );
        });
        // Configurar reglas de contraseñas: Flexible pero segura (Permite RIF)
        \Illuminate\Validation\Rules\Password::defaults(function () {
            return \Illuminate\Validation\Rules\Password::min(8)
                ->letters() // Debe contener al menos una letra (ej. la 'V' o 'J' del RIF)
                ->numbers(); // Debe contener al menos un número
        });
    }
}
