<?php

namespace Modules\Medical\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Modules\Medical\Models\Invoice;

class InvoicePdfService
{
    /**
     * Generate PDF for an invoice and return the PDF content as string.
     *
     * @param Invoice $invoice
     * @return string The PDF content
     */
    public function generate(Invoice $invoice): string
    {
        // Load relationships
        $invoice->load(['policy.plan', 'items', 'group']);

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
        ]);

        // Set paper size and orientation
        $pdf->setPaper('a4', 'portrait');

        // Return PDF content as string
        return $pdf->output();
    }

    /**
     * Generate PDF for an invoice and return the PDF instance for download/stream.
     *
     * @param Invoice $invoice
     * @return \Barryvdh\DomPDF\PDF
     */
    public function generateForDownload(Invoice $invoice): \Barryvdh\DomPDF\PDF
    {
        // Load relationships
        $invoice->load(['policy.plan', 'items', 'group']);

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
        ]);

        // Set paper size and orientation
        $pdf->setPaper('a4', 'portrait');

        return $pdf;
    }

    /**
     * Generate the filename for the invoice PDF.
     *
     * @param Invoice $invoice
     * @return string
     */
    public function getFilename(Invoice $invoice): string
    {
        return 'Invoice-' . $invoice->invoice_number . '.pdf';
    }

    /**
     * Save invoice PDF to storage.
     *
     * @param Invoice $invoice
     * @param string|null $disk
     * @return string The file path
     */
    public function saveToStorage(Invoice $invoice, ?string $disk = null): string
    {
        $disk = $disk ?? config('filesystems.default');
        $content = $this->generate($invoice);
        $filename = $this->getFilename($invoice);
        $path = 'invoices/' . date('Y/m') . '/' . $filename;

        \Storage::disk($disk)->put($path, $content);

        return $path;
    }
}
