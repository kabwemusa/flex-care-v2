<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ==========================================================================
     * MEDICAL INSURANCE - PRODUCT CONFIGURATION
     * ==========================================================================
     * 
     * Module: Medical
     * Prefix: med_
     * 
     * This module is FULLY INDEPENDENT. All tables can be dropped without
     * affecting other insurance lines (life, motor, etc.)
     * 
     * Hierarchy:
     * ----------
     * Scheme → Plan → Benefits (with limits)
     *              → Rate Cards (pricing)
     *              → Addons (optional extras)
     *              → Exclusions & Waiting Periods
     * 
     * ==========================================================================
     */

    public function up(): void
    {
        // ======================================================================
        // 1. SCHEMES - Product Line Container
        // ======================================================================
        // A Scheme is a branded medical product, e.g., "Corporate Health Plus"
        // It groups related plans and defines overall product rules
        
        Schema::create('med_schemes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // --- Identity ---
            $table->string('code')->unique()->index();       // CORP-HEALTH-2025
            $table->string('name');                          // "Corporate Health Plus"
            $table->string('slug')->unique();                // corporate-health-plus
            
            // --- Classification ---
            $table->string('market_segment')->index();       // corporate, individual, sme, micro
            
            // --- Configuration ---
            $table->text('description')->nullable();
            $table->json('eligibility_rules')->nullable();
            /*
                {
                    "min_group_size": 10,
                    "max_group_size": null,
                    "industries": ["all"],
                    "min_age": 18,
                    "max_age": 65
                }
            */
            
            $table->json('underwriting_rules')->nullable();
            /*
                {
                    "medical_exam_required": false,
                    "declaration_required": true,
                    "free_cover_limit": 50000
                }
            */
            
            // --- Status & Validity ---
            $table->boolean('is_active')->default(true);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();        // NULL = no end date
            
            // --- Audit ---
            $table->timestamps();
            $table->softDeletes();
        });

        // ======================================================================
        // 2. PLANS - Purchasable Product Tier
        // ======================================================================
        // A Plan is what the customer buys: "Gold", "Silver", "Bronze"
        
        Schema::create('med_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // --- Parent Reference ---
            $table->uuid('scheme_id');
            $table->foreign('scheme_id')
                  ->references('id')
                  ->on('med_schemes')
                  ->cascadeOnDelete();
            
            // --- Identity ---
            $table->string('code')->unique()->index();       // CORP-GOLD-2025
            $table->string('name');                          // "Gold Plan"
            $table->unsignedSmallInteger('tier_level')->default(1); // 1=highest tier
            
            // --- Plan Type ---
            $table->string('plan_type')->index();            // individual, family, group
            /*
                individual: Single person coverage
                family: Principal + dependents
                group: Corporate/employer-sponsored
            */
            
            // --- Member Configuration ---
            $table->json('member_config');
            /*
                {
                    "max_dependents": 5,
                    "allowed_member_types": ["principal", "spouse", "child", "parent"],
                    "child_age_limit": 21,
                    "child_student_age_limit": 25,
                    "parent_age_limit": 70
                }
            */
            
            // --- Default Waiting Periods (days) ---
            $table->json('default_waiting_periods')->nullable();
            /*
                {
                    "general": 30,
                    "pre_existing": 365,
                    "maternity": 300,
                    "chronic": 365
                }
            */
            
            // --- Default Cost Sharing ---
            $table->json('default_cost_sharing')->nullable();
            /*
                {
                    "co_pay_type": "fixed",       // fixed, percentage, none
                    "co_pay_amount": 50,          // K50 per visit
                    "co_pay_percentage": null,
                    "co_insurance": 20,           // Member pays 20%
                    "annual_deductible": 0
                }
            */
            
            // --- Network Configuration ---
            $table->string('network_type')->default('open'); // open, closed, hybrid
            $table->decimal('out_of_network_penalty', 5, 2)->default(0); // e.g., 30% less cover
            
            // --- Status & Validity ---
            $table->boolean('is_active')->default(true);
            $table->boolean('is_visible')->default(true);    // Show in product catalog
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            
            // --- Display ---
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('description')->nullable();
            $table->text('highlights')->nullable();          // Marketing bullet points
            
            // --- Audit ---
            $table->timestamps();
            $table->softDeletes();
            
            // --- Indexes ---
            $table->index(['scheme_id', 'is_active']);
            $table->index(['plan_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('med_plans');
        Schema::dropIfExists('med_schemes');
    }
};