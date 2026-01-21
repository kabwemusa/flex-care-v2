<?php

namespace Modules\Medical\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Modules\Medical\Models\Application;

class QuotePdfService
{
    /**
     * Generate PDF for a quote and return the PDF content as string.
     *
     * @param Application $application
     * @return string The PDF content
     */
    public function generate(Application $application): string
    {
        // Load relationships (including addon details for name)
        $application->load(['scheme', 'plan', 'members', 'addons.addon']);

        $pdf = Pdf::loadView('pdf.quote', [
            'application' => $application,
        ]);

        // Set paper size and orientation
        $pdf->setPaper('a4', 'portrait');

        // Return PDF content as string
        return $pdf->output();
    }

    /**
     * Generate PDF for a quote and return the PDF instance for download/stream.
     *
     * @param Application $application
     * @return \Barryvdh\DomPDF\PDF
     */
    public function generateForDownload(Application $application): \Barryvdh\DomPDF\PDF
    {
        // Load relationships (including addon details for name)
        $application->load(['scheme', 'plan', 'members', 'addons.addon']);

        $pdf = Pdf::loadView('pdf.quote', [
            'application' => $application,
        ]);

        // Set paper size and orientation
        $pdf->setPaper('a4', 'portrait');

        return $pdf;
    }

    /**
     * Generate the filename for the quote PDF.
     *
     * @param Application $application
     * @return string
     */
    public function getFilename(Application $application): string
    {
        return 'Quote-' . $application->application_number . '.pdf';
    }
}
