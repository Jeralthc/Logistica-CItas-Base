<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'email', 'password', 'role', 'activo', 'rif', 'es_galpon', 'modulos_permitidos'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    // Cargar HasPushSubscriptions solo si el paquete está instalado
    public function initializeHasPushSubscriptionsIfAvailable()
    {
        // Se usa boot/initialize dinámico en su lugar
    }

    public static function bootHasPushSubscriptionsIfAvailable()
    {
        // placeholder
    }

    /**
     * Relación con push_subscriptions (compatible con o sin paquete webpush)
     */
    public function pushSubscriptions()
    {
        if (trait_exists(\NotificationChannels\WebPush\HasPushSubscriptions::class)) {
            return $this->morphMany(\NotificationChannels\WebPush\PushSubscription::class, 'subscribable');
        }
        // Retornar relación vacía si el paquete no está instalado
        return $this->hasMany(self::class, 'id', 'id')->whereRaw('1=0');
    }

    /**
     * Canal WebPush: Retorna siempre una colección (nunca null) para evitar crash en WebPushChannel
     */
    public function routeNotificationForWebPush()
    {
        return $this->pushSubscriptions ? $this->pushSubscriptions()->get() : collect();
    }

    /**
     * Crear o actualizar una suscripción push
     */
    public function updatePushSubscription($endpoint, $key = null, $token = null, $contentEncoding = null)
    {
        if (trait_exists(\NotificationChannels\WebPush\HasPushSubscriptions::class)) {
            // Usar el método del trait original
            $this->pushSubscriptions()->updateOrCreate(
                ['endpoint' => $endpoint],
                [
                    'public_key' => $key,
                    'auth_token' => $token,
                    'content_encoding' => $contentEncoding,
                ]
            );
            return;
        }

        // Fallback manual si el trait no existe
        \Illuminate\Support\Facades\DB::table('push_subscriptions')->updateOrInsert(
            ['endpoint' => $endpoint, 'subscribable_id' => $this->id, 'subscribable_type' => get_class($this)],
            ['public_key' => $key, 'auth_token' => $token, 'content_encoding' => $contentEncoding, 'updated_at' => now()]
        );
    }

    /**
     * Eliminar una suscripción push
     */
    public function deletePushSubscription($endpoint)
    {
        if (trait_exists(\NotificationChannels\WebPush\HasPushSubscriptions::class)) {
            $this->pushSubscriptions()->where('endpoint', $endpoint)->delete();
            return;
        }

        \Illuminate\Support\Facades\DB::table('push_subscriptions')
            ->where('endpoint', $endpoint)
            ->where('subscribable_id', $this->id)
            ->delete();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
            'es_galpon' => 'boolean',
            'modulos_permitidos' => 'array',
        ];
    }

    /**
     * Módulos predeterminados según el rol del usuario.
     */
    public static function modulosPorDefecto(?string $role): array
    {
        return match ($role) {
            'admin' => ['recepcion', 'monitor_odc', 'operarios', 'reservar_cita', 'monitoreo', 'configuracion_erp', 'despliegue', 'usuarios', 'categorias'],
            'receptor' => ['recepcion', 'monitor_odc', 'operarios', 'reservar_cita'],
            'comprador' => ['recepcion', 'monitor_odc', 'reservar_cita', 'monitoreo'],
            'proveedor' => ['recepcion', 'reservar_cita'],
            default => ['recepcion'],
        };
    }

    protected static function booted()
    {
        static::creating(function ($user) {
            if (empty($user->modulos_permitidos) && !empty($user->role)) {
                $user->modulos_permitidos = self::modulosPorDefecto($user->role);
            }
        });
    }

    /**
     * Verificar si el usuario tiene acceso a un módulo específico.
     * Jeralth (ID 1 / Sistemas.Jeralthc) tiene acceso irrestricto a todos los módulos.
     */
    public function tieneModulo(string $modulo): bool
    {
        if ($this->id === 1 || $this->username === 'Sistemas.Jeralthc') {
            return true;
        }

        if (is_array($this->modulos_permitidos) && !empty($this->modulos_permitidos)) {
            return in_array($modulo, $this->modulos_permitidos, true);
        }

        // Si modulos_permitidos es null o vacío, fallback a los permisos autorizados por rol
        $permitidos = self::modulosPorDefecto($this->role);
        return in_array($modulo, $permitidos, true);
    }

    public function contactos()
    {
        return $this->hasMany(ProveedorContacto::class);
    }

    /**
     * Enviar la notificación de restablecimiento de contraseña personalizada
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new \App\Notifications\ResetPasswordCustom($token));
    }
}
