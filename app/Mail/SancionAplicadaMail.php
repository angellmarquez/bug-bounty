<?php

namespace App\Mail;

use App\Models\Sancion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SancionAplicadaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Sancion $sancion) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Se ha aplicado una sancion a tu cuenta');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.sancion-aplicada');
    }
}
