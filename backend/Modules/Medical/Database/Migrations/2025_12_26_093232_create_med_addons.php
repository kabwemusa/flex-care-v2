<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ==========================================================================
     * MEDICAL ADDONS - Optional Coverage Extras
     * ==========================================================================
     * 
     * Addon Structure:
     * ----------------
     * Addon (catalog definition)
     *    └── Addon Benefits (what coverage it provides)
     *    └── Addon Rates (pricing)
     * 
     * Plan Addon (which plans can offer this addon)
     *    └── Availability (mandatory, optional, included)
     * 
     * ==========================================================================
     */

    public function up(): void
    {
        // ======================================================================
        // 1. ADDONS - Addon Catalog
        // ======================================================================
        
        Schema::create('med_addons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // --- Identity ---
            $table->string('code')->unique();                // ADD-DENTAL-BOOST
            $table->string('name');                          // "Dental Boost"
            
            // --- Classification ---
            $table->string('addon_type')->default('optional');
            /*
                optional: Customer chooses to add
                mandatory: Required with certain plans
                conditional: Required under certain conditions
            */
            
            // --- Description ---
            $table->text('description')->nullable();
            $table->text('terms_conditions')->nullable();
            
            // --- Availability ---
            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            
            // --- Display ---
            $table->string('icon')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            
            // --- Audit ---
            $table->timestamps();
            $table->softDeletes();
        });

        // ======================================================================
        // 2. ADDON BENEFITS - What benefits does the addon provide?
        // ======================================================================
        
        Schema::create('med_addon_benefits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            $table->uuid('addon_id');
            $table->uuid('benefit_id');
            
            $table->foreign('addon_id')
                  ->references('id')
                  ->on('med_addons')
                  ->cascadeOnDelete();
                  
            $table->foreign('benefit_id')
                  ->references('id')
                  ->on('med_benefits')
                  ->cascadeOnDelete();
            
            // --- Benefit Limits for this Addon ---
            $table->decimal('limit_amount', 15, 2)->nullable();
            $table->unsignedInteger('limit_count')->nullable();
            $table->unsignedInteger('limit_days')->nullable();
            
            $table->string('limit_type')->nullable();
            $table->string('limit_frequency')->nullable();
            $table->string('limit_basis')->nullable();
            
            // --- Waiting Period ---
            $table->unsignedInteger('waiting_period_days')->nullable();
            
            // --- Display ---
            $table->string('display_value')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            
            $table->timestamps();
            
            $table->unique(['addon_id', 'benefit_id']);
        });

        // ======================================================================
        // 3. PLAN ADDONS - Which addons are available on which plans?
        // ======================================================================
        
        Schema::create('med_plan_addons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            $table->uuid('plan_id');
            $table->uuid('addon_id');
            
            $table->foreign('plan_id')
                  ->references('id')
                  ->on('med_plans')
                  ->cascadeOnDelete();
                  
            $table->foreign('addon_id')
                  ->references('id')
                  ->on('med_addons')
                  ->cascadeOnDelete();
            
            // --- Availability on this Plan ---
            $table->string('availability')->default('optional');
            /*
                mandatory: Required, cannot opt out
                optional: Customer choice
                included: Comes free with plan
                conditional: Required if certain conditions met
            */
            
            $table->json('conditions')->nullable();          // For conditional availability
            
            // --- Plan-Specific Benefit Overrides ---
            // Override addon_benefits limits for this specific plan
            $table->json('benefit_overrides')->nullable();
            /*
                {
                    "benefit_uuid": { "limit_amount": 30000 }
                }
            */
            
            // --- Status ---
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            
            $table->timestamps();
            
            $table->unique(['plan_id', 'addon_id']);
        });

        // ======================================================================
        // 4. ADDON RATES - Addon Pricing
        // ======================================================================
        
        Schema::create('med_addon_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            $table->uuid('addon_id');
            $table->foreign('addon_id')
                  ->references('id')
                  ->on('med_addons')
                  ->cascadeOnDelete();
            
            // --- Plan-Specific Rate (optional) ---
            // NULL = applies to all plans
            $table->uuid('plan_id')->nullable();
            $table->foreign('plan_id')
                  ->references('id')
                  ->on('med_plans')
                  ->nullOnDelete();
            
            // --- Pricing Method ---
            $table->string('pricing_type')->default('fixed');
            /*
                fixed: Flat amount
                per_member: Per covered member
                percentage: % of base premium
                age_rated: Uses addon_rate_entries
            */
            
            // --- Fixed/Per-Member Amounts ---
            $table->char('currency', 3)->default('ZMW');
            $table->decimal('amount', 15, 2)->nullable();
            
            // --- Percentage Pricing ---
            $table->decimal('percentage', 5, 2)->nullable();
            $table->string('percentage_basis')->nullable();  // base_premium, total_premium
            
            // --- Validity ---
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(false);
            
            $table->timestamps();
            
            $table->index(['addon_id', 'is_active']);
            $table->index(['addon_id', 'plan_id']);
        });

        // ======================================================================
        // 5. ADDON RATE ENTRIES - Age-rated Addon Pricing
        // ======================================================================
        
        Schema::create('med_addon_rate_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            $table->uuid('addon_rate_id');
            $table->foreign('addon_rate_id')
                  ->references('id')
                  ->on('med_addon_rates')
                  ->cascadeOnDelete();
            
            // --- Age Band ---
            $table->unsignedTinyInteger('min_age')->default(0);
            $table->unsignedTinyInteger('max_age')->default(100);
            
            // --- Gender (optional) ---
            $table->char('gender', 1)->nullable();
            
            // --- Premium ---
            $table->decimal('premium', 15, 2);
            
            $table->timestamps();
            
            $table->index(['addon_rate_id', 'min_age', 'max_age']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('med_addon_rate_entries');
        Schema::dropIfExists('med_addon_rates');
        Schema::dropIfExists('med_plan_addons');
        Schema::dropIfExists('med_addon_benefits');
        Schema::dropIfExists('med_addons');
    }
};