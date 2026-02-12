<?php

use Illuminate\Support\Facades\Route;
use Modules\Medical\Http\Controllers\Provider\ProviderEligibilityController;
use Modules\Medical\Http\Controllers\Provider\ProviderPreAuthController;
use Modules\Medical\Http\Controllers\Provider\ProviderClaimController;
use Modules\Medical\Http\Controllers\Provider\ProviderAccountController;

/*
|--------------------------------------------------------------------------
| Provider API Routes
|--------------------------------------------------------------------------
|
| These routes are used by healthcare providers (hospitals, clinics, etc.)
| to submit claims, check eligibility, and manage pre-authorizations.
|
| Authentication: API Key + Secret in headers
| - X-API-Key: Provider's API key
| - X-API-Secret: Provider's API secret
|
| Rate Limiting: Per-minute, per-hour, and per-day limits per credential
|
*/

Route::prefix('v1/provider')
    ->middleware([
        'provider.auth',      // Authenticate provider API credentials
        'provider.rate-limit', // Apply rate limiting
        'provider.log',       // Log all API requests
    ])
    ->group(function () {

        // =====================================================================
        // ELIGIBILITY ENDPOINTS (< 500ms SLA)
        // Required Scope: eligibility
        // =====================================================================
        Route::prefix('eligibility')->group(function () {
            // Quick eligibility check with optional service codes
            Route::post('/check', [ProviderEligibilityController::class, 'check'])
                ->name('provider.eligibility.check');

            // Get member benefits summary (cached)
            Route::get('/member/{cardNumber}/benefits', [ProviderEligibilityController::class, 'benefits'])
                ->name('provider.eligibility.benefits');

            // Estimate copay for proposed services
            Route::post('/estimate-copay', [ProviderEligibilityController::class, 'estimateCopay'])
                ->name('provider.eligibility.estimate-copay');

            // Validate member card number
            Route::get('/validate-card/{cardNumber}', [ProviderEligibilityController::class, 'validateCard'])
                ->name('provider.eligibility.validate-card');
        });

        // =====================================================================
        // PRE-AUTHORIZATION ENDPOINTS
        // Required Scope: preauth
        // =====================================================================
        Route::prefix('preauth')->group(function () {
            // Submit pre-authorization request
            Route::post('/', [ProviderPreAuthController::class, 'store'])
                ->name('provider.preauth.store');

            // Get pre-authorization status
            Route::get('/{preauthNumber}', [ProviderPreAuthController::class, 'show'])
                ->name('provider.preauth.show');

            // Cancel pre-authorization
            Route::post('/{preauthNumber}/cancel', [ProviderPreAuthController::class, 'cancel'])
                ->name('provider.preauth.cancel');

            // Request extension of pre-authorization validity
            Route::post('/{preauthNumber}/extend', [ProviderPreAuthController::class, 'extend'])
                ->name('provider.preauth.extend');

            // Add line items to existing pre-authorization
            Route::post('/{preauthNumber}/lines', [ProviderPreAuthController::class, 'addLines'])
                ->name('provider.preauth.add-lines');

            // Upload supporting documents
            Route::post('/{preauthNumber}/documents', [ProviderPreAuthController::class, 'uploadDocument'])
                ->name('provider.preauth.upload-document');

            // List pre-authorizations for a member
            Route::get('/member/{cardNumber}', [ProviderPreAuthController::class, 'memberPreAuths'])
                ->name('provider.preauth.member');
        });

        // =====================================================================
        // CLAIMS ENDPOINTS
        // Required Scope: claims
        // =====================================================================
        Route::prefix('claims')->group(function () {
            // Submit new claim
            Route::post('/', [ProviderClaimController::class, 'store'])
                ->name('provider.claims.store');

            // Get claim status
            Route::get('/{claimNumber}', [ProviderClaimController::class, 'show'])
                ->name('provider.claims.show');

            // Add line items to existing claim (if still editable)
            Route::post('/{claimNumber}/lines', [ProviderClaimController::class, 'addLines'])
                ->name('provider.claims.add-lines');

            // Upload supporting documents
            Route::post('/{claimNumber}/documents', [ProviderClaimController::class, 'uploadDocument'])
                ->name('provider.claims.upload-document');

            // Get claim documents
            Route::get('/{claimNumber}/documents', [ProviderClaimController::class, 'documents'])
                ->name('provider.claims.documents');

            // Get claims for a member (history)
            Route::get('/member/{cardNumber}', [ProviderClaimController::class, 'memberHistory'])
                ->name('provider.claims.member-history');

            // Bulk claim submission
            Route::post('/bulk', [ProviderClaimController::class, 'bulkStore'])
                ->name('provider.claims.bulk-store');

            // Get bulk submission status
            Route::get('/bulk/{batchId}', [ProviderClaimController::class, 'bulkStatus'])
                ->name('provider.claims.bulk-status');
        });

        // =====================================================================
        // PROVIDER ACCOUNT/SELF-SERVICE ENDPOINTS
        // No specific scope required (uses credential's own data)
        // =====================================================================
        Route::prefix('account')->group(function () {
            // Get provider profile
            Route::get('/profile', [ProviderAccountController::class, 'profile'])
                ->name('provider.account.profile');

            // Get API usage statistics
            Route::get('/statistics', [ProviderAccountController::class, 'statistics'])
                ->name('provider.account.statistics');

            // Get current API credential info
            Route::get('/credential', [ProviderAccountController::class, 'credential'])
                ->name('provider.account.credential');

            // Test API connection (health check)
            Route::get('/ping', [ProviderAccountController::class, 'ping'])
                ->name('provider.account.ping');
        });

        // =====================================================================
        // LOOKUP ENDPOINTS
        // Required Scope: member_lookup (optional, for detailed member info)
        // =====================================================================
        Route::prefix('lookup')->group(function () {
            // Lookup benefit by code
            Route::get('/benefit/{benefitCode}', [ProviderEligibilityController::class, 'lookupBenefit'])
                ->name('provider.lookup.benefit');

            // Lookup ICD-10 code
            Route::get('/icd10/{code}', [ProviderEligibilityController::class, 'lookupIcd10'])
                ->name('provider.lookup.icd10');

            // Search ICD-10 codes by description
            Route::get('/icd10', [ProviderEligibilityController::class, 'searchIcd10'])
                ->name('provider.lookup.icd10-search');
        });
    });

/*
|--------------------------------------------------------------------------
| Webhook Endpoints (for provider systems to receive updates)
|--------------------------------------------------------------------------
*/
Route::prefix('v1/provider/webhooks')
    ->middleware(['provider.auth'])
    ->group(function () {
        // Register webhook URL
        Route::post('/register', [ProviderAccountController::class, 'registerWebhook'])
            ->name('provider.webhooks.register');

        // List registered webhooks
        Route::get('/', [ProviderAccountController::class, 'listWebhooks'])
            ->name('provider.webhooks.list');

        // Delete webhook
        Route::delete('/{webhookId}', [ProviderAccountController::class, 'deleteWebhook'])
            ->name('provider.webhooks.delete');

        // Test webhook (send test payload)
        Route::post('/{webhookId}/test', [ProviderAccountController::class, 'testWebhook'])
            ->name('provider.webhooks.test');
    });
