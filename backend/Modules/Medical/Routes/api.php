<?php

use Illuminate\Support\Facades\Route;
use Modules\Medical\Http\Controllers\SchemeController;
use Modules\Medical\Http\Controllers\PlanController;
use Modules\Medical\Http\Controllers\BenefitController;
use Modules\Medical\Http\Controllers\RateCardController;
use Modules\Medical\Http\Controllers\AddonController;
use Modules\Medical\Http\Controllers\DiscountController;
use Modules\Medical\Http\Controllers\GroupController;
use Modules\Medical\Http\Controllers\QuoteController;
use Modules\Medical\Http\Controllers\LoadingController;
use Modules\Medical\Http\Controllers\MemberController;
use Modules\Medical\Http\Controllers\PolicyController;
use Modules\Medical\Http\Controllers\ApplicationController;
use Modules\Medical\Http\Controllers\PlanExclusionController;
use Modules\Medical\Http\Controllers\EndorsementController;
use Modules\Medical\Http\Controllers\ClaimController;
use Modules\Medical\Http\Controllers\BillingController;
use Modules\Medical\Constants\MedicalConstants;

/*
|--------------------------------------------------------------------------
| Medical Module API Routes
|--------------------------------------------------------------------------
|
| All routes are protected by:
| - auth:sanctum - Requires valid authentication token
| - module:medical - Requires user to have Medical module access
|
*/

