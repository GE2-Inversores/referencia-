<?php

namespace App\Mail;

use App\Services\MailConfigService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CorreoPrueba extends Mailable
{
    use Queueable, SerializesModels;

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: MailConfigService::replyToAddresses(),
            subject: '🏥 Correo de prueba — Sistema de Referencia Santa Bárbara',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.correo-prueba');
    }
}
