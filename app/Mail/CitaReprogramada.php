<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CitaReprogramada extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $cita;
    public $motivo;
    public $fechaAnterior;

    /**
     * Create a new message instance.
     */
    public function __construct(object $cita, $motivo, $fechaAnterior)
    {
        $this->cita = $cita;
        $this->motivo = $motivo;
        $this->fechaAnterior = $fechaAnterior;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Cita Reprogramada - OC: ' . $this->cita->numero_oc,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.cita_reprogramada',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
