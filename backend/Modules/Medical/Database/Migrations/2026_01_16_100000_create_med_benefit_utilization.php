<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration to create benefit utilization tracking tables.
 * 
 * This tracks how much of each benefit a member has used within a policy period.
 * When a claim is approved, the utilization is updated.
 * When a policy renews, utilization resets for the new period.
 */
return new class extends Migration
{
    public function up(): void
    {
        // =====================================================================
        // MEMBER BENEFIT UTILIZATION
        // Tracks usage of benefits per member per policy period
        // =====================================================================
        Schema::create('med_member_benefit_utilization', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // References
            $table->foreignUuid('member_id')->constrained('med_members')->cascadeOnDelete();
            $table->foreignUuid('policy_id')->constrained('med_policies')->cascadeOnDelete();
            $table->foreignUuid('benefit_id')->constrained('med_benefits')->cascadeOnDelete();
            $table->foreignUuid('plan_benefit_id')->nullable()->constrained('med_plan_benefits')->nullOnDelete();
            
            // Period tracking (matches policy period)
            $table->date('period_start');
            $table->date('period_end');
            
            // Limit configuration (captured from plan at period start)
            $table->string('limit_type', 20)->default('monetary'); // monetary, count, days, unlimited
            $table->string('limit_frequency', 20)->default('per_annum'); // per_annum, per_visit, per_claim, lifetime
            $table->string('limit_basis', 20)->default('per_member'); // per_member, per_family
            
            // Limits (captured from plan benefit configuration)
            $table->decimal('limit_amount', 15, 2)->nullable(); // For monetary limits
            $table->integer('limit_count')->nullable(); // For count-based limits (visits, procedures)
            $table->integer('limit_days')->nullable(); // For day-based limits (hospital days)
            
            // Usage tracking
            $table->decimal('used_amount', 15, 2)->default(0);
            $table->integer('used_count')->default(0);
            $table->integer('used_days')->default(0);
            $table->integer('claims_count')->default(0); // Number of claims against this benefit
            
            // Computed remaining (for quick queries)
            $table->decimal('remaining_amount', 15, 2)->nullable();
            $table->integer('remaining_count')->nullable();
            $table->integer('remaining_days')->nullable();
            
            // Status
            $table->boolean('is_exhausted')->default(false);
            $table->timestamp('exhausted_at')->nullable();
            
            // Audit
            $table->timestamp('last_claim_at')->nullable();
            $table->timestamps();
            
            // Indexes for fast lookups
            // Indexes for fast lookups
            $table->unique(
                ['member_id', 'benefit_id', 'period_start'],
                'member_benefit_period_unique'
            );

            $table->index(
                ['policy_id', 'period_start', 'period_end'],
                'idx_mmbu_policy_period'
            );

            $table->index(
                ['member_id', 'is_exhausted'],
                'idx_mmbu_member_exhausted'
            );

            $table->index('benefit_id', 'idx_mmbu_benefit');

        });

        // =====================================================================
        // BENEFIT UTILIZATION HISTORY
        // Tracks each change to utilization (for audit trail)
        // =====================================================================
        Schema::create('med_benefit_utilization_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            $table->foreignUuid('utilization_id')
                ->constrained('med_member_benefit_utilization')
                ->cascadeOnDelete();
            $table->foreignUuid('claim_id')->nullable()->constrained('med_claims')->nullOnDelete();
            $table->foreignUuid('claim_line_id')->nullable()->constrained('med_claim_lines')->nullOnDelete();
            
            // Transaction type
            $table->string('transaction_type', 20); // claim_approved, claim_reversed, manual_adjustment, period_reset
            
            // Amount changed
            $table->decimal('amount_change', 15, 2)->default(0);
            $table->integer('count_change')->default(0);
            $table->integer('days_change')->default(0);
            
            // Balance after transaction
            $table->decimal('balance_amount', 15, 2)->nullable();
            $table->integer('balance_count')->nullable();
            $table->integer('balance_days')->nullable();
            
            // Metadata
            $table->string('notes')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            
            $table->index('claim_id');
            $table->index(['utilization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('med_benefit_utilization_history');
        Schema::dropIfExists('med_member_benefit_utilization');
    }
};
