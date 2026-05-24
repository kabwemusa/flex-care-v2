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
use Modules\Medical\Http\Controllers\PublicApplicationController;
use Modules\Medical\Http\Controllers\PublicProviderController;
use Modules\Medical\Http\Controllers\PublicPlanController;
use Modules\Medical\Http\Controllers\PublicAddonController;
use Modules\Medical\Http\Controllers\PublicQuoteController;
use Modules\Medical\Http\Controllers\MemberAuthController;
use Modules\Medical\Http\Controllers\MemberPortalController;
use Modules\Medical\Constants\MedicalConstants;

/*
|--------------------------------------------------------------------------
| Medical Module API Routes
|--------------------------------------------------------------------------
|
| All routes are protected by:
| - auth:sanctum - Requires valid authentication token
| - module:medical - Requires user to have Medical module access
| - permission:* - Requires specific permission for each action
|
*/

// =========================================================================
// PUBLIC ROUTES — Website browsing & quoting (no auth required)
// =========================================================================
// Rate limiting: 60 requests/minute for browsing, 20/minute for quotes
Route::prefix('v1/medical/public')->group(function () {
    // Browsing endpoints - higher rate limit
    Route::middleware(['throttle:60,1'])->group(function () {
        Route::get('plans', [PublicPlanController::class, 'index']);
        Route::get('plans/{plan}', [PublicPlanController::class, 'show']);
        Route::post('plans/compare', [PublicPlanController::class, 'compare']);
        Route::get('addons', [PublicAddonController::class, 'index']);
        Route::get('addons/{addon}', [PublicAddonController::class, 'show']);
        Route::get('plans/{planId}/addons', [PublicAddonController::class, 'forPlan']);
    });

    // Quote endpoints - lower rate limit (CPU intensive)
    Route::middleware(['throttle:20,1'])->group(function () {
        Route::post('quotes', [PublicQuoteController::class, 'generate']);
        Route::post('quotes/compare', [PublicQuoteController::class, 'compare']);
    });

    // Application submission - strict rate limit
    Route::middleware(['throttle:10,1'])->group(function () {
        Route::post('applications', [PublicApplicationController::class, 'store']);
    });

    // Provider registration - very strict rate limit
    Route::middleware(['throttle:5,1'])->group(function () {
        Route::post('providers/register', [PublicProviderController::class, 'register']);
    });
});

// =========================================================================
// MEMBER PORTAL ROUTES — Customer self-service portal
// =========================================================================
Route::prefix('v1/medical/member')->group(function () {
    // ─── Auth endpoints (public, rate limited) ───────────────────────────────
    Route::prefix('auth')->middleware(['throttle:10,1'])->group(function () {
        Route::post('request-otp', [MemberAuthController::class, 'requestOtp']);
        Route::post('verify-otp', [MemberAuthController::class, 'verifyOtp']);
        Route::post('login', [MemberAuthController::class, 'login']);
        Route::post('forgot-password', [MemberAuthController::class, 'forgotPassword']);
        Route::post('reset-password', [MemberAuthController::class, 'resetPassword']);
    });

    // ─── Authenticated member endpoints ──────────────────────────────────────
    Route::middleware(['member.auth'])->group(function () {
        // Auth management
        Route::get('auth/me', [MemberAuthController::class, 'me']);
        Route::post('auth/set-password', [MemberAuthController::class, 'setPassword']);
        Route::post('auth/logout', [MemberAuthController::class, 'logout']);
        Route::post('auth/logout-all', [MemberAuthController::class, 'logoutAll']);

        // Portal
        Route::prefix('portal')->group(function () {
            // Dashboard
            Route::get('dashboard', [MemberPortalController::class, 'dashboard']);

            // Policy & ID Cards
            Route::get('policy', [MemberPortalController::class, 'policy']);
            Route::get('id-card/{memberId?}', [MemberPortalController::class, 'idCard']);

            // Claims
            Route::get('claims', [MemberPortalController::class, 'claims']);
            Route::get('claims/{id}', [MemberPortalController::class, 'claimDetail']);

            // Applications
            Route::get('applications', [MemberPortalController::class, 'applications']);

            // Profile
            Route::get('profile', [MemberPortalController::class, 'profile']);
            Route::put('profile', [MemberPortalController::class, 'updateProfile']);
        });
    });
});

