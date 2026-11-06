<?php

namespace App\Mail;

use App\Models\Clinica;
use App\Services\MailConfigService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClinicaRegistroConfirmacion extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Clinica $clinica) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: MailConfigService::replyToAddresses(),
            subject: 'Solicitud de registro recibida — Sistema de Referencia Santa Bárbara',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.clinica-registro-confirmacion',
        );
    }
}
