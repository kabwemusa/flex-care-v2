<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Medical\Models\Application as ModelsApplication;
use Modules\Medical\Services\QuotePdfService;

class QuoteEmail extends Mailable
{
    use Queueable, SerializesModels;

    private ?string $pdfContent = null;

    public function __construct(
        public ModelsApplication $application,
        public ?string $customMessage = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Medical Insurance Quote: ' . $this->application->application_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.quotes.generated',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $pdfService = app(QuotePdfService::class);
        $pdfContent = $pdfService->generate($this->application);
        $filename = $pdfService->getFilename($this->application);

        return [
            Attachment::fromData(fn () => $pdfContent, $filename)
                ->withMime('application/pdf'),
        ];
    }
}