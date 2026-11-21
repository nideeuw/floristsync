<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** The 6-digit code that confirms the email address while registering. */
class RegistrationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $name, public string $code, public int $minutes = 10) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your '.config('app.name').' verification code: '.$this->code);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.registration-code');
    }
}
