<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Applications (Quotes/Proposals)
     * 
     * THIS IS THE STARTING POINT OF THE SALES FLOW.
     * 
     * An Application captures:
     * - Customer/Group information
     * - Product selection (scheme, plan)
     * - All members to be covered
     * - Premium calculations
     * - Underwriting decisions
     * 
     * Flow:
     * 1. Application created (status: draft)
     * 2. Members added to application
     * 3. Premium calculated (status: quoted)
     * 4. Submitted for underwriting (status: submitted)
     * 5. Underwriter reviews, adds loadings/exclusions (status: underwriting)
     * 6. Approved/Declined (status: approved/declined)
     * 7. Customer accepts terms (status: accepted)
     * 8. Payment received
     * 9. Convert to Policy (status: converted) -> Creates Policy + Members
     * 
     * Tables:
     * - med_applications: The main application/quote record
     * - med_application_members: Members in the application
     * - med_application_addons: Selected addons
     * - med_application_documents: Supporting documents
     */
    public function up(): void
    {
        // =====================================================================
        // APPLICATIONS (QUOTES/PROPOSALS)
        // =====================================================================
        Schema::create('med_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('application_number', 30)->unique(); // APP-2025-000001
            
            // Application Type
            $table->string('application_type', 20); // new_business, renewal, addition
            $table->string('policy_type', 20); // individual, family, corporate, sme
            
            // Product Selection
            $table->foreignUuid('scheme_id')->constrained('med_schemes')->restrictOnDelete();
            $table->foreignUuid('plan_id')->constrained('med_plans')->restrictOnDelete();
            $table->foreignUuid('rate_card_id')->nullable()->constrained('med_rate_cards')->nullOnDelete();
            
            // Corporate (optional - only for corporate/sme types)
            $table->uuid('group_id')->nullable();
            $table->foreign('group_id')->references('id')->on('med_corporate_groups')->nullOnDelete();
            
            // For Renewals - link to existing policy
            $table->uuid('renewal_of_policy_id')->nullable();
            
            // Applicant Contact (for individual/family - the principal's contact)
            // For corporate - this is the HR/contact person
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 30)->nullable();
            
            // Coverage Period
            $table->date('proposed_start_date');
            $table->date('proposed_end_date')->nullable();
            $table->integer('policy_term_months')->default(12);
            $table->string('billing_frequency', 20)->default('monthly');
            
            // Premium Calculation (updated as members are added)
            $table->string('currency', 3)->default('ZMW');
            $table->decimal('base_premium', 15, 2)->default(0);
            $table->decimal('addon_premium', 15, 2)->default(0);
            $table->decimal('loading_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total_premium', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('gross_premium', 15, 2)->default(0);
            
            // Member Summary (denormalized for quick access)
            $table->integer('member_count')->default(0);
            $table->integer('principal_count')->default(0);
            $table->integer('dependent_count')->default(0);
            
            // Discounts & Promo
            $table->foreignUuid('promo_code_id')->nullable()->constrained('med_promo_codes')->nullOnDelete();
            $table->json('applied_discounts')->nullable();
            
            // Application Status
            $table->string('status', 20)->default('draft');
            // draft -> quoted -> submitted -> underwriting -> approved/declined/referred -> accepted -> converted
            // Can also be: expired, cancelled, on_hold
            
            // Underwriting
            $table->string('underwriting_status', 20)->nullable(); // pending, in_progress, approved, declined, referred
            $table->text('underwriting_notes')->nullable();
            $table->uuid('underwriter_id')->nullable();
            $table->timestamp('underwriting_started_at')->nullable();
            $table->timestamp('underwriting_completed_at')->nullable();
            $table->json('underwriting_decisions')->nullable(); // Summary of all UW decisions
            
            // Customer Acceptance
            $table->timestamp('quoted_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->string('acceptance_reference')->nullable(); // Customer's acceptance confirmation
            
            // Conversion to Policy
            $table->uuid('converted_policy_id')->nullable(); // Set when converted
            $table->timestamp('converted_at')->nullable();
            $table->uuid('converted_by')->nullable();
            
            // Validity
            $table->date('quote_valid_until')->nullable();
            $table->date('expired_at')->nullable();
            
            // Sales Attribution
            $table->string('source', 30)->nullable(); // online, walk_in, agent, broker, referral
            $table->uuid('sales_agent_id')->nullable();
            $table->uuid('broker_id')->nullable();
            $table->decimal('commission_rate', 5, 2)->nullable();
            
            // Metadata
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('application_number');
            $table->index('status');
            $table->index('policy_type');
            $table->index('group_id');
            $table->index('scheme_id');
            $table->index('plan_id');
            $table->index(['status', 'quote_valid_until']);
        });

        // =====================================================================
        // APPLICATION MEMBERS
        // These are the proposed members - will become med_members after conversion
        // =====================================================================
        Schema::create('med_application_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('application_id')->constrained('med_applications')->cascadeOnDelete();
            
            // Member Type & Relationship
            $table->string('member_type', 20); // principal, spouse, child, parent
            $table->uuid('principal_member_id')->nullable(); // For dependents - links to principal in same application
            $table->string('relationship', 30)->nullable(); // wife, husband, son, daughter, father, mother
            
            // Personal Information
            $table->string('title', 10)->nullable();
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->date('date_of_birth');
            $table->string('gender', 1); // M, F
            $table->string('marital_status', 20)->nullable();
            
            // Identification
            $table->string('national_id', 30)->nullable();
            $table->string('passport_number', 30)->nullable();
            
            // Contact
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('mobile', 30)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            
            // Employment (for corporate)
            $table->string('employee_number', 30)->nullable();
            $table->string('job_title', 100)->nullable();
            $table->string('department', 100)->nullable();
            $table->date('employment_date')->nullable();
            $table->decimal('salary', 15, 2)->nullable();
            $table->string('salary_band', 20)->nullable();
            
            // Premium Calculation
            $table->integer('age_at_inception')->nullable(); // Calculated from DOB
            $table->decimal('base_premium', 15, 2)->default(0);
            $table->decimal('loading_amount', 15, 2)->default(0);
            $table->decimal('total_premium', 15, 2)->default(0);
            
            // Medical Declaration (filled by applicant)
            $table->boolean('has_pre_existing_conditions')->default(false);
            $table->json('declared_conditions')->nullable();
            $table->text('medical_history_notes')->nullable();
            
            // Underwriting Decisions (filled by underwriter)
            $table->string('underwriting_status', 20)->default('pending'); // pending, approved, declined, terms
            $table->json('applied_loadings')->nullable(); // Loadings decided by UW
            $table->json('applied_exclusions')->nullable(); // Exclusions decided by UW
            $table->text('underwriting_notes')->nullable();
            $table->uuid('underwritten_by')->nullable();
            $table->timestamp('underwritten_at')->nullable();
            
            // After Conversion - links to actual member record
            $table->uuid('converted_member_id')->nullable();
            
            $table->boolean('is_active')->default(true); // Can be removed from application
            $table->timestamps();
            
            $table->index('application_id');
            $table->index('member_type');
            $table->index('principal_member_id');
        });

        // Self-referencing FK for dependents
        Schema::table('med_application_members', function (Blueprint $table) {
            $table->foreign('principal_member_id')
                  ->references('id')
                  ->on('med_application_members')
                  ->nullOnDelete();
        });

        // =====================================================================
        // APPLICATION ADDONS
        // =====================================================================
        Schema::create('med_application_addons', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('application_id')->constrained('med_applications')->cascadeOnDelete();
            $table->foreignUuid('addon_id')->constrained('med_addons')->restrictOnDelete();
            $table->foreignUuid('addon_rate_id')->nullable()->constrained('med_addon_rates')->nullOnDelete();
            
            $table->decimal('premium', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            
            $table->unique(['application_id', 'addon_id']);
        });

        // =====================================================================
        // APPLICATION DOCUMENTS
        // Supporting documents: ID copies, medical reports, census files, etc.
        // =====================================================================
        Schema::create('med_application_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('application_id')->constrained('med_applications')->cascadeOnDelete();
            $table->uuid('application_member_id')->nullable(); // If document is for specific member
            
            $table->string('document_type', 30); // id_copy, medical_report, census_file, declaration, passport, birth_certificate
            $table->string('title');
            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime_type', 50)->nullable();
            $table->integer('file_size')->nullable();
            
            $table->boolean('is_verified')->default(false);
            $table->uuid('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            
            $table->uuid('uploaded_by')->nullable();
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            
            $table->index(['application_id', 'document_type']);
        });

        // FK for application_member_id
        Schema::table('med_application_documents', function (Blueprint $table) {
            $table->foreign('application_member_id')
                  ->references('id')
                  ->on('med_application_members')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('med_application_documents', function (Blueprint $table) {
            $table->dropForeign(['application_member_id']);
        });
        Schema::dropIfExists('med_application_documents');
        Schema::dropIfExists('med_application_addons');
        
        Schema::table('med_application_members', function (Blueprint $table) {
            $table->dropForeign(['principal_member_id']);
        });
        Schema::dropIfExists('med_application_members');
        Schema::dropIfExists('med_applications');
    }
};