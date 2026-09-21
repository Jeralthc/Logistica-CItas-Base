<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CredencialesGalponProveedor extends Mailable
{
    use Queueable, SerializesModels;

    public $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '📦 Credenciales de Acceso - Portal de Traslados Internos Suraki',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.credenciales_galpon_proveedor',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
