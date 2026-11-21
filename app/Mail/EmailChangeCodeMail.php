<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** The 6-digit code that confirms a new email address from the profile page. */
class EmailChangeCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $name, public string $code, public int $minutes = 10) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Confirm your new email: '.$this->code);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.email-change-code');
    }
}
