<?php

namespace App\Mail;

use App\Models\EmpresaInvitacion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EmpresaInvitacionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public EmpresaInvitacion $invitacion, public string $url) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Invitacion a una empresa en '.config('app.name'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.empresa-invitacion');
    }
}
