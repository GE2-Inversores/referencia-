<?php

namespace App\Mail;

use App\Models\SolicitudEvolucion;
use App\Models\SolicitudReferencia;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NuevaEvolucionPaciente extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public SolicitudReferencia $solicitud, public SolicitudEvolucion $evolucion) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $institucion = $this->solicitud->clinica?->nombre ?? $this->solicitud->institucion_remitente ?? 'Entidad externa';

        return new Envelope(
            replyTo: [new Address(config('mail.referencia_interna'), config('mail.referencia_nombre'))],
            subject: 'Evolución registrada — '.$institucion.' · '.$this->solicitud->primer_nombre.' '.$this->solicitud->primer_apellido.' (ID'.str_pad((string) $this->solicitud->id, 6, '0', STR_PAD_LEFT).')',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.nueva-evolucion-paciente',
        );
    }
}
