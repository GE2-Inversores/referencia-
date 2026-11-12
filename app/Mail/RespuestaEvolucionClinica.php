<?php

namespace App\Mail;

use App\Models\SolicitudEvolucionRespuesta;
use App\Models\SolicitudReferencia;
use App\Services\MailConfigService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RespuestaEvolucionClinica extends Mailable
{
    use Queueable, SerializesModels;

    public string $enlace;

    public function __construct(public SolicitudReferencia $solicitud, public SolicitudEvolucionRespuesta $respuesta)
    {
        $this->enlace = rtrim((string) config('app.url'), '/').'/clinica/evolucion?id='.$solicitud->id;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: MailConfigService::replyToAddresses(),
            subject: 'Respuesta a la evolución — '.$this->solicitud->primer_nombre.' '.$this->solicitud->primer_apellido.' (ID'.str_pad((string) $this->solicitud->id, 6, '0', STR_PAD_LEFT).')',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.respuesta-evolucion-clinica');
    }
}
