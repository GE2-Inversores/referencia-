<?php

namespace App\Mail;

use App\Models\Clinica;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClinicaRechazo extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Clinica $clinica, public string $motivo) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address(config('mail.referencia_interna'), config('mail.referencia_nombre'))],
            subject: '❌ Solicitud rechazada — Sistema de Referencia Santa Bárbara',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.clinica-rechazo',
        );
    }
}
