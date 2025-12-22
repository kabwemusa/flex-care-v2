<?php

use Illuminate\Support\Facades\Route;
use Modules\Medical\Controllers\AddonController;
use Modules\Medical\Controllers\DiscountCardController;
use Modules\Medical\Controllers\FeatureController;
use Modules\Medical\Controllers\MedicalQuoteController;
use Modules\Medical\Controllers\SchemeController;

use Modules\Medical\Controllers\PlanController;
use Modules\Medical\Controllers\RateCardController;

Route::prefix('medical')->group(function () {
    Route::get('/quote', [MedicalQuoteController::class, 'calculate']);
});





Route::prefix('v1/medical')->group(function () {
    
    // 1. Scheme Management
    // Maps: GET /schemes, POST /schemes, GET /schemes/{id}, PUT /schemes/{id}, DELETE /schemes/{id}
    Route::apiResource('schemes', SchemeController::class);

    // 2. Plan Management
    // Maps: GET /plans, POST /plans, etc.
    Route::apiResource('plans', PlanController::class);

    // 3. Domain Specific Helper Routes
    Route::prefix('schemes/{scheme_id}')->group(function () {
        // Fetch plans filtered by a specific scheme (Great for the Angular "View Plans" button)
        Route::get('/plans', [PlanController::class, 'getPlansByScheme']);
    });

    Route::apiResource('features', FeatureController::class);
    Route::post('plans/{plan}/features', [PlanController::class, 'syncFeatures']);
    Route::post('plans/{plan}/addons', [PlanController::class, 'syncAddons']);
        //  ->name('medical.plans.sync-features');
    Route::apiResource('addons', AddonController::class);

    Route::apiResource('rate-cards', RateCardController::class);
    Route::post('rate-cards/{id}/entries', [RateCardController::class, 'syncEntries']);

    Route::apiResource('discount-cards', DiscountCardController::class);

});