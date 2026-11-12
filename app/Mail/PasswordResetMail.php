<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Port of sendPasswordResetEmail() from includes/Mailer.php.
 *
 * Subject, plain-text body and HTML template are copied verbatim so the
 * message a user receives is identical to the legacy one.
 */
class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $resetUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reset your ODCI Document password',
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'mail.password-reset-html',
            text: 'mail.password-reset-text',
            with: ['resetUrl' => $this->resetUrl],
        );
    }
}
