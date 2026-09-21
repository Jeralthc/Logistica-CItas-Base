<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class NotificacionReactivacionOdc extends Mailable
{
    use Queueable, SerializesModels;

    public $info;

    public function __construct($info)
    {
        $this->info = $info;
    }

    public function envelope(): Envelope
    {
        $oc = $this->info->numero_oc ?? '';
        return new Envelope(
            subject: "[Hipersuraki] Actualización: Orden de Compra #{$oc} disponible para agendar cita",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.odc_notificacion_disponible',
            text: 'emails.odc_notificacion_disponible_plain',
        );
    }

    public function headers(): Headers
    {
        return new Headers(
            text: [
                'X-Auto-Response-Suppress' => 'All',
                'Auto-Submitted' => 'auto-generated',
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
