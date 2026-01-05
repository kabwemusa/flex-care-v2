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
    ->middleware(['auth:sanctum', 'module:medical'])
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
    });

    // =========================================================================
    // APPLICATIONS (Application-First Workflow)
    // =========================================================================
    Route::prefix('applications')->group(function () {
        // CRUD
        Route::get('/', [ApplicationController::class, 'index']);
        Route::post('/', [ApplicationController::class, 'store']);
        Route::get('/stats', [ApplicationController::class, 'stats']);
        Route::get('/{id}', [ApplicationController::class, 'show']);
        Route::put('/{id}', [ApplicationController::class, 'update']);
        Route::delete('/{id}', [ApplicationController::class, 'destroy']);

        // Workflow Actions
        Route::post('/{id}/calculate-premium', [ApplicationController::class, 'calculatePremium']);
        Route::post('/{id}/quote', [ApplicationController::class, 'markAsQuoted']);
        Route::get('/{id}/quote/download', [ApplicationController::class, 'downloadQuote']);
        Route::post('/{id}/quote/email', [ApplicationController::class, 'emailQuote']);
        Route::post('/{id}/submit', [ApplicationController::class, 'submit']);
        Route::post('/{id}/start-underwriting', [ApplicationController::class, 'startUnderwriting']);
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
        
        // Addons (mid-term modifications)
        Route::post('/{id}/addons', [PolicyController::class, 'addAddon']);
        Route::delete('/{id}/addons/{addonId}', [PolicyController::class, 'removeAddon']);
        
        // Documents
        Route::get('/{id}/documents', [PolicyController::class, 'documents']);
        Route::post('/{id}/documents', [PolicyController::class, 'uploadDocument']);
        
        // Issue cards for all members
        Route::post('/{policyId}/issue-cards', [MemberController::class, 'issueCardsForPolicy']);
    });

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
        
        // Eligibility
        Route::get('/{id}/eligibility', [MemberController::class, 'checkEligibility']);
        
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
            ]
        ]));
    });

});