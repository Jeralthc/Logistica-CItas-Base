<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
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
        return new Envelope(
            subject: '¡Nueva Orden de Compra Habilitada! - Empresa Base',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.odc_habilitada_registrado',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
