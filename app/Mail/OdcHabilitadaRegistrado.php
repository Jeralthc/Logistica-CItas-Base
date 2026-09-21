<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class OdcHabilitadaRegistrado extends Mailable
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
            subject: "[Hipersuraki] Notificación: Nueva Orden de Compra #{$oc} disponible para agendar cita",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.odc_habilitada_registrado',
            text: 'emails.odc_habilitada_registrado_plain',
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
