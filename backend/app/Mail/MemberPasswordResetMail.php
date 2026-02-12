<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MemberPasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public ?string $memberName = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'FlexCare Password Reset Code: ' . $this->code,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.member.password-reset',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