Route::prefix('v1/medical')
    ->middleware(['auth:sanctum', 'session.check', 'module:medical'])
    ->group(function () {

    // =========================================================================
    // SCHEMES
    // =========================================================================
    Route::get('schemes/dropdown', [SchemeController::class, 'dropdown']);
    Route::post('schemes/{id}/activate', [SchemeController::class, 'activate']);
    Route::apiResource('schemes', SchemeController::class);

    // =========================================================================
    // PLANS
    // =========================================================================
    Route::get('plans/dropdown', [PlanController::class, 'dropdown']);
    Route::post('plans/compare', [PlanController::class, 'compare']);
    Route::post('plans/{plan}/clone', [PlanController::class, 'clone']);
    Route::post('plans/{plan}/activate', [PlanController::class, 'activate']);
    Route::get('plans/{plan}/export-pdf', [PlanController::class, 'exportPdf']);
    Route::get('schemes/{scheme}/plans', [PlanController::class, 'byScheme']);
    Route::apiResource('plans', PlanController::class);

    // =========================================================================
    // BENEFITS
    // =========================================================================
    
    // Benefit Categories
    Route::get('benefit-categories', [BenefitController::class, 'categories']);
    Route::post('benefit-categories', [BenefitController::class, 'storeCategory']);

    // Benefit Catalog
    Route::get('benefits/tree', [BenefitController::class, 'tree']);
    Route::apiResource('benefits', BenefitController::class);

    // Plan Benefits Configuration
    Route::get('plans/{plan}/benefits', [BenefitController::class, 'planBenefits']);
    Route::post('plans/{plan}/benefits', [BenefitController::class, 'addToPlan']);
    Route::post('plans/{plan}/benefits/bulk', [BenefitController::class, 'bulkAddToPlan']);
    Route::get('plans/{plan}/benefit-schedule', [BenefitController::class, 'schedule']);
    Route::put('plan-benefits/{planBenefit}', [BenefitController::class, 'updatePlanBenefit']);
    Route::delete('plan-benefits/{planBenefit}', [BenefitController::class, 'removeFromPlan']);

    // Eligibility Check
    Route::post('benefits/check-eligibility', [BenefitController::class, 'checkEligibility']);

    // =========================================================================
    // RATE CARDS
    // =========================================================================
    Route::get('plans/{plan}/rate-cards', [RateCardController::class, 'byPlan']);
    Route::post('rate-cards/{rateCard}/activate', [RateCardController::class, 'activate']);
    Route::post('rate-cards/{rateCard}/clone', [RateCardController::class, 'clone']);
    Route::post('rate-cards/{rateCard}/calculate', [RateCardController::class, 'calculate']);
    
    // Rate Card Entries
    Route::post('rate-cards/{rateCard}/entries', [RateCardController::class, 'addEntry']);
    Route::post('rate-cards/{rateCard}/entries/bulk', [RateCardController::class, 'bulkImportEntries']);
    Route::put('rate-card-entries/{entry}', [RateCardController::class, 'updateEntry']);
    Route::delete('rate-card-entries/{entry}', [RateCardController::class, 'deleteEntry']);

    // Rate Card Tiers
    Route::post('rate-cards/{rateCard}/tiers', [RateCardController::class, 'addTier']);
    Route::put('rate-card-tiers/{tier}', [RateCardController::class, 'updateTier']);
    Route::delete('rate-card-tiers/{tier}', [RateCardController::class, 'deleteTier']);

    Route::apiResource('rate-cards', RateCardController::class);

    // =========================================================================
    // ADDONS
    // =========================================================================
    Route::get('addons/dropdown', [AddonController::class, 'dropdown']);
    Route::post('addons/{addon}/activate', [AddonController::class, 'activate']);

    // Addon Benefits
    Route::post('addons/{addon}/benefits', [AddonController::class, 'addBenefit']);
    Route::put('addon-benefits/{addonBenefit}', [AddonController::class, 'updateBenefit']);
    Route::delete('addon-benefits/{addonBenefit}', [AddonController::class, 'removeBenefit']);

    // Addon Rates
    Route::post('addons/{addon}/rates', [AddonController::class, 'addRate']);
    Route::post('addon-rates/{addonRate}/activate', [AddonController::class, 'activateRate']);

    // Plan Addons Configuration
    Route::get('plans/{plan}/addons', [AddonController::class, 'planAddons']);
    Route::post('plans/{plan}/addons', [AddonController::class, 'configurePlanAddon']);
    Route::get('plans/{plan}/available-addons', [AddonController::class, 'availableAddons']);
    Route::put('plan-addons/{planAddon}', [AddonController::class, 'updatePlanAddon']);
    Route::delete('plan-addons/{planAddon}', [AddonController::class, 'removePlanAddon']);

    Route::apiResource('addons', AddonController::class);

    // =========================================================================
    // PLAN EXCLUSIONS
    // =========================================================================
    Route::get('plans/{plan}/exclusions', [PlanExclusionController::class, 'index']);
    Route::post('plans/{plan}/exclusions', [PlanExclusionController::class, 'store']);
    Route::post('plans/{plan}/exclusions/bulk-delete', [PlanExclusionController::class, 'bulkDelete']);
    Route::get('plan-exclusions/{planExclusion}', [PlanExclusionController::class, 'show']);
    Route::put('plan-exclusions/{planExclusion}', [PlanExclusionController::class, 'update']);
    Route::delete('plan-exclusions/{planExclusion}', [PlanExclusionController::class, 'destroy']);
    Route::post('plan-exclusions/{planExclusion}/activate', [PlanExclusionController::class, 'activate']);

    // =========================================================================
    // DISCOUNTS & PROMO CODES
    // =========================================================================
    Route::get('plans/{plan}/discounts', [DiscountController::class, 'forPlan']);
    Route::post('discounts/simulate', [DiscountController::class, 'simulate']);
    Route::apiResource('discount-rules', DiscountController::class);

    // Promo Codes
    Route::post('promo-codes/validate', [DiscountController::class, 'validatePromoCode']);
    Route::post('promo-codes/apply', [DiscountController::class, 'applyPromoCode']);
    Route::get('promo-codes', [DiscountController::class, 'promoCodes']);
    Route::post('promo-codes', [DiscountController::class, 'storePromoCode']);
    Route::get('promo-codes/{promoCode}', [DiscountController::class, 'showPromoCode']);
    Route::put('promo-codes/{promoCode}', [DiscountController::class, 'updatePromoCode']);
    Route::delete('promo-codes/{promoCode}', [DiscountController::class, 'destroyPromoCode']);

    // =========================================================================
    // QUOTES (Quick quotes without creating application)
    // =========================================================================
    Route::post('quotes', [QuoteController::class, 'generate']);
    Route::post('quotes/compare', [QuoteController::class, 'compare']);
    Route::post('group-quotes', [QuoteController::class, 'groupQuote']);
    Route::get('plans/{plan}/quick-quote', [QuoteController::class, 'quickQuote']);
    Route::post('quotes/convert-frequency', [QuoteController::class, 'convertFrequency']);
    
    // Quick quote via ApplicationController (alternative endpoint)
    Route::post('quote', [ApplicationController::class, 'generateQuote']);

    // =========================================================================
    // LOADING RULES
    // =========================================================================
    Route::get('loading-rules/search', [LoadingController::class, 'search']);
    Route::get('loading-rules/categories', [LoadingController::class, 'categories']);
    Route::get('loading-rules/by-category/{category}', [LoadingController::class, 'byCategory']);
    Route::get('loading-rules/options/{identifier}', [LoadingController::class, 'options']);
    Route::post('loadings/calculate', [LoadingController::class, 'calculate']);
    Route::apiResource('loading-rules', LoadingController::class);

    // =========================================================================
    // CORPORATE GROUPS
    // =========================================================================
    Route::prefix('groups')->group(function () {
        Route::get('/', [GroupController::class, 'index']);
        Route::get('/dropdown', [GroupController::class, 'dropdown']);
        Route::post('/', [GroupController::class, 'store']);
        Route::get('/{id}', [GroupController::class, 'show']);
        Route::put('/{id}', [GroupController::class, 'update']);
        Route::delete('/{id}', [GroupController::class, 'destroy']);
        
        // Status actions
        Route::post('/{id}/activate', [GroupController::class, 'activate']);
        Route::post('/{id}/suspend', [GroupController::class, 'suspend']);
        
        // Contacts
        Route::get('/{groupId}/contacts', [GroupController::class, 'contacts']);
        Route::post('/{groupId}/contacts', [GroupController::class, 'addContact']);
        Route::put('/{groupId}/contacts/{contactId}', [GroupController::class, 'updateContact']);
        Route::delete('/{groupId}/contacts/{contactId}', [GroupController::class, 'removeContact']);
        Route::post('/{groupId}/contacts/{contactId}/primary', [GroupController::class, 'setPrimaryContact']);

        // Plan Distribution
        Route::get('/{id}/plan-distribution', [GroupController::class, 'planDistribution']);
    });

    // =========================================================================
    // APPLICATIONS (Application-First Workflow)
    // =========================================================================
    Route::prefix('applications')->group(function () {
        // CRUD
        Route::get('/', [ApplicationController::class, 'index']);
        Route::post('/', [ApplicationController::class, 'store']);
        Route::get('/stats', [ApplicationController::class, 'stats']);

        // Corporate Census Import
        Route::post('/import-census', [ApplicationController::class, 'importCensus']);
        Route::post('/create-from-census', [ApplicationController::class, 'createFromCensus']);
        Route::post('/create-multi-plan-from-census', [ApplicationController::class, 'createMultiPlanFromCensus']);

        Route::get('/{id}', [ApplicationController::class, 'show']);
        Route::patch('/{id}', [ApplicationController::class, 'update']);
        Route::delete('/{id}', [ApplicationController::class, 'destroy']);

        // Workflow Actions
        Route::post('/{id}/calculate-premium', [ApplicationController::class, 'calculatePremium']);
        Route::post('/{id}/quote', [ApplicationController::class, 'markAsQuoted']);
        Route::get('/{id}/quote/download', [ApplicationController::class, 'downloadQuote']);
        Route::post('/{id}/quote/email', [ApplicationController::class, 'emailQuote']);
        Route::post('/{id}/submit', [ApplicationController::class, 'submit']);
        Route::post('/{id}/start-underwriting', [ApplicationController::class, 'startUnderwriting']);
        Route::post('/{id}/regenerate-quote', [ApplicationController::class, 'regenerateQuote']);
        Route::post('/{id}/approve', [ApplicationController::class, 'approve']);
        Route::post('/{id}/decline', [ApplicationController::class, 'decline']);
        Route::post('/{id}/refer', [ApplicationController::class, 'refer']);
        Route::post('/{id}/accept', [ApplicationController::class, 'accept']);
        Route::post('/{id}/convert', [ApplicationController::class, 'convert']);
        Route::post('/{id}/cancel', [ApplicationController::class, 'cancel']);

        // Application Members
        Route::get('/{id}/members', [ApplicationController::class, 'members']);
        Route::post('/{id}/members', [ApplicationController::class, 'addMember']);
        Route::put('/{appId}/members/{memberId}', [ApplicationController::class, 'updateMember']);
        Route::delete('/{appId}/members/{memberId}', [ApplicationController::class, 'removeMember']);

        // Member Underwriting
        Route::post('/{appId}/members/{memberId}/underwrite', [ApplicationController::class, 'underwriteMember']);
        Route::post('/{appId}/members/{memberId}/loadings', [ApplicationController::class, 'addMemberLoading']);
        Route::post('/{appId}/members/{memberId}/exclusions', [ApplicationController::class, 'addMemberExclusion']);

        // Application Addons
        Route::post('/{id}/addons', [ApplicationController::class, 'addAddon']);
        Route::delete('/{id}/addons/{addonId}', [ApplicationController::class, 'removeAddon']);

        // Promo Code
        Route::post('/{id}/promo-code', [ApplicationController::class, 'applyPromoCode']);

        // Documents
        Route::get('/{id}/documents', [ApplicationController::class, 'documents']);
        Route::post('/{id}/documents', [ApplicationController::class, 'uploadDocument']);
        Route::get('/{id}/documents/{documentId}/download', [ApplicationController::class, 'downloadDocument']);
    });

    // =========================================================================
    // POLICIES
    // =========================================================================
    Route::prefix('policies')->group(function () {
        Route::get('/', [PolicyController::class, 'index']);
        Route::get('/stats', [PolicyController::class, 'stats']);
        Route::get('/for-renewal', [PolicyController::class, 'forRenewal']);
        // NOTE: POST / (store) removed - policies created via application conversion
        Route::get('/{id}', [PolicyController::class, 'show']);
        Route::put('/{id}', [PolicyController::class, 'update']);
        Route::delete('/{id}', [PolicyController::class, 'destroy']);
        
        // Create Renewal Application from Policy
        Route::post('/{id}/renewal-application', [ApplicationController::class, 'createRenewalApplication']);
        
        // Status actions
        Route::post('/{id}/activate', [PolicyController::class, 'activate']);
        Route::post('/{id}/suspend', [PolicyController::class, 'suspend']);
        Route::post('/{id}/cancel', [PolicyController::class, 'cancel']);
        Route::post('/{id}/reinstate', [PolicyController::class, 'reinstate']);
        
        // Underwriting (legacy - for policies not created via application workflow)
        Route::post('/{id}/approve', [PolicyController::class, 'approve']);
        Route::post('/{id}/decline', [PolicyController::class, 'decline']);
        Route::post('/{id}/refer', [PolicyController::class, 'refer']);
        
        // Renewal (legacy - prefer using /renewal-application)
        Route::post('/{id}/renew', [PolicyController::class, 'renew']);
        
        // Premium
        Route::post('/{id}/calculate-premium', [PolicyController::class, 'calculatePremium']);

        // Members (mid-term additions/removals)
        Route::post('/{id}/members', [PolicyController::class, 'addMember']);
        Route::delete('/{id}/members/{memberId}', [PolicyController::class, 'removeMember']);

        // Addons (mid-term modifications)
        Route::post('/{id}/addons', [PolicyController::class, 'addAddon']);
        Route::delete('/{id}/addons/{addonId}', [PolicyController::class, 'removeAddon']);
        
        // Documents
        Route::get('/{id}/documents', [PolicyController::class, 'documents']);
        Route::post('/{id}/documents', [PolicyController::class, 'uploadDocument']);
        
        // Issue cards for all members
        Route::post('/{policyId}/issue-cards', [MemberController::class, 'issueCardsForPolicy']);

        // Endorsements for a specific policy
        Route::get('/{policyId}/endorsements', [EndorsementController::class, 'forPolicy']);
    });

    // =========================================================================
    // ENDORSEMENTS
    // =========================================================================
    Route::prefix('endorsements')->group(function () {
        Route::get('/', [EndorsementController::class, 'index']);
        Route::get('/stats', [EndorsementController::class, 'stats']);
        Route::get('/types', [EndorsementController::class, 'types']);
        Route::get('/statuses', [EndorsementController::class, 'statuses']);
        Route::post('/', [EndorsementController::class, 'store']);
        Route::get('/{id}', [EndorsementController::class, 'show']);

        // Workflow actions
        Route::post('/{id}/approve', [EndorsementController::class, 'approve']);
        Route::post('/{id}/reject', [EndorsementController::class, 'reject']);
        Route::post('/{id}/process', [EndorsementController::class, 'process']);
        Route::post('/{id}/cancel', [EndorsementController::class, 'cancel']);
    });

    // =========================================================================
    // CLAIMS
    // =========================================================================
    Route::prefix('claims')->group(function () {
        Route::get('/', [ClaimController::class, 'index']);
        Route::get('/stats', [ClaimController::class, 'stats']);
        Route::get('/types', [ClaimController::class, 'types']);
        Route::get('/statuses', [ClaimController::class, 'statuses']);
        Route::get('/rejection-reasons', [ClaimController::class, 'rejectionReasons']);
        Route::post('/check-benefit-eligibility', [ClaimController::class, 'checkBenefitEligibility']);
        Route::post('/', [ClaimController::class, 'store']);
        Route::get('/{id}', [ClaimController::class, 'show']);
        Route::put('/{id}', [ClaimController::class, 'update']);

        // Workflow actions
        Route::post('/{id}/start-review', [ClaimController::class, 'startReview']);
        Route::post('/{id}/request-documents', [ClaimController::class, 'requestDocuments']);
        Route::post('/{id}/adjudicate', [ClaimController::class, 'adjudicate']);
        Route::post('/{id}/approve', [ClaimController::class, 'approve']);
        Route::post('/{id}/reject', [ClaimController::class, 'reject']);
        Route::post('/{id}/pay', [ClaimController::class, 'pay']);
        Route::post('/{id}/close', [ClaimController::class, 'close']);
        Route::post('/{id}/assign', [ClaimController::class, 'assign']);

        // Claim lines
        Route::post('/{id}/lines', [ClaimController::class, 'addLine']);

        // Documents
        Route::post('/{id}/documents', [ClaimController::class, 'uploadDocument']);

        // Notes
        Route::post('/{id}/notes', [ClaimController::class, 'addNote']);
    });

    // Claims for a specific policy
    Route::get('policies/{policyId}/claims', [ClaimController::class, 'forPolicy']);

    // Claims for a specific member
    Route::get('members/{memberId}/claims', [ClaimController::class, 'forMember']);

    // =========================================================================
    // BILLING (Invoices & Payments)
    // =========================================================================
    Route::prefix('billing')->group(function () {
        // Statistics & Dashboard
        Route::get('/stats', [BillingController::class, 'stats']);
        Route::get('/action-required', [BillingController::class, 'actionRequired']);
        Route::get('/unallocated-payments', [BillingController::class, 'unallocatedPayments']);

        // Reference Data
        Route::get('/invoice-types', [BillingController::class, 'invoiceTypes']);
        Route::get('/invoice-statuses', [BillingController::class, 'invoiceStatuses']);
        Route::get('/payment-methods', [BillingController::class, 'paymentMethods']);
        Route::get('/payment-statuses', [BillingController::class, 'paymentStatuses']);

        // Invoices
        Route::get('/invoices', [BillingController::class, 'invoices']);
        Route::get('/invoices/{id}', [BillingController::class, 'showInvoice']);
        Route::post('/invoices/{id}/send', [BillingController::class, 'sendInvoice']);
        Route::post('/invoices/{id}/cancel', [BillingController::class, 'cancelInvoice']);
        Route::post('/invoices/{id}/write-off', [BillingController::class, 'writeOffInvoice']);
        Route::post('/invoices/{invoiceId}/payments', [BillingController::class, 'recordPaymentForInvoice']);

        // Payments
        Route::get('/payments', [BillingController::class, 'payments']);
        Route::get('/payments/{id}', [BillingController::class, 'showPayment']);
        Route::post('/payments', [BillingController::class, 'recordPayment']);
        Route::post('/payments/{paymentId}/allocate', [BillingController::class, 'allocatePayment']);
        Route::post('/payments/{paymentId}/auto-allocate', [BillingController::class, 'autoAllocatePayment']);
        Route::post('/payments/{id}/confirm', [BillingController::class, 'confirmPayment']);
        Route::post('/payments/{id}/bounce', [BillingController::class, 'bouncePayment']);
        Route::post('/payments/{id}/reverse', [BillingController::class, 'reversePayment']);
        Route::post('/payments/{id}/reconcile', [BillingController::class, 'reconcilePayment']);

        // Allocations
        Route::delete('/allocations/{allocationId}', [BillingController::class, 'reverseAllocation']);
    });

    // Policy Billing
    Route::get('policies/{policyId}/invoices', [BillingController::class, 'invoicesForPolicy']);
    Route::get('policies/{policyId}/payments', [BillingController::class, 'paymentsForPolicy']);
    Route::get('policies/{policyId}/billing-summary', [BillingController::class, 'policyBillingSummary']);
    Route::post('policies/{policyId}/generate-invoice', [BillingController::class, 'generateInvoice']);
    Route::post('policies/{policyId}/generate-recurring-invoice', [BillingController::class, 'generateRecurringInvoice']);

    // Group Billing
    Route::get('groups/{groupId}/invoices', [BillingController::class, 'invoicesForGroup']);

    // Endorsement Invoice Generation
    Route::post('endorsements/{endorsementId}/generate-invoice', [BillingController::class, 'generateEndorsementInvoice']);

    // =========================================================================
    // MEMBERS
    // =========================================================================
    Route::prefix('members')->group(function () {
        Route::get('/', [MemberController::class, 'index']);
        Route::get('/stats', [MemberController::class, 'stats']);
        // NOTE: Member creation for NEW policies happens via applications
        // This endpoint is for mid-term additions to existing policies
        Route::post('/', [MemberController::class, 'store']);
        Route::get('/{id}', [MemberController::class, 'show']);
        Route::put('/{id}', [MemberController::class, 'update']);
        Route::delete('/{id}', [MemberController::class, 'destroy']);
        
        // Status actions
        Route::post('/{id}/activate', [MemberController::class, 'activate']);
        Route::post('/{id}/suspend', [MemberController::class, 'suspend']);
        Route::post('/{id}/terminate', [MemberController::class, 'terminate']);
        Route::post('/{id}/deceased', [MemberController::class, 'markDeceased']);
        
        // Card management
        Route::post('/{id}/issue-card', [MemberController::class, 'issueCard']);
        Route::post('/{id}/activate-card', [MemberController::class, 'activateCard']);
        Route::post('/{id}/block-card', [MemberController::class, 'blockCard']);

        // Plan Management
        Route::post('/{id}/change-plan', [MemberController::class, 'changePlan']);
        
        // Eligibility
        Route::get('/{id}/eligibility', [MemberController::class, 'checkEligibility']);
        
        // Benefit Balances
        Route::get('/{id}/benefits', [MemberController::class, 'benefitBalances']);
        Route::get('/{id}/benefits/{benefitId}/history', [MemberController::class, 'benefitHistory']);
        
        // Dependents
        Route::get('/{id}/dependents', [MemberController::class, 'dependents']);
        Route::post('/{id}/dependents', [MemberController::class, 'addDependent']);
        
        // Loadings
        Route::get('/{id}/loadings', [MemberController::class, 'loadings']);
        Route::post('/{id}/loadings', [MemberController::class, 'addLoading']);
        Route::delete('/{memberId}/loadings/{loadingId}', [MemberController::class, 'removeLoading']);
        
        // Exclusions
        Route::get('/{id}/exclusions', [MemberController::class, 'exclusions']);
        Route::post('/{id}/exclusions', [MemberController::class, 'addExclusion']);
        Route::delete('/{memberId}/exclusions/{exclusionId}', [MemberController::class, 'removeExclusion']);
        
        // Documents
        Route::get('/{id}/documents', [MemberController::class, 'documents']);
        Route::post('/{id}/documents', [MemberController::class, 'uploadDocument']);
        Route::post('/{memberId}/documents/{documentId}/verify', [MemberController::class, 'verifyDocument']);
    });

    // =========================================================================
    // LOOKUPS (Constants for dropdowns)
    // =========================================================================
    Route::prefix('lookups')->group(function () {
        Route::get('/policy-types', fn() => response()->json([
            'data' => MedicalConstants::POLICY_TYPES
        ]));
        Route::get('/application-types', fn() => response()->json([
            'data' => MedicalConstants::APPLICATION_TYPES
        ]));
        Route::get('/application-statuses', fn() => response()->json([
            'data' => MedicalConstants::APPLICATION_STATUSES
        ]));
        Route::get('/policy-statuses', fn() => response()->json([
            'data' => MedicalConstants::POLICY_STATUSES
        ]));
        Route::get('/member-types', fn() => response()->json([
            'data' => MedicalConstants::MEMBER_TYPES
        ]));
        Route::get('/member-statuses', fn() => response()->json([
            'data' => MedicalConstants::MEMBER_STATUSES
        ]));
        Route::get('/relationships', fn() => response()->json([
            'data' => MedicalConstants::RELATIONSHIPS
        ]));
        Route::get('/billing-frequencies', fn() => response()->json([
            'data' => MedicalConstants::BILLING_FREQUENCIES
        ]));
        Route::get('/underwriting-statuses', fn() => response()->json([
            'data' => MedicalConstants::UW_STATUSES
        ]));
        Route::get('/market-segments', fn() => response()->json([
            'data' => MedicalConstants::MARKET_SEGMENTS
        ]));
        Route::get('/company-sizes', fn() => response()->json([
            'data' => MedicalConstants::COMPANY_SIZES
        ]));
        Route::get('/contact-types', fn() => response()->json([
            'data' => MedicalConstants::CONTACT_TYPES
        ]));
        Route::get('/group-statuses', fn() => response()->json([
            'data' => MedicalConstants::GROUP_STATUSES
        ]));
        Route::get('/card-statuses', fn() => response()->json([
            'data' => MedicalConstants::CARD_STATUSES
        ]));
        // Route::get('/document-types', fn() => response()->json([
        //     'data' => MedicalConstants::DOCUMENT_TYPES
        // ]));
        Route::get('/application-sources', fn() => response()->json([
            'data' => MedicalConstants::APPLICATION_SOURCES
        ]));
        Route::get('/benefit-types', fn() => response()->json([
            'data' => MedicalConstants::BENEFIT_TYPES
        ]));
        Route::get('/limit-types', fn() => response()->json([
            'data' => MedicalConstants::LIMIT_TYPES
        ]));
        Route::get('/copay-types', fn() => response()->json([
            'data' => MedicalConstants::COPAY_TYPES
        ]));
        Route::get('/exclusion-types', fn() => response()->json([
            'data' => MedicalConstants::EXCLUSION_TYPES
        ]));
        Route::get('/endorsement-types', fn() => response()->json([
            'data' => MedicalConstants::ENDORSEMENT_TYPES
        ]));
        Route::get('/endorsement-statuses', fn() => response()->json([
            'data' => MedicalConstants::ENDORSEMENT_STATUSES
        ]));
        Route::get('/claim-types', fn() => response()->json([
            'data' => MedicalConstants::CLAIM_TYPES
        ]));
        Route::get('/claim-statuses', fn() => response()->json([
            'data' => MedicalConstants::CLAIM_STATUSES
        ]));
        Route::get('/claim-rejection-reasons', fn() => response()->json([
            'data' => MedicalConstants::CLAIM_REJECTION_REASONS
        ]));
        Route::get('/submission-types', fn() => response()->json([
            'data' => MedicalConstants::SUBMISSION_TYPES
        ]));
        Route::get('/provider-types', fn() => response()->json([
            'data' => MedicalConstants::PROVIDER_TYPES
        ]));
        Route::get('/invoice-types', fn() => response()->json([
            'data' => MedicalConstants::INVOICE_TYPES
        ]));
        Route::get('/invoice-statuses', fn() => response()->json([
            'data' => MedicalConstants::INVOICE_STATUSES
        ]));
        Route::get('/payment-statuses', fn() => response()->json([
            'data' => MedicalConstants::PAYMENT_STATUSES
        ]));
        Route::get('/billing-payment-methods', fn() => response()->json([
            'data' => MedicalConstants::BILLING_PAYMENT_METHODS
        ]));

        // Get all lookups in one call
        Route::get('/all', fn() => response()->json([
            'data' => [
                'policy_types' => MedicalConstants::POLICY_TYPES,
                'application_types' => MedicalConstants::APPLICATION_TYPES,
                'application_statuses' => MedicalConstants::APPLICATION_STATUSES,
                'policy_statuses' => MedicalConstants::POLICY_STATUSES,
                'member_types' => MedicalConstants::MEMBER_TYPES,
                'member_statuses' => MedicalConstants::MEMBER_STATUSES,
                'relationships' => MedicalConstants::RELATIONSHIPS,
                'billing_frequencies' => MedicalConstants::BILLING_FREQUENCIES,
                'underwriting_statuses' => MedicalConstants::UW_STATUSES,
                'market_segments' => MedicalConstants::MARKET_SEGMENTS,
                'company_sizes' => MedicalConstants::COMPANY_SIZES,
                'contact_types' => MedicalConstants::CONTACT_TYPES,
                'group_statuses' => MedicalConstants::GROUP_STATUSES,
                'card_statuses' => MedicalConstants::CARD_STATUSES,
                // 'document_types' => MedicalConstants::DOCUMENT_TYPES,
                'application_sources' => MedicalConstants::APPLICATION_SOURCES,
                'benefit_types' => MedicalConstants::BENEFIT_TYPES,
                'limit_types' => MedicalConstants::LIMIT_TYPES,
                'copay_types' => MedicalConstants::COPAY_TYPES,
                'exclusion_types' => MedicalConstants::EXCLUSION_TYPES,
                'endorsement_types' => MedicalConstants::ENDORSEMENT_TYPES,
                'endorsement_statuses' => MedicalConstants::ENDORSEMENT_STATUSES,
                'claim_types' => MedicalConstants::CLAIM_TYPES,
                'claim_statuses' => MedicalConstants::CLAIM_STATUSES,
                'claim_rejection_reasons' => MedicalConstants::CLAIM_REJECTION_REASONS,
                'submission_types' => MedicalConstants::SUBMISSION_TYPES,
                'provider_types' => MedicalConstants::PROVIDER_TYPES,
                'invoice_types' => MedicalConstants::INVOICE_TYPES,
                'invoice_statuses' => MedicalConstants::INVOICE_STATUSES,
                'payment_statuses' => MedicalConstants::PAYMENT_STATUSES,
                'billing_payment_methods' => MedicalConstants::BILLING_PAYMENT_METHODS,
            ]
        ]));
    });

});