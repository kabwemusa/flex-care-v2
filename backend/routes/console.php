<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Modules\Medical\Services\BillingService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// =========================================================================
// SCHEDULED TASKS
// =========================================================================

/**
 * Mark past-due invoices as overdue and update days_overdue counter.
 * Runs at midnight every day.
 */
Schedule::call(function () {
    $service = app(BillingService::class);
    $updated = $service->updateOverdueInvoices();
    \Illuminate\Support\Facades\Log::info("Overdue invoice sweep complete: {$updated} invoice(s) updated.");
})->dailyAt('00:05')->name('billing:update-overdue-invoices')->withoutOverlapping();
