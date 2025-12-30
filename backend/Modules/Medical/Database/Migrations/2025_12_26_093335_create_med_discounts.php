<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ==========================================================================
     * MEDICAL DISCOUNTS & LOADINGS - Premium Adjustments
     * ==========================================================================
     * 
     * Two Types:
     * ----------
     * DISCOUNTS: Reduce premium (loyalty, annual payment, group size)
     * LOADINGS: Increase premium (health conditions, occupation risk)
     * 
     * Application:
     * ------------
     * - Automatic: Based on rules (group size > 50 = 10% off)
     * - Manual: Underwriter applies (medical loading)
     * - Promo Code: Customer enters code
     * 
     * ==========================================================================
     */

    public function up(): void
    {
        // ======================================================================
        // 1. DISCOUNT RULES - Automatic and Manual Discounts
        // ======================================================================
        
        Schema::create('med_discount_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // --- Identity ---
            $table->string('code')->unique();                // DISC-ANNUAL-10
            $table->string('name');                          // "Annual Payment Discount"
            
            // --- Scope (NULL = applies to all) ---
            $table->uuid('scheme_id')->nullable();
            $table->uuid('plan_id')->nullable();
            
            $table->foreign('scheme_id')
                  ->references('id')
                  ->on('med_schemes')
                  ->nullOnDelete();
                  
            $table->foreign('plan_id')
                  ->references('id')
                  ->on('med_plans')
                  ->nullOnDelete();
            
            // --- Type ---
            $table->string('adjustment_type')->default('discount');
            // discount, loading
            
            // --- Value ---
            $table->string('value_type');                    // percentage, fixed
            $table->decimal('value', 15, 2);                 // 10 (%) or 500 (ZMW)
            
            // --- Application Target ---
            $table->string('applies_to')->default('total');
            /*
                base: Base premium only
                total: Total premium (base + addons)
                addon: Specific addon only
            */
            
            // --- Application Method ---
            $table->string('application_method')->default('automatic');
            /*
                automatic: System applies when rules match
                manual: Admin/underwriter applies
                promo_code: Requires code entry
            */
            
            // --- Trigger Rules ---
            $table->json('trigger_rules')->nullable();
            /*
                {"billing_frequency": "annual"}
                {"group_size_min": 50}
                {"loyalty_years_min": 3}
                {"member_count_min": 100}
            */
            
            // --- Stacking ---
            $table->boolean('can_stack')->default(true);
            $table->unsignedInteger('priority')->default(0); // Order of application
            $table->decimal('max_total_discount', 5, 2)->nullable(); // Max % when stacking
            
            // --- Limits ---
            $table->decimal('max_discount_amount', 15, 2)->nullable(); // Cap in currency
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_count')->default(0);
            
            // --- Validity ---
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            
            // --- Description ---
            $table->text('description')->nullable();
            $table->text('terms_conditions')->nullable();
            
            // --- Approval ---
            $table->boolean('requires_approval')->default(false);
            $table->decimal('approval_threshold', 15, 2)->nullable();
            
            // --- Audit ---
            $table->timestamps();
            $table->softDeletes();
            
            // --- Indexes ---
            $table->index(['scheme_id', 'is_active']);
            $table->index(['plan_id', 'is_active']);
            $table->index(['adjustment_type', 'is_active']);
            $table->index(['application_method']);
        });

        // ======================================================================
        // 2. PROMO CODES - Promotional Discount Codes
        // ======================================================================
        
        Schema::create('med_promo_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // --- The Code ---
            $table->string('code')->unique();                // SUMMER2025
            $table->string('name');                          // "Summer 2025 Promo"
            
            // --- Link to Discount Rule ---
            $table->uuid('discount_rule_id');
            $table->foreign('discount_rule_id')
                  ->references('id')
                  ->on('med_discount_rules')
                  ->cascadeOnDelete();
            
            // --- Validity ---
            $table->date('valid_from');
            $table->date('valid_to');
            
            // --- Usage Limits ---
            $table->unsignedInteger('max_uses')->nullable(); // NULL = unlimited
            $table->unsignedInteger('current_uses')->default(0);
            $table->unsignedInteger('max_uses_per_policy')->default(1);
            
            // --- Eligibility ---
            $table->json('eligible_schemes')->nullable();    // NULL = all
            $table->json('eligible_plans')->nullable();      // NULL = all
            $table->json('eligible_groups')->nullable();     // Specific corporate clients
            
            // --- Status ---
            $table->boolean('is_active')->default(true);
            
            // --- Audit ---
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['code', 'is_active']);
            $table->index(['valid_from', 'valid_to']);
        });

        // ======================================================================
        // 3. MEDICAL LOADING RULES - Health Condition Premium Adjustments
        // ======================================================================
        // Standard loadings for known medical conditions
        
        Schema::create('med_loading_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // --- Condition Identity ---
            $table->string('code')->unique();                // LOAD-DIAB-TYPE2
            $table->string('condition_name');                // "Diabetes Type 2"
            $table->string('condition_category');            // chronic, pre_existing, lifestyle
            
            // --- Medical Coding (for claims integration) ---
            $table->string('icd10_code')->nullable();        // E11
            $table->json('related_icd_codes')->nullable();   // Related conditions
            
            // --- Loading Value ---
            $table->string('loading_type');                  // percentage, fixed, exclusion
            $table->decimal('loading_value', 15, 2)->nullable(); // 25 (%) or 500 (ZMW)
            $table->decimal('min_loading', 15, 2)->nullable();   // Floor
            $table->decimal('max_loading', 15, 2)->nullable();   // Cap
            
            // --- Duration ---
            $table->string('duration_type')->default('permanent');
            /*
                permanent: Never expires
                time_limited: For X months
                reviewable: Subject to annual review
            */
            $table->unsignedInteger('duration_months')->nullable();
            
            // --- Alternative: Exclusion ---
            $table->boolean('exclusion_available')->default(false);
            $table->uuid('exclusion_benefit_id')->nullable(); // Which benefit to exclude
            $table->text('exclusion_terms')->nullable();
            
            // --- Underwriting Guidelines ---
            $table->text('underwriting_notes')->nullable();
            $table->json('required_documents')->nullable();  // ["medical_report", "lab_results"]
            $table->json('assessment_criteria')->nullable(); // Severity assessment
            
            // --- Status ---
            $table->boolean('is_active')->default(true);
            
            // --- Audit ---
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['condition_category', 'is_active']);
            $table->index(['icd10_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('med_loading_rules');
        Schema::dropIfExists('med_promo_codes');
        Schema::dropIfExists('med_discount_rules');
    }
};