// =========================================================================
// AUTHENTICATED ROUTES (Admin/Staff)
// =========================================================================
Route::prefix('v1/medical')
    ->middleware(['auth:sanctum', 'session.check', 'module:medical'])
    ->group(function () {

    // =========================================================================
    // SCHEMES
    // =========================================================================
    Route::middleware(['permission:medical.schemes.view'])->group(function () {
        Route::get('schemes', [SchemeController::class, 'index']);
        Route::get('schemes/dropdown', [SchemeController::class, 'dropdown']);
        Route::get('schemes/{scheme}', [SchemeController::class, 'show']);
    });
    Route::post('schemes', [SchemeController::class, 'store'])->middleware('permission:medical.schemes.create');
    Route::patch('schemes/{scheme}', [SchemeController::class, 'update'])->middleware('permission:medical.schemes.update');
    Route::delete('schemes/{scheme}', [SchemeController::class, 'destroy'])->middleware('permission:medical.schemes.delete');
    Route::post('schemes/{id}/activate', [SchemeController::class, 'activate'])->middleware('permission:medical.schemes.activate');

    // =========================================================================
    // PLANS
    // =========================================================================
    Route::middleware(['permission:medical.plans.view'])->group(function () {
        Route::get('plans', [PlanController::class, 'index']);
        Route::get('plans/dropdown', [PlanController::class, 'dropdown']);
        Route::get('plans/{plan}', [PlanController::class, 'show']);
        Route::post('plans/compare', [PlanController::class, 'compare']);
        Route::get('plans/{plan}/export-pdf', [PlanController::class, 'exportPdf']);
        Route::get('schemes/{scheme}/plans', [PlanController::class, 'byScheme']);
    });
    Route::post('plans', [PlanController::class, 'store'])->middleware('permission:medical.plans.create');
    Route::put('plans/{plan}', [PlanController::class, 'update'])->middleware('permission:medical.plans.update');
    Route::delete('plans/{plan}', [PlanController::class, 'destroy'])->middleware('permission:medical.plans.delete');
    Route::post('plans/{plan}/clone', [PlanController::class, 'clone'])->middleware('permission:medical.plans.create');
    Route::post('plans/{plan}/activate', [PlanController::class, 'activate'])->middleware('permission:medical.plans.configure');

    // =========================================================================
    // BENEFITS
    // =========================================================================
    Route::middleware(['permission:medical.benefits.view'])->group(function () {
        Route::get('benefits/tree', [BenefitController::class, 'tree']);
        Route::get('benefits', [BenefitController::class, 'index']);
        Route::get('benefits/{benefit}', [BenefitController::class, 'show']);
    });
    Route::middleware(['permission:medical.plans.view'])->group(function () {
        Route::get('plans/{plan}/benefits', [BenefitController::class, 'planBenefits']);
        Route::get('plans/{plan}/benefit-schedule', [BenefitController::class, 'schedule']);
        Route::post('benefits/check-eligibility', [BenefitController::class, 'checkEligibility']);
    });
    Route::middleware(['permission:medical.benefits.create'])->group(function () {
        Route::post('benefits', [BenefitController::class, 'store']);
    });
    Route::middleware(['permission:medical.benefits.update'])->group(function () {
        Route::put('benefits/{benefit}', [BenefitController::class, 'update']);
    });
    Route::middleware(['permission:medical.benefits.delete'])->group(function () {
        Route::delete('benefits/{benefit}', [BenefitController::class, 'destroy']);
    });
    Route::middleware(['permission:medical.plans.configure'])->group(function () {
        Route::post('plans/{plan}/benefits', [BenefitController::class, 'addToPlan']);
        Route::post('plans/{plan}/benefits/bulk', [BenefitController::class, 'bulkAddToPlan']);
        Route::put('plan-benefits/{planBenefit}', [BenefitController::class, 'updatePlanBenefit']);
        Route::delete('plan-benefits/{planBenefit}', [BenefitController::class, 'removeFromPlan']);
    });

    // =========================================================================
    // RATE CARDS
    // =========================================================================
    Route::middleware(['permission:medical.rate_cards.view'])->group(function () {
        Route::get('rate-cards', [RateCardController::class, 'index']);
        Route::get('rate-cards/{rateCard}', [RateCardController::class, 'show']);
        Route::get('plans/{plan}/rate-cards', [RateCardController::class, 'byPlan']);
        Route::post('rate-cards/{rateCard}/calculate', [RateCardController::class, 'calculate']);
    });
    Route::post('rate-cards', [RateCardController::class, 'store'])->middleware('permission:medical.rate_cards.create');
    Route::put('rate-cards/{rateCard}', [RateCardController::class, 'update'])->middleware('permission:medical.rate_cards.update');
    Route::delete('rate-cards/{rateCard}', [RateCardController::class, 'destroy'])->middleware('permission:medical.rate_cards.delete');
    Route::post('rate-cards/{rateCard}/activate', [RateCardController::class, 'activate'])->middleware('permission:medical.rate_cards.activate');
    Route::post('rate-cards/{rateCard}/clone', [RateCardController::class, 'clone'])->middleware('permission:medical.rate_cards.create');

    // Rate Card Entries & Tiers
    Route::middleware(['permission:medical.rate_cards.update'])->group(function () {
        Route::post('rate-cards/{rateCard}/entries', [RateCardController::class, 'addEntry']);
        Route::post('rate-cards/{rateCard}/entries/bulk', [RateCardController::class, 'bulkImportEntries']);
        Route::put('rate-card-entries/{entry}', [RateCardController::class, 'updateEntry']);
        Route::delete('rate-card-entries/{entry}', [RateCardController::class, 'deleteEntry']);
        Route::post('rate-cards/{rateCard}/tiers', [RateCardController::class, 'addTier']);
        Route::put('rate-card-tiers/{tier}', [RateCardController::class, 'updateTier']);
        Route::delete('rate-card-tiers/{tier}', [RateCardController::class, 'deleteTier']);
    });

    // =========================================================================
    // ADDONS
    // =========================================================================
    Route::middleware(['permission:medical.addons.view'])->group(function () {
        Route::get('addons', [AddonController::class, 'index']);
        Route::get('addons/dropdown', [AddonController::class, 'dropdown']);
        Route::get('addons/{addon}', [AddonController::class, 'show']);
        Route::get('plans/{plan}/addons', [AddonController::class, 'planAddons']);
        Route::get('plans/{plan}/available-addons', [AddonController::class, 'availableAddons']);
    });
    Route::post('addons', [AddonController::class, 'store'])->middleware('permission:medical.addons.create');
    Route::put('addons/{addon}', [AddonController::class, 'update'])->middleware('permission:medical.addons.update');
    Route::delete('addons/{addon}', [AddonController::class, 'destroy'])->middleware('permission:medical.addons.delete');
    Route::post('addons/{addon}/activate', [AddonController::class, 'activate'])->middleware('permission:medical.addons.update');

    // Addon Configuration
    Route::middleware(['permission:medical.addons.update'])->group(function () {
        Route::post('addons/{addon}/benefits', [AddonController::class, 'addBenefit']);
        Route::put('addon-benefits/{addonBenefit}', [AddonController::class, 'updateBenefit']);
        Route::delete('addon-benefits/{addonBenefit}', [AddonController::class, 'removeBenefit']);
        Route::post('addons/{addon}/rates', [AddonController::class, 'addRate']);
        Route::post('addon-rates/{addonRate}/activate', [AddonController::class, 'activateRate']);
    });

    // Plan Addons Configuration
    Route::middleware(['permission:medical.plans.configure'])->group(function () {
        Route::post('plans/{plan}/addons', [AddonController::class, 'configurePlanAddon']);
        Route::put('plan-addons/{planAddon}', [AddonController::class, 'updatePlanAddon']);
        Route::delete('plan-addons/{planAddon}', [AddonController::class, 'removePlanAddon']);
    });

    // =========================================================================
    // PLAN EXCLUSIONS
    // =========================================================================
    Route::middleware(['permission:medical.plans.view'])->group(function () {
        Route::get('plans/{plan}/exclusions', [PlanExclusionController::class, 'index']);
        Route::get('plan-exclusions/{planExclusion}', [PlanExclusionController::class, 'show']);
    });
    Route::middleware(['permission:medical.plans.configure'])->group(function () {
        Route::post('plans/{plan}/exclusions', [PlanExclusionController::class, 'store']);
        Route::post('plans/{plan}/exclusions/bulk-delete', [PlanExclusionController::class, 'bulkDelete']);
        Route::put('plan-exclusions/{planExclusion}', [PlanExclusionController::class, 'update']);
        Route::delete('plan-exclusions/{planExclusion}', [PlanExclusionController::class, 'destroy']);
        Route::post('plan-exclusions/{planExclusion}/activate', [PlanExclusionController::class, 'activate']);
    });

    // =========================================================================
    // DISCOUNTS & PROMO CODES
    // =========================================================================
    Route::middleware(['permission:medical.discounts.view'])->group(function () {
        Route::get('plans/{plan}/discounts', [DiscountController::class, 'forPlan']);
        Route::post('discounts/simulate', [DiscountController::class, 'simulate']);
        Route::get('discount-rules', [DiscountController::class, 'index']);
        Route::get('discount-rules/{discountRule}', [DiscountController::class, 'show']);
        Route::post('promo-codes/validate', [DiscountController::class, 'validatePromoCode']);
        Route::get('promo-codes', [DiscountController::class, 'promoCodes']);
        Route::get('promo-codes/{promoCode}', [DiscountController::class, 'showPromoCode']);
    });
    Route::middleware(['permission:medical.discounts.create'])->group(function () {
        Route::post('discount-rules', [DiscountController::class, 'store']);
        Route::post('promo-codes', [DiscountController::class, 'storePromoCode']);
    });
    Route::middleware(['permission:medical.discounts.update'])->group(function () {
        Route::put('discount-rules/{discountRule}', [DiscountController::class, 'update']);
        Route::post('promo-codes/apply', [DiscountController::class, 'applyPromoCode']);
        Route::put('promo-codes/{promoCode}', [DiscountController::class, 'updatePromoCode']);
    });
    Route::middleware(['permission:medical.discounts.delete'])->group(function () {
        Route::delete('discount-rules/{discountRule}', [DiscountController::class, 'destroy']);
        Route::delete('promo-codes/{promoCode}', [DiscountController::class, 'destroyPromoCode']);
    });

    // =========================================================================
    // QUOTES (Quick quotes without creating application)
    // =========================================================================
    Route::middleware(['permission:medical.applications.quote'])->group(function () {
        Route::post('quotes', [QuoteController::class, 'generate']);
        Route::post('quotes/compare', [QuoteController::class, 'compare']);
        Route::post('group-quotes', [QuoteController::class, 'groupQuote']);
        Route::get('plans/{plan}/quick-quote', [QuoteController::class, 'quickQuote']);
        Route::post('quotes/convert-frequency', [QuoteController::class, 'convertFrequency']);
        Route::post('quote', [ApplicationController::class, 'generateQuote']);
    });

    // =========================================================================
    // LOADING RULES
    // =========================================================================
    Route::middleware(['permission:medical.loading_rules.view'])->group(function () {
        Route::get('loading-rules', [LoadingController::class, 'index']);
        Route::get('loading-rules/search', [LoadingController::class, 'search']);
        Route::get('loading-rules/categories', [LoadingController::class, 'categories']);
        Route::get('loading-rules/by-category/{category}', [LoadingController::class, 'byCategory']);
        Route::get('loading-rules/options/{identifier}', [LoadingController::class, 'options']);
        Route::get('loading-rules/{loadingRule}', [LoadingController::class, 'show']);
        Route::post('loadings/calculate', [LoadingController::class, 'calculate']);
    });
    Route::middleware(['permission:medical.loading_rules.create'])->group(function () {
        Route::post('loading-rules', [LoadingController::class, 'store']);
    });
    Route::middleware(['permission:medical.loading_rules.update'])->group(function () {
        Route::put('loading-rules/{loadingRule}', [LoadingController::class, 'update']);
    });
    Route::middleware(['permission:medical.loading_rules.delete'])->group(function () {
        Route::delete('loading-rules/{loadingRule}', [LoadingController::class, 'destroy']);
    });

    // =========================================================================
    // CORPORATE GROUPS
    // =========================================================================
    Route::prefix('groups')->group(function () {
        Route::middleware(['permission:medical.groups.view'])->group(function () {
            Route::get('/', [GroupController::class, 'index']);
            Route::get('/dropdown', [GroupController::class, 'dropdown']);
            Route::get('/{id}', [GroupController::class, 'show']);
            Route::get('/{id}/members', [GroupController::class, 'members']);
            Route::get('/{groupId}/contacts', [GroupController::class, 'contacts']);
            Route::get('/{id}/plan-distribution', [GroupController::class, 'planDistribution']);
        });
        Route::post('/', [GroupController::class, 'store'])->middleware('permission:medical.groups.create');
        Route::put('/{id}', [GroupController::class, 'update'])->middleware('permission:medical.groups.update');
        Route::delete('/{id}', [GroupController::class, 'destroy'])->middleware('permission:medical.groups.delete');

        // Status actions
        Route::post('/{id}/activate', [GroupController::class, 'activate'])->middleware('permission:medical.groups.update');
        Route::post('/{id}/suspend', [GroupController::class, 'suspend'])->middleware('permission:medical.groups.update');

        // Contacts
        Route::middleware(['permission:medical.groups.update'])->group(function () {
            Route::post('/{groupId}/contacts', [GroupController::class, 'addContact']);
            Route::put('/{groupId}/contacts/{contactId}', [GroupController::class, 'updateContact']);
            Route::delete('/{groupId}/contacts/{contactId}', [GroupController::class, 'removeContact']);
            Route::post('/{groupId}/contacts/{contactId}/primary', [GroupController::class, 'setPrimaryContact']);
        });
    });

    // =========================================================================
    // APPLICATIONS (Application-First Workflow)
    // =========================================================================
    Route::prefix('applications')->group(function () {
        // View permissions
        Route::middleware(['permission:medical.applications.view'])->group(function () {
            Route::get('/', [ApplicationController::class, 'index']);
            Route::get('/stats', [ApplicationController::class, 'stats']);
            Route::get('/{id}', [ApplicationController::class, 'show']);
            Route::get('/{id}/members', [ApplicationController::class, 'members']);
            Route::get('/{id}/documents', [ApplicationController::class, 'documents']);
            Route::get('/{id}/documents/{documentId}/download', [ApplicationController::class, 'downloadDocument']);
            Route::get('/{id}/approval-status', [ApplicationController::class, 'approvalStatus']);
            Route::get('/{id}/can-approve', [ApplicationController::class, 'canApprove']);
        });

        // Create permissions
        Route::middleware(['permission:medical.applications.create'])->group(function () {
            Route::post('/', [ApplicationController::class, 'store']);
            Route::post('/import-census', [ApplicationController::class, 'importCensus']);
            Route::post('/create-from-census', [ApplicationController::class, 'createFromCensus']);
            Route::post('/create-multi-plan-from-census', [ApplicationController::class, 'createMultiPlanFromCensus']);
        });

        // Update permissions
        Route::middleware(['permission:medical.applications.update'])->group(function () {
            Route::patch('/{id}', [ApplicationController::class, 'update']);
            Route::post('/{id}/members', [ApplicationController::class, 'addMember']);
            Route::patch('/{appId}/members/{memberId}', [ApplicationController::class, 'updateMember']);
            Route::delete('/{appId}/members/{memberId}', [ApplicationController::class, 'removeMember']);
            Route::post('/{id}/addons', [ApplicationController::class, 'addAddon']);
            Route::delete('/{id}/addons/{addonId}', [ApplicationController::class, 'removeAddon']);
            Route::post('/{id}/promo-code', [ApplicationController::class, 'applyPromoCode']);
            Route::post('/{id}/documents', [ApplicationController::class, 'uploadDocument']);
        });

        Route::delete('/{id}', [ApplicationController::class, 'destroy'])->middleware('permission:medical.applications.delete');

        // Quote permissions
        Route::middleware(['permission:medical.applications.quote'])->group(function () {
            Route::post('/{id}/calculate-premium', [ApplicationController::class, 'calculatePremium']);
            Route::post('/{id}/quote', [ApplicationController::class, 'markAsQuoted']);
            Route::get('/{id}/quote/download', [ApplicationController::class, 'downloadQuote']);
            Route::post('/{id}/quote/email', [ApplicationController::class, 'emailQuote']);
            Route::post('/{id}/regenerate-quote', [ApplicationController::class, 'regenerateQuote']);
        });

        // Submit permission
        Route::post('/{id}/submit', [ApplicationController::class, 'submit'])->middleware('permission:medical.applications.submit');
        Route::post('/{id}/accept', [ApplicationController::class, 'accept'])->middleware('permission:medical.applications.update');
        Route::post('/{id}/cancel', [ApplicationController::class, 'cancel'])->middleware('permission:medical.applications.update');

        // Underwriting permissions
        Route::middleware(['permission:medical.underwriting.assess'])->group(function () {
            Route::post('/{id}/start-underwriting', [ApplicationController::class, 'startUnderwriting']);
            Route::post('/{appId}/members/{memberId}/underwrite', [ApplicationController::class, 'underwriteMember']);
        });
        Route::middleware(['permission:medical.underwriting.add_loading'])->group(function () {
            Route::post('/{appId}/members/{memberId}/loadings', [ApplicationController::class, 'addMemberLoading']);
        });
        Route::middleware(['permission:medical.underwriting.add_exclusion'])->group(function () {
            Route::post('/{appId}/members/{memberId}/exclusions', [ApplicationController::class, 'addMemberExclusion']);
        });
        Route::post('/{id}/refer', [ApplicationController::class, 'refer'])->middleware('permission:medical.underwriting.assess');

        // Approval Workflow Actions
        Route::post('/{id}/approval/approve', [ApplicationController::class, 'approvalApprove'])->middleware('permission:medical.underwriting.approve');
        Route::post('/{id}/approval/reject', [ApplicationController::class, 'approvalReject'])->middleware('permission:medical.underwriting.reject');
        Route::post('/{id}/approval/return', [ApplicationController::class, 'approvalReturn'])->middleware('permission:medical.underwriting.assess');

        // Convert to policy
        Route::post('/{id}/convert', [ApplicationController::class, 'convert'])->middleware('permission:medical.policies.create');
    });

    // =========================================================================
    // POLICIES
    // =========================================================================
    Route::prefix('policies')->group(function () {
        // View permissions
        Route::middleware(['permission:medical.policies.view'])->group(function () {
            Route::get('/', [PolicyController::class, 'index']);
            Route::get('/stats', [PolicyController::class, 'stats']);
            Route::get('/for-renewal', [PolicyController::class, 'forRenewal']);
            Route::get('/{id}', [PolicyController::class, 'show']);
            Route::get('/{id}/documents', [PolicyController::class, 'documents']);
            Route::get('/{id}/documents/{documentId}/download', [PolicyController::class, 'downloadDocument']);
            Route::get('/{policyId}/endorsements', [EndorsementController::class, 'forPolicy']);
        });

        // Update permissions
        Route::middleware(['permission:medical.policies.update'])->group(function () {
            Route::put('/{id}', [PolicyController::class, 'update']);
            Route::post('/{id}/documents', [PolicyController::class, 'uploadDocument']);
            Route::post('/{id}/calculate-premium', [PolicyController::class, 'calculatePremium']);
        });

        Route::delete('/{id}', [PolicyController::class, 'destroy'])->middleware('permission:medical.policies.update');

        // Status actions
        Route::post('/{id}/activate', [PolicyController::class, 'activate'])->middleware('permission:medical.policies.update');
        Route::post('/{id}/suspend', [PolicyController::class, 'suspend'])->middleware('permission:medical.policies.suspend');
        Route::post('/{id}/cancel', [PolicyController::class, 'cancel'])->middleware('permission:medical.policies.cancel');
        Route::post('/{id}/reinstate', [PolicyController::class, 'reinstate'])->middleware('permission:medical.policies.reinstate');

        // Renewal
        Route::post('/{id}/renewal-application', [ApplicationController::class, 'createRenewalApplication'])->middleware('permission:medical.policies.renew');
        Route::post('/{id}/renew', [PolicyController::class, 'renew'])->middleware('permission:medical.policies.renew');

        // Legacy underwriting (for policies not created via application workflow)
        Route::post('/{id}/approve', [PolicyController::class, 'approve'])->middleware('permission:medical.underwriting.approve');
        Route::post('/{id}/decline', [PolicyController::class, 'decline'])->middleware('permission:medical.underwriting.reject');
        Route::post('/{id}/refer', [PolicyController::class, 'refer'])->middleware('permission:medical.underwriting.assess');

        // Members (mid-term additions/removals)
        Route::post('/{id}/members', [PolicyController::class, 'addMember'])->middleware('permission:medical.members.add');
        Route::delete('/{id}/members/{memberId}', [PolicyController::class, 'removeMember'])->middleware('permission:medical.members.remove');

        // Addons (mid-term modifications)
        Route::post('/{id}/addons', [PolicyController::class, 'addAddon'])->middleware('permission:medical.policies.update');
        Route::delete('/{id}/addons/{addonId}', [PolicyController::class, 'removeAddon'])->middleware('permission:medical.policies.update');

        // Issue cards for all members
        Route::post('/{policyId}/issue-cards', [MemberController::class, 'issueCardsForPolicy'])->middleware('permission:medical.members.update');
    });

    // =========================================================================
    // ENDORSEMENTS
    // =========================================================================
    Route::prefix('endorsements')->group(function () {
        Route::middleware(['permission:medical.policies.view'])->group(function () {
            Route::get('/', [EndorsementController::class, 'index']);
            Route::get('/stats', [EndorsementController::class, 'stats']);
            Route::get('/types', [EndorsementController::class, 'types']);
            Route::get('/statuses', [EndorsementController::class, 'statuses']);
            Route::get('/{id}', [EndorsementController::class, 'show']);
        });
        Route::post('/', [EndorsementController::class, 'store'])->middleware('permission:medical.policies.update');
        Route::post('/{id}/approve', [EndorsementController::class, 'approve'])->middleware('permission:medical.underwriting.approve');
        Route::post('/{id}/reject', [EndorsementController::class, 'reject'])->middleware('permission:medical.underwriting.reject');
        Route::post('/{id}/process', [EndorsementController::class, 'process'])->middleware('permission:medical.policies.update');
        Route::post('/{id}/cancel', [EndorsementController::class, 'cancel'])->middleware('permission:medical.policies.update');
    });

    // =========================================================================
    // CLAIMS
    // =========================================================================
    Route::prefix('claims')->group(function () {
        Route::middleware(['permission:medical.claims.view'])->group(function () {
            Route::get('/', [ClaimController::class, 'index']);
            Route::get('/stats', [ClaimController::class, 'stats']);
            Route::get('/types', [ClaimController::class, 'types']);
            Route::get('/statuses', [ClaimController::class, 'statuses']);
            Route::get('/rejection-reasons', [ClaimController::class, 'rejectionReasons']);
            Route::post('/check-benefit-eligibility', [ClaimController::class, 'checkBenefitEligibility']);
            Route::get('/{id}', [ClaimController::class, 'show']);
        });

        Route::post('/', [ClaimController::class, 'store'])->middleware('permission:medical.claims.view');
        Route::put('/{id}', [ClaimController::class, 'update'])->middleware('permission:medical.claims.process');

        // Workflow actions
        Route::middleware(['permission:medical.claims.process'])->group(function () {
            Route::post('/{id}/start-review', [ClaimController::class, 'startReview']);
            Route::post('/{id}/request-documents', [ClaimController::class, 'requestDocuments']);
            Route::post('/{id}/adjudicate', [ClaimController::class, 'adjudicate']);
            Route::post('/{id}/assign', [ClaimController::class, 'assign']);
            Route::post('/{id}/lines', [ClaimController::class, 'addLine']);
            Route::post('/{id}/documents', [ClaimController::class, 'uploadDocument']);
            Route::post('/{id}/notes', [ClaimController::class, 'addNote']);
        });

        Route::post('/{id}/approve', [ClaimController::class, 'approve'])->middleware('permission:medical.claims.approve');
        Route::post('/{id}/reject', [ClaimController::class, 'reject'])->middleware('permission:medical.claims.reject');
        Route::post('/{id}/pay', [ClaimController::class, 'pay'])->middleware('permission:medical.claims.approve');
        Route::post('/{id}/close', [ClaimController::class, 'close'])->middleware('permission:medical.claims.process');
    });

    // Claims for a specific policy/member
    Route::get('policies/{policyId}/claims', [ClaimController::class, 'forPolicy'])->middleware('permission:medical.claims.view');
    Route::get('members/{memberId}/claims', [ClaimController::class, 'forMember'])->middleware('permission:medical.claims.view');

    // =========================================================================
    // BILLING (Invoices & Payments)
    // =========================================================================
    Route::prefix('billing')->group(function () {
        // View permissions
        Route::middleware(['permission:medical.billing.view'])->group(function () {
            Route::get('/stats', [BillingController::class, 'stats']);
            Route::get('/action-required', [BillingController::class, 'actionRequired']);
            Route::get('/unallocated-payments', [BillingController::class, 'unallocatedPayments']);
            Route::get('/invoice-types', [BillingController::class, 'invoiceTypes']);
            Route::get('/invoice-statuses', [BillingController::class, 'invoiceStatuses']);
            Route::get('/payment-methods', [BillingController::class, 'paymentMethods']);
            Route::get('/payment-statuses', [BillingController::class, 'paymentStatuses']);
            Route::get('/invoices', [BillingController::class, 'invoices']);
            Route::get('/invoices/{id}', [BillingController::class, 'showInvoice']);
            Route::get('/payments', [BillingController::class, 'payments']);
            Route::get('/payments/{id}', [BillingController::class, 'showPayment']);
        });

        // Invoice actions
        Route::post('/invoices/{id}/send', [BillingController::class, 'sendInvoice'])->middleware('permission:medical.billing.invoices.send');
        Route::post('/invoices/{id}/cancel', [BillingController::class, 'cancelInvoice'])->middleware('permission:medical.billing.invoices.cancel');
        Route::post('/invoices/{id}/write-off', [BillingController::class, 'writeOffInvoice'])->middleware('permission:medical.billing.invoices.cancel');
        Route::post('/invoices/{invoiceId}/payments', [BillingController::class, 'recordPaymentForInvoice'])->middleware('permission:medical.billing.payments.record');

        // Payment actions
        Route::post('/payments', [BillingController::class, 'recordPayment'])->middleware('permission:medical.billing.payments.record');
        Route::post('/payments/{paymentId}/allocate', [BillingController::class, 'allocatePayment'])->middleware('permission:medical.billing.payments.allocate');
        Route::post('/payments/{paymentId}/auto-allocate', [BillingController::class, 'autoAllocatePayment'])->middleware('permission:medical.billing.payments.allocate');
        Route::post('/payments/{id}/confirm', [BillingController::class, 'confirmPayment'])->middleware('permission:medical.billing.payments.record');
        Route::post('/payments/{id}/bounce', [BillingController::class, 'bouncePayment'])->middleware('permission:medical.billing.payments.record');
        Route::post('/payments/{id}/reverse', [BillingController::class, 'reversePayment'])->middleware('permission:medical.billing.payments.reverse');
        Route::post('/payments/{id}/reconcile', [BillingController::class, 'reconcilePayment'])->middleware('permission:medical.billing.payments.record');

        // Allocations
        Route::delete('/allocations/{allocationId}', [BillingController::class, 'reverseAllocation'])->middleware('permission:medical.billing.payments.reverse');
    });

    // Policy Billing
    Route::get('policies/{policyId}/invoices', [BillingController::class, 'invoicesForPolicy'])->middleware('permission:medical.billing.view');
    Route::get('policies/{policyId}/payments', [BillingController::class, 'paymentsForPolicy'])->middleware('permission:medical.billing.view');
    Route::get('policies/{policyId}/billing-summary', [BillingController::class, 'policyBillingSummary'])->middleware('permission:medical.billing.view');
    Route::post('policies/{policyId}/generate-invoice', [BillingController::class, 'generateInvoice'])->middleware('permission:medical.billing.invoices.create');
    Route::post('policies/{policyId}/generate-recurring-invoice', [BillingController::class, 'generateRecurringInvoice'])->middleware('permission:medical.billing.invoices.create');

    // Group Billing
    Route::get('groups/{groupId}/invoices', [BillingController::class, 'invoicesForGroup'])->middleware('permission:medical.billing.view');

    // Endorsement Invoice Generation
    Route::post('endorsements/{endorsementId}/generate-invoice', [BillingController::class, 'generateEndorsementInvoice'])->middleware('permission:medical.billing.invoices.create');

    // =========================================================================
    // MEMBERS
    // =========================================================================
    Route::prefix('members')->group(function () {
        // View permissions
        Route::middleware(['permission:medical.members.view'])->group(function () {
            Route::get('/', [MemberController::class, 'index']);
            Route::get('/stats', [MemberController::class, 'stats']);
            Route::get('/{id}', [MemberController::class, 'show']);
            Route::get('/{id}/eligibility', [MemberController::class, 'checkEligibility']);
            Route::get('/{id}/benefits', [MemberController::class, 'benefitBalances']);
            Route::get('/{id}/benefits/{benefitId}/history', [MemberController::class, 'benefitHistory']);
            Route::get('/{id}/dependents', [MemberController::class, 'dependents']);
            Route::get('/{id}/loadings', [MemberController::class, 'loadings']);
            Route::get('/{id}/exclusions', [MemberController::class, 'exclusions']);
            Route::get('/{id}/documents', [MemberController::class, 'documents']);
        });

        // Add member (mid-term)
        Route::post('/', [MemberController::class, 'store'])->middleware('permission:medical.members.add');

        // Update permissions
        Route::middleware(['permission:medical.members.update'])->group(function () {
            Route::put('/{id}', [MemberController::class, 'update']);
            Route::patch('/{id}', [MemberController::class, 'update']);
            Route::post('/{id}/activate', [MemberController::class, 'activate']);
            Route::post('/{id}/issue-card', [MemberController::class, 'issueCard']);
            Route::post('/{id}/activate-card', [MemberController::class, 'activateCard']);
            Route::post('/{id}/block-card', [MemberController::class, 'blockCard']);
            Route::post('/{id}/change-plan', [MemberController::class, 'changePlan']);
            Route::post('/{id}/dependents', [MemberController::class, 'addDependent']);
            Route::post('/{id}/documents', [MemberController::class, 'uploadDocument']);
            Route::post('/{memberId}/documents/{documentId}/verify', [MemberController::class, 'verifyDocument']);
        });

        // Remove/status change permissions
        Route::delete('/{id}', [MemberController::class, 'destroy'])->middleware('permission:medical.members.remove');
        Route::post('/{id}/suspend', [MemberController::class, 'suspend'])->middleware('permission:medical.members.suspend');
        Route::post('/{id}/terminate', [MemberController::class, 'terminate'])->middleware('permission:medical.members.exit');
        Route::post('/{id}/deceased', [MemberController::class, 'markDeceased'])->middleware('permission:medical.members.exit');

        // Underwriting permissions for loadings/exclusions
        Route::post('/{id}/loadings', [MemberController::class, 'addLoading'])->middleware('permission:medical.underwriting.add_loading');
        Route::delete('/{memberId}/loadings/{loadingId}', [MemberController::class, 'removeLoading'])->middleware('permission:medical.underwriting.add_loading');
        Route::post('/{id}/exclusions', [MemberController::class, 'addExclusion'])->middleware('permission:medical.underwriting.add_exclusion');
        Route::delete('/{memberId}/exclusions/{exclusionId}', [MemberController::class, 'removeExclusion'])->middleware('permission:medical.underwriting.add_exclusion');
    });

    // =========================================================================
    // LOOKUPS (Reference data for dropdowns) - No specific permission needed
    // These endpoints return minimal data for dropdown selections and are
    // accessible to any user with medical module access.
    // =========================================================================
    Route::prefix('lookups')->group(function () {
        // Entity lookups for dropdowns (active items only, minimal fields)
        // These are for selecting items in forms, not for managing them
        Route::get('/schemes', [SchemeController::class, 'dropdown']);
        Route::get('/plans', [PlanController::class, 'dropdown']);  // Supports ?scheme_id= filter
        Route::get('/rate-cards', [RateCardController::class, 'dropdown']);  // Supports ?plan_id= filter
        Route::get('/addons', [AddonController::class, 'dropdown']);
        Route::get('/groups', [GroupController::class, 'dropdown']);

        // Constant lookups
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
