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

/*
|--------------------------------------------------------------------------
| Medical Module API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1/medical')->group(function () {

    // =========================================================================
    // SCHEMES
    // =========================================================================
    Route::get('schemes/dropdown', [SchemeController::class, 'dropdown']);
    Route::apiResource('schemes', SchemeController::class);

    // =========================================================================
    // PLANS
    // =========================================================================
    Route::get('plans/dropdown', [PlanController::class, 'dropdown']);
    Route::post('plans/compare', [PlanController::class, 'compare']);
    Route::post('plans/{plan}/clone', [PlanController::class, 'clone']);
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

    Route::apiResource('rate-cards', RateCardController::class);

    // =========================================================================
    // ADDONS
    // =========================================================================
    Route::get('addons/dropdown', [AddonController::class, 'dropdown']);
    
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
    // QUOTES
    // =========================================================================
    Route::post('quotes', [QuoteController::class, 'generate']);
    Route::post('quotes/compare', [QuoteController::class, 'compare']);
    Route::get('plans/{plan}/quick-quote', [QuoteController::class, 'quickQuote']);
    Route::post('quotes/convert-frequency', [QuoteController::class, 'convertFrequency']);

    // =========================================================================
    // LOADING RULES
    // =========================================================================
    Route::get('loading-rules/search', [LoadingController::class, 'search']);
    Route::get('loading-rules/categories', [LoadingController::class, 'categories']);
    Route::get('loading-rules/by-category/{category}', [LoadingController::class, 'byCategory']);
    Route::get('loading-rules/options/{identifier}', [LoadingController::class, 'options']);
    Route::post('loadings/calculate', [LoadingController::class, 'calculate']);
    Route::apiResource('loading-rules', LoadingController::class);


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
    // POLICIES
    // =========================================================================
    Route::prefix('policies')->group(function () {
        Route::get('/', [PolicyController::class, 'index']);
        Route::get('/stats', [PolicyController::class, 'stats']);
        Route::get('/for-renewal', [PolicyController::class, 'forRenewal']);
        Route::post('/', [PolicyController::class, 'store']);
        Route::get('/{id}', [PolicyController::class, 'show']);
        Route::put('/{id}', [PolicyController::class, 'update']);
        Route::delete('/{id}', [PolicyController::class, 'destroy']);
        
        // Status actions
        Route::post('/{id}/activate', [PolicyController::class, 'activate']);
        Route::post('/{id}/suspend', [PolicyController::class, 'suspend']);
        Route::post('/{id}/cancel', [PolicyController::class, 'cancel']);
        Route::post('/{id}/reinstate', [PolicyController::class, 'reinstate']);
        
        // Underwriting
        Route::post('/{id}/approve', [PolicyController::class, 'approve']);
        Route::post('/{id}/decline', [PolicyController::class, 'decline']);
        Route::post('/{id}/refer', [PolicyController::class, 'refer']);
        
        // Renewal
        Route::post('/{id}/renew', [PolicyController::class, 'renew']);
        
        // Premium
        Route::post('/{id}/calculate-premium', [PolicyController::class, 'calculatePremium']);
        
        // Addons
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

});