<?php

namespace App\Notifications;

use App\Mail\RecuperarContrasenaMail;
use App\Models\EmailLog;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ResetPasswordCustom extends Notification
{
    use Queueable;

    public $token;

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $appUrl = config('app.url');
        if (empty($appUrl) || str_contains($appUrl, '.test') || str_contains($appUrl, 'localhost') || str_contains($appUrl, '127.0.0.1') || str_contains($appUrl, 'logistica.suraki.net')) {
            $appUrl = 'https://citsur.suraki.net';
        }
        $appUrl = rtrim($appUrl, '/');
        $email = $notifiable->getEmailForPasswordReset();
        $url = $appUrl . '/reset-password/' . $this->token . '?email=' . urlencode($email);

        try {
            EmailLog::create([
                'numero_oc' => 'N/A',
                'proveedor' => $notifiable->name ?? 'Usuario',
                'email_destino' => $email,
                'vendedor_nombre' => $notifiable->username ?? 'Usuario',
                'tipo_evento' => 'recuperacion_contrasena',
                'estatus' => 'exitoso',
            ]);
        } catch (\Throwable $e) {}

        return (new RecuperarContrasenaMail($notifiable, $url))
            ->to($email);
    }
}
