<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class RecuperarContrasenaMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $url;

    public function __construct($user, string $url)
    {
        $this->user = $user;
        $this->url = $url;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Hipersuraki] Enlace para restablecer su contraseña',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.recuperar_contrasena',
            text: 'emails.recuperar_contrasena_plain',
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
