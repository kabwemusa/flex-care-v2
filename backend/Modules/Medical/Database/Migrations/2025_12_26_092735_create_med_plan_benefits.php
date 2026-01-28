<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ==========================================================================
     * PLAN BENEFITS - Plan-Specific Benefit Configuration
     * ==========================================================================
     * 
     * This is where we define HOW MUCH of each benefit a specific plan offers.
     * 
     * Key Features:
     * - Overall limits per benefit
     * - Sub-limits (Surgery K50k within In-Patient K100k)
     * - Member-type specific limits (Principal K50k, Child K25k)
     * - Age-band limits (0-17: K25k, 18-65: K50k)
     * - Waiting period overrides
     * - Cost sharing overrides
     * 
     * ==========================================================================
     */

    public function up(): void
    {
        // ======================================================================
        // 1. PLAN BENEFITS - Links Benefits to Plans with Limits
        // ======================================================================
        
        Schema::create('med_plan_benefits', function (Blueprint $table) {

            // --- Primary Key (EXPLICIT) ---
            $table->uuid('id');
            $table->primary('id');
        
            // --- References ---
            $table->uuid('plan_id');
            $table->uuid('benefit_id');
        
            // --- Sub-limit Reference (self hierarchy) ---
            $table->uuid('parent_plan_benefit_id')->nullable();
        
            // --- Limit Overrides ---
            $table->string('limit_type')->nullable();
            $table->string('limit_frequency')->nullable();
            $table->string('limit_basis')->nullable();
        
            // --- Actual Limits ---
            $table->decimal('limit_amount', 15, 2)->nullable();
            $table->unsignedInteger('limit_count')->nullable();
            $table->unsignedInteger('limit_days')->nullable();
        
            // --- Per-Event Caps ---
            $table->decimal('per_claim_limit', 15, 2)->nullable();
            $table->decimal('per_day_limit', 15, 2)->nullable();
            $table->unsignedInteger('max_claims_per_year')->nullable();
        
            // --- Waiting Period ---
            $table->unsignedInteger('waiting_period_days')->nullable();
        
            // --- Cost Sharing ---
            $table->json('cost_sharing')->nullable();
        
            // --- Network ---
            $table->string('network_restriction')->nullable();
        
            // --- Status ---
            $table->boolean('is_covered')->default(true);
            $table->boolean('is_visible')->default(true);
        
            // --- Display ---
            $table->string('display_value')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
        
            // --- Audit ---
            $table->timestamps();
            $table->softDeletes();
        
            // --- Indexes & Uniques ---
            $table->unique(['plan_id', 'benefit_id'], 'unique_plan_benefit');
            $table->index(['plan_id', 'is_covered']);
            $table->index('parent_plan_benefit_id');
        
            // --- Foreign Keys (DEFINED LAST) ---
            $table->foreign('plan_id')
                ->references('id')
                ->on('med_plans')
                ->cascadeOnDelete();
        
            $table->foreign('benefit_id')
                ->references('id')
                ->on('med_benefits')
                ->cascadeOnDelete();
        
            $table->foreign('parent_plan_benefit_id')
                ->references('id')
                ->on('med_plan_benefits')
                ->nullOnDelete();
        });
        

        // ======================================================================
        // 2. PLAN BENEFIT MEMBER LIMITS - Different limits per member type/age
        // ======================================================================
        // If no record exists for a member type, use the plan_benefit default
        
        Schema::create('med_plan_benefit_limits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            $table->uuid('plan_benefit_id');
            $table->foreign('plan_benefit_id')
                  ->references('id')
                  ->on('med_plan_benefits')
                  ->cascadeOnDelete();
            
            // --- Member Type ---
            $table->string('member_type');                   // principal, spouse, child, parent
            
            // --- Age Band (optional) ---
            $table->unsignedTinyInteger('min_age')->default(0);
            $table->unsignedTinyInteger('max_age')->default(100);
            
            // --- Limits for this combination ---
            $table->decimal('limit_amount', 15, 2)->nullable();
            $table->unsignedInteger('limit_count')->nullable();
            $table->unsignedInteger('limit_days')->nullable();
            
            // --- Display ---
            $table->string('display_value')->nullable();
            
            // --- Unique per member_type + age band ---
            $table->unique(
                ['plan_benefit_id', 'member_type', 'min_age', 'max_age'],
                'unique_member_age_limit'
            );
            
            $table->timestamps();
        });

        // ======================================================================
        // 3. PLAN EXCLUSIONS - What's NOT covered
        // ======================================================================
        
        Schema::create('med_plan_exclusions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            $table->uuid('plan_id');
            $table->foreign('plan_id')
                  ->references('id')
                  ->on('med_plans')
                  ->cascadeOnDelete();
            
            // --- Optional: Link to specific benefit ---
            // NULL = general exclusion for the plan
            $table->uuid('benefit_id')->nullable();
            $table->foreign('benefit_id')
                  ->references('id')
                  ->on('med_benefits')
                  ->nullOnDelete();
            
            // --- Exclusion Details ---
            $table->string('code')->index();                 // EXC-COSM-001
            $table->string('name');                          // "Cosmetic Surgery"
            $table->text('description')->nullable();
            
            // --- Exclusion Type ---
            $table->string('exclusion_type');
            /*
                absolute: Never covered under any circumstances
                conditional: May be covered if conditions met
                time_limited: Excluded for X months, then covered
                pre_existing: Related to pre-existing conditions
            */
            
            $table->json('conditions')->nullable();          // For conditional exclusions
            $table->unsignedInteger('exclusion_period_days')->nullable(); // For time_limited
            
            // --- Display ---
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            $table->softDeletes();
        });

        // ======================================================================
        // 4. PLAN WAITING PERIODS - Detailed waiting period config
        // ======================================================================
        
        Schema::create('med_plan_waiting_periods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            $table->uuid('plan_id');
            $table->foreign('plan_id')
                  ->references('id')
                  ->on('med_plans')
                  ->cascadeOnDelete();
            
            // --- Optional: Link to specific benefit ---
            $table->uuid('benefit_id')->nullable();
            $table->foreign('benefit_id')
                  ->references('id')
                  ->on('med_benefits')
                  ->nullOnDelete();
            
            // --- Waiting Period Definition ---
            $table->string('waiting_type');                  // general, maternity, pre_existing, chronic
            $table->string('name');                          // "Maternity Waiting Period"
            $table->unsignedInteger('waiting_days');         // 365
            
            // --- Applicability ---
            $table->json('applies_to_member_types')->nullable(); // ["spouse", "principal"] or NULL=all
            $table->boolean('applies_to_new_members')->default(true);
            $table->boolean('applies_to_upgrades')->default(false);
            
            // --- Waiver Rules ---
            $table->boolean('can_be_waived')->default(false);
            $table->text('waiver_conditions')->nullable();   // "With proof of prior continuous coverage"
            
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            
            $table->index(['plan_id', 'waiting_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('med_plan_waiting_periods');
        Schema::dropIfExists('med_plan_exclusions');
        Schema::dropIfExists('med_plan_benefit_limits');
        Schema::dropIfExists('med_plan_benefits');
    }
};