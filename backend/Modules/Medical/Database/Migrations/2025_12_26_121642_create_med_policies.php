<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Policies (Contracts)
     * 
     * A Policy is created ONLY when an Application is approved, accepted, and paid.
     * It represents an active insurance contract.
     * 
     * Key principle: Policy is the OUTPUT of the sales process, not the starting point.
     * 
     * Tables:
     * - med_policies: Insurance contracts
     * - med_policy_addons: Active addons on the policy
     * - med_policy_documents: Policy documents (certificates, schedules, endorsements)
     */
    public function up(): void
    {
        // =====================================================================
        // POLICIES
        // =====================================================================
        Schema::create('med_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('policy_number', 30)->unique(); // POL-2025-000001
            
            // Source Application (always required for new business)
            $table->uuid('application_id')->nullable();
            $table->foreign('application_id')->references('id')->on('med_applications')->nullOnDelete();
            
            // Links to Product Configuration
            $table->foreignUuid('scheme_id')->constrained('med_schemes')->restrictOnDelete();
            $table->foreignUuid('plan_id')->constrained('med_plans')->restrictOnDelete();
            $table->foreignUuid('rate_card_id')->nullable()->constrained('med_rate_cards')->nullOnDelete();
            
            // Policy Type & Holder
            $table->string('policy_type', 20); // individual, family, corporate, sme
            
            // Corporate Group (only for corporate/sme)
            $table->uuid('group_id')->nullable();
            $table->foreign('group_id')->references('id')->on('med_corporate_groups')->nullOnDelete();
            
            // Principal Member (set after members are created)
            // For individual/family - the main policyholder
            // For corporate - may be null or set to a designated principal
            $table->uuid('principal_member_id')->nullable();
            
            // Policy Holder Contact (denormalized for quick access)
            $table->string('holder_name')->nullable(); // Person or Company name
            $table->string('holder_email')->nullable();
            $table->string('holder_phone', 30)->nullable();
            
            // Policy Period
            $table->date('inception_date');
            $table->date('expiry_date');
            $table->date('renewal_date')->nullable();
            $table->integer('policy_term_months')->default(12);
            $table->boolean('is_auto_renew')->default(true);
            
            // Premium (as of policy issue - snapshot from application)
            $table->string('currency', 3)->default('ZMW');
            $table->string('billing_frequency', 20); // monthly, quarterly, semi_annual, annual
            $table->decimal('base_premium', 15, 2)->default(0);
            $table->decimal('addon_premium', 15, 2)->default(0);
            $table->decimal('loading_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total_premium', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('gross_premium', 15, 2)->default(0);
            
            // Member Counts (updated as members are added/removed via endorsements)
            $table->integer('member_count')->default(0);
            $table->integer('principal_count')->default(0);
            $table->integer('dependent_count')->default(0);
            
            // Policy Status (post-issue lifecycle)
            $table->string('status', 20)->default('active');
            // active, suspended, lapsed, cancelled, expired, renewed
            // Note: No 'draft' or 'pending' - those are Application statuses
            
            // Underwriting (copied from application for reference)
            $table->string('underwriting_status', 20)->default('approved');
            $table->text('underwriting_notes')->nullable();
            $table->uuid('underwritten_by')->nullable();
            $table->timestamp('underwritten_at')->nullable();
            
            // Suspension
            $table->date('suspended_at')->nullable();
            $table->string('suspension_reason', 100)->nullable();
            
            // Cancellation
            $table->date('cancelled_at')->nullable();
            $table->string('cancellation_reason', 50)->nullable();
            $table->text('cancellation_notes')->nullable();
            $table->uuid('cancelled_by')->nullable();
            
            // Renewal Tracking
            $table->uuid('previous_policy_id')->nullable(); // Renewed from
            $table->uuid('renewed_to_policy_id')->nullable(); // Renewed to
            $table->integer('renewal_count')->default(0);
            
            // Sales & Commission (copied from application)
            $table->string('source', 30)->nullable();
            $table->uuid('sales_agent_id')->nullable();
            $table->uuid('broker_id')->nullable();
            $table->decimal('commission_rate', 5, 2)->nullable();
            $table->decimal('commission_amount', 15, 2)->nullable();
            
            // Promo & Discounts (copied from application)
            $table->foreignUuid('promo_code_id')->nullable()->constrained('med_promo_codes')->nullOnDelete();
            $table->json('applied_discounts')->nullable();
            $table->json('applied_loadings')->nullable();
            
            // Issue Information
            $table->timestamp('issued_at')->nullable();
            $table->uuid('issued_by')->nullable();
            
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('policy_number');
            $table->index('status');
            $table->index('policy_type');
            $table->index(['inception_date', 'expiry_date']);
            $table->index('group_id');
            $table->index('scheme_id');
            $table->index('plan_id');
            $table->index('application_id');
        });

        // Self-referencing FKs for renewal chain
        Schema::table('med_policies', function (Blueprint $table) {
            $table->foreign('previous_policy_id')->references('id')->on('med_policies')->nullOnDelete();
            $table->foreign('renewed_to_policy_id')->references('id')->on('med_policies')->nullOnDelete();
        });

        // Update applications table to link converted policy
        Schema::table('med_applications', function (Blueprint $table) {
            $table->foreign('converted_policy_id')->references('id')->on('med_policies')->nullOnDelete();
            $table->foreign('renewal_of_policy_id')->references('id')->on('med_policies')->nullOnDelete();
        });

        // =====================================================================
        // POLICY ADDONS
        // =====================================================================
        Schema::create('med_policy_addons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('policy_id')->constrained('med_policies')->cascadeOnDelete();
            $table->foreignUuid('addon_id')->constrained('med_addons')->restrictOnDelete();
            $table->foreignUuid('addon_rate_id')->nullable()->constrained('med_addon_rates')->nullOnDelete();
            
            $table->decimal('premium', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            
            $table->timestamps();
            
            $table->unique(['policy_id', 'addon_id']);
        });

        // =====================================================================
        // POLICY DOCUMENTS
        // =====================================================================
        Schema::create('med_policy_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('policy_id')->constrained('med_policies')->cascadeOnDelete();
            
            $table->string('document_type', 30); // certificate, schedule, endorsement, terms, welcome_letter
            $table->string('title');
            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime_type', 50)->nullable();
            $table->integer('file_size')->nullable();
            
            $table->string('version', 10)->default('1.0');
            $table->date('issue_date')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            
            $table->uuid('uploaded_by')->nullable();
            $table->uuid('generated_by')->nullable();
            $table->boolean('is_system_generated')->default(false);
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            
            $table->index(['policy_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('med_policy_documents');
        Schema::dropIfExists('med_policy_addons');
        
        Schema::table('med_applications', function (Blueprint $table) {
            $table->dropForeign(['converted_policy_id']);
            $table->dropForeign(['renewal_of_policy_id']);
        });
        
        Schema::table('med_policies', function (Blueprint $table) {
            $table->dropForeign(['previous_policy_id']);
            $table->dropForeign(['renewed_to_policy_id']);
        });
        
        Schema::dropIfExists('med_policies');
    }
};