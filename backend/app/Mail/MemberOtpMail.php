<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MemberOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public ?string $memberName = null,
        public string $purpose = 'login'
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->purpose === 'login'
            ? 'Your FlexCare Login Code: ' . $this->code
            : 'Your FlexCare Verification Code: ' . $this->code;

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.member.otp',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
