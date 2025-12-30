<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ==========================================================================
     * MEDICAL BENEFIT CATALOG
     * ==========================================================================
     * 
     * Benefits are defined ONCE in a catalog, then LINKED to plans with limits.
     * 
     * Structure:
     * ----------
     * Category (In-Patient, Out-Patient, Dental...)
     *    └── Benefit (Surgery, Room & Board, Dental Extraction...)
     *           └── Sub-Benefit (Major Surgery, Minor Surgery...) [via parent_id]
     * 
     * This separates WHAT is covered (catalog) from HOW MUCH (plan_benefits)
     * 
     * ==========================================================================
     */

    public function up(): void
    {
        // ======================================================================
        // 1. BENEFIT CATEGORIES - Top-level groupings
        // ======================================================================
        // Examples: In-Patient, Out-Patient, Maternity, Dental, Optical, Wellness
        
        Schema::create('med_benefit_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            $table->string('code')->unique();                // CAT-INP, CAT-OPD, CAT-MAT
            $table->string('name');                          // "In-Patient Services"
            
            $table->text('description')->nullable();
            $table->string('icon')->nullable();              // For UI: "hospital-bed"
            $table->string('color')->nullable();             // For UI: "#4CAF50"
            
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            $table->softDeletes();
        });

        // ======================================================================
        // 2. BENEFITS - Master Benefit Library
        // ======================================================================
        // Reusable definitions across all medical plans
        
        Schema::create('med_benefits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // --- Category Reference ---
            $table->uuid('category_id');
            $table->foreign('category_id')
                  ->references('id')
                  ->on('med_benefit_categories')
                  ->cascadeOnDelete();
            
            // --- Hierarchy (for sub-benefits) ---
            // e.g., Surgery → Major Surgery, Minor Surgery
            $table->uuid('parent_id')->nullable();
            $table->foreign('parent_id')
                  ->references('id')
                  ->on('med_benefits')
                  ->nullOnDelete();
            
            // --- Identity ---
            $table->string('code')->unique()->index();       // BEN-INP-SURG-001
            $table->string('name');                          // "Surgical Procedures"
            $table->string('short_name')->nullable();        // "Surgery" (for cards)
            
            // --- Classification ---
            // $table->string('benefit_type')->index();         // core, optional, addon
            /*
                core: Standard benefit included in plans
                optional: Member can opt-in during enrollment
                addon: Requires additional premium (sold separately)
            */
            
            // --- Default Limit Configuration ---
            // These are DEFAULTS - overridden in med_plan_benefits
            $table->string('limit_type')->default('amount');
            /*
                amount: Currency-based (K50,000)
                visits: Count-based (12 visits)
                days: Duration-based (30 days)
                unlimited: No limit
                combined: Multiple types (K50k OR 12 visits)
            */
            
            $table->string('limit_frequency')->default('annual');
            /*
                annual: Resets every policy year
                lifetime: Never resets
                per_event: Per claim/incident
                per_visit: Each visit has its own limit
            */
            
            $table->string('limit_basis')->default('individual');
            /*
                individual: Each member has own limit
                family: Shared across family (floater)
                principal_only: Only principal gets this
            */
            
            // --- Waiting Period Category ---
            $table->string('waiting_period_type')->default('general');
            /*
                general: Standard waiting
                maternity: Maternity-specific
                pre_existing: Pre-existing conditions
                chronic: Chronic conditions
                none: No waiting period
            */
            
            // --- Claims Requirements ---
            $table->boolean('requires_preauth')->default(false);
            $table->boolean('requires_referral')->default(false);
            $table->json('required_documents')->nullable();  // ["invoice", "prescription"]
            
            // --- Applicable Member Types ---
            // NULL = applies to all member types
            $table->json('applicable_member_types')->nullable();
            // ["principal", "spouse"] - e.g., Maternity only for principal & spouse
            
            // --- Display ---
            $table->text('description')->nullable();
            $table->text('terms_conditions')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            
            // --- Audit ---
            $table->timestamps();
            $table->softDeletes();
            
            // --- Indexes ---
            $table->index(['category_id', 'is_active']);
            $table->index(['parent_id']);
            $table->string('benefit_type')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('med_benefits');
        Schema::dropIfExists('med_benefit_categories');
    }
};