<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Medical\Models\Invoice;
use Modules\Medical\Services\InvoicePdfService;

class InvoiceEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public ?string $customMessage = null
    ) {}

    public function envelope(): Envelope
    {
        $subject = 'Invoice ' . $this->invoice->invoice_number;

        if ($this->invoice->is_overdue) {
            $subject = '[OVERDUE] ' . $subject . ' - Payment Required';
        }

        return new Envelope(
            subject: $subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoices.sent',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $pdfService = app(InvoicePdfService::class);
        $pdfContent = $pdfService->generate($this->invoice);
        $filename = $pdfService->getFilename($this->invoice);

        return [
            Attachment::fromData(fn () => $pdfContent, $filename)
                ->withMime('application/pdf'),
        ];
    }
}
