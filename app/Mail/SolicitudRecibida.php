<?php

namespace App\Mail;

use App\Models\SolicitudReferencia;
use App\Services\MailConfigService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SolicitudRecibida extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SolicitudReferencia $solicitud) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: MailConfigService::replyToAddresses(),
            subject: 'Solicitud de referencia recibida — ID'.str_pad((string) $this->solicitud->id, 6, '0', STR_PAD_LEFT).' · '.$this->solicitud->primer_nombre.' '.$this->solicitud->primer_apellido,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.solicitud-recibida');
    }
}
