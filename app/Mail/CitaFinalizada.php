<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CitaFinalizada extends Mailable
{
    use Queueable, SerializesModels;

    public $cita;

    public function __construct($cita)
    {
        $this->cita = $cita;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '¡Orden de Compra Recibida y Completada! - Empresa Base',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.cita_finalizada',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
