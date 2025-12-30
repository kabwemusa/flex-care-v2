<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ==========================================================================
     * MEDICAL RATE CARDS - Pricing Configuration
     * ==========================================================================
     * 
     * Rate Card Structure:
     * --------------------
     * Rate Card (version container)
     *    └── Rate Entries (age/gender/region → base premium)
     *    └── Rate Tiers (family size → flat premium) [optional]
     * 
     * Premium Calculation:
     * --------------------
     * 1. Get base premium from rate entry (age, gender, region)
     * 2. Apply member type factor (spouse +10%, child -30%)
     * 3. Add addon premiums
     * 4. Apply discounts
     * 5. Apply medical loadings (if any)
     * 
     * ==========================================================================
     */

    public function up(): void
    {
        // ======================================================================
        // 1. RATE CARDS - Pricing Version Container
        // ======================================================================
        
        Schema::create('med_rate_cards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // --- Reference ---
            $table->uuid('plan_id');
            $table->foreign('plan_id')
                  ->references('id')
                  ->on('med_plans')
                  ->cascadeOnDelete();
            
            // --- Identity ---
            $table->string('code')->unique();                // RC-GOLD-2025-V1
            $table->string('name');                          // "Gold Plan Rates 2025"
            $table->string('version')->default('1.0');
            
            // --- Currency & Frequency ---
            $table->char('currency', 3)->default('ZMW');
            $table->string('premium_frequency')->default('monthly');
            // monthly, quarterly, semi_annual, annual
            
            // --- Validity ---
            $table->date('effective_from');
            $table->date('effective_to')->nullable();        // NULL = no end date
            
            // --- Status ---
            $table->boolean('is_active')->default(false);    // Only ONE active per plan
            $table->boolean('is_draft')->default(true);
            
            // --- Premium Basis ---
            $table->string('premium_basis')->default('per_member');
            /*
                per_member: Each member rated individually
                per_family: Flat rate for family
                tiered: Based on family size tiers
            */
            
            // --- Member Type Loading Factors ---
            // Multiplier applied to base premium per member type
            $table->json('member_type_factors');
            /*
                {
                    "principal": 1.00,
                    "spouse": 1.00,
                    "child": 0.50,
                    "parent": 1.50
                }
            */
            
            // --- Notes ---
            $table->text('notes')->nullable();
            
            // --- Approval ---
            $table->uuid('created_by')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            
            // --- Audit ---
            $table->timestamps();
            $table->softDeletes();
            
            // --- Indexes ---
            $table->index(['plan_id', 'is_active']);
            $table->index(['effective_from', 'effective_to']);
        });

        // ======================================================================
        // 2. RATE CARD ENTRIES - Age/Gender/Region Price Matrix
        // ======================================================================
        
        Schema::create('med_rate_card_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            $table->uuid('rate_card_id');
            $table->foreign('rate_card_id')
                  ->references('id')
                  ->on('med_rate_cards')
                  ->cascadeOnDelete();
            
            // --- Rating Factors ---
            
            // Age Band
            $table->unsignedTinyInteger('min_age')->default(0);
            $table->unsignedTinyInteger('max_age')->default(100);
            $table->string('age_band_label')->nullable();    // "18-25", "26-35"
            
            // Gender (NULL = unisex rate)
            $table->char('gender', 1)->nullable();           // M, F, NULL
            
            // Region (NULL = national rate)
            $table->string('region_code')->nullable();       // LSK, KIT, NDL
            
            // --- The Premium ---
            $table->decimal('base_premium', 15, 2);
            
            // --- Audit ---
            $table->timestamps();
            
            // --- Indexes for Lookup ---
            $table->index(['rate_card_id', 'min_age', 'max_age'], 'idx_rc_age');
            $table->index(['rate_card_id', 'gender'], 'idx_rc_gender');
            $table->index(['rate_card_id', 'region_code'], 'idx_rc_region');
            
            // Composite for premium lookup
            $table->index(
                ['rate_card_id', 'min_age', 'max_age', 'gender', 'region_code'],
                'idx_rc_lookup'
            );
        });

        // ======================================================================
        // 3. RATE CARD TIERS - Family Size Based Pricing
        // ======================================================================
        // Used when premium_basis = 'tiered'
        
        Schema::create('med_rate_card_tiers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            $table->uuid('rate_card_id');
            $table->foreign('rate_card_id')
                  ->references('id')
                  ->on('med_rate_cards')
                  ->cascadeOnDelete();
            
            // --- Tier Definition ---
            $table->string('tier_name');                     // "M", "M+1", "M+2", "M+3+"
            $table->string('tier_description')->nullable();  // "Member Only", "Member + Spouse"
            $table->unsignedTinyInteger('min_members');      // 1
            $table->unsignedTinyInteger('max_members');      // 2
            
            // --- Premium ---
            $table->decimal('tier_premium', 15, 2);
            
            // --- Additional Member (if exceeds max) ---
            $table->decimal('extra_member_premium', 15, 2)->nullable();
            
            $table->unsignedInteger('sort_order')->default(0);
            
            $table->timestamps();
            
            $table->index(['rate_card_id', 'min_members', 'max_members']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('med_rate_card_tiers');
        Schema::dropIfExists('med_rate_card_entries');
        Schema::dropIfExists('med_rate_cards');
    }
};