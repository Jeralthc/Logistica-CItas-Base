<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OdcHabilitada extends Mailable
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
            subject: '¡Orden de Compra Habilitada para Cita! - Empresa Base',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.odc_habilitada',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
