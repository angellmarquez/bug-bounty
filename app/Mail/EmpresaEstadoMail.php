<?php

namespace App\Mail;

use App\Models\Empresa;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmpresaEstadoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Empresa $empresa, public string $decision) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Actualizacion de solicitud empresarial');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.empresa-estado');
    }
}
