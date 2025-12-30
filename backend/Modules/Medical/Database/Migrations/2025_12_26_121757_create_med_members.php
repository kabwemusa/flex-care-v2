<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Members & Dependents
     * 
     * Tables:
     * - med_members: All covered individuals (principals and dependents)
     * - med_member_loadings: Medical loadings applied to specific members
     * - med_member_exclusions: Benefit exclusions for specific members
     * - med_member_documents: ID copies, medical reports, etc.
     */
    public function up(): void
    {
        // =====================================================================
        // MEMBERS
        // =====================================================================
        Schema::create('med_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('member_number', 30)->unique();
            $table->foreignUuid('policy_id')->constrained('med_policies')->cascadeOnDelete();
            
            // Member Type & Relationship
            $table->string('member_type', 20); // principal, spouse, child, parent
            $table->foreignUuid('principal_id')->nullable(); // FK to self - null for principals
            $table->string('relationship', 30)->nullable(); // spouse, son, daughter, father, mother
            
            // Personal Information
            $table->string('title', 10)->nullable(); // Mr, Mrs, Ms, Dr
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->date('date_of_birth');
            $table->string('gender', 1); // M, F
            $table->string('marital_status', 20)->nullable(); // single, married, divorced, widowed
            
            // Identification
            $table->string('national_id', 30)->nullable();
            $table->string('passport_number', 30)->nullable();
            $table->string('employee_number', 30)->nullable(); // For corporate members
            
            // Contact
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('mobile', 30)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('region_code', 20)->nullable();
            
            // Employment (for corporate members)
            $table->string('job_title', 100)->nullable();
            $table->string('department', 100)->nullable();
            $table->date('employment_date')->nullable();
            $table->decimal('salary', 15, 2)->nullable();
            $table->string('salary_band', 20)->nullable();
            
            // Coverage
            $table->date('cover_start_date');
            $table->date('cover_end_date')->nullable();
            $table->date('waiting_period_end_date')->nullable();
            $table->decimal('premium', 15, 2)->default(0);
            $table->decimal('loading_amount', 15, 2)->default(0);
            
            // Card
            $table->string('card_number', 30)->nullable();
            $table->date('card_issued_date')->nullable();
            $table->date('card_expiry_date')->nullable();
            $table->string('card_status', 20)->default('pending'); // pending, issued, active, blocked, expired
            
            // Status
            $table->string('status', 20)->default('pending'); // pending, active, suspended, terminated, deceased
            $table->date('status_changed_at')->nullable();
            $table->string('status_reason', 100)->nullable();
            
            // Termination
            $table->date('terminated_at')->nullable();
            $table->string('termination_reason', 50)->nullable();
            $table->text('termination_notes')->nullable();
            
            // Medical History (high-level flags)
            $table->boolean('has_pre_existing_conditions')->default(false);
            $table->boolean('is_chronic_patient')->default(false);
            $table->boolean('requires_special_underwriting')->default(false);
            $table->json('declared_conditions')->nullable();
            
            // Portal Access
            $table->boolean('has_portal_access')->default(false);
            $table->uuid('user_id')->nullable();
            
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('member_number');
            $table->index('policy_id');
            $table->index('principal_id');
            $table->index('member_type');
            $table->index('status');
            $table->index(['cover_start_date', 'cover_end_date']);
            $table->index('national_id');
            $table->index('card_number');
        });

        // Add self-referencing FK and update policy FK
        Schema::table('med_members', function (Blueprint $table) {
            $table->foreign('principal_id')->references('id')->on('med_members')->nullOnDelete();
        });

        // Now we can add the FK from policies to members
        Schema::table('med_policies', function (Blueprint $table) {
            $table->foreign('principal_member_id')->references('id')->on('med_members')->nullOnDelete();
        });

        // =====================================================================
        // MEMBER LOADINGS
        // =====================================================================
        Schema::create('med_member_loadings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('member_id')->constrained('med_members')->cascadeOnDelete();
            $table->foreignUuid('loading_rule_id')->nullable()->constrained('med_loading_rules')->nullOnDelete();
            
            $table->string('condition_name');
            $table->string('icd10_code', 20)->nullable();
            $table->string('loading_type', 20); // percentage, fixed, exclusion
            $table->decimal('loading_value', 10, 2)->nullable();
            $table->decimal('loading_amount', 15, 2)->default(0);
            
            $table->string('duration_type', 20); // permanent, time_limited, reviewable
            $table->integer('duration_months')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('review_date')->nullable();
            
            $table->string('status', 20)->default('active'); // active, expired, waived, removed
            $table->text('underwriting_notes')->nullable();
            $table->uuid('applied_by')->nullable();
            
            $table->timestamps();
            
            $table->index(['member_id', 'status']);
        });

        // =====================================================================
        // MEMBER EXCLUSIONS
        // =====================================================================
        Schema::create('med_member_exclusions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('member_id')->constrained('med_members')->cascadeOnDelete();
            $table->foreignUuid('benefit_id')->nullable()->constrained('med_benefits')->nullOnDelete();
            
            $table->string('exclusion_type', 20); // benefit, condition, body_part
            $table->string('exclusion_name');
            $table->text('description')->nullable();
            $table->string('icd10_codes', 255)->nullable(); // Comma-separated
            
            $table->date('start_date');
            $table->date('end_date')->nullable(); // Null = permanent
            $table->date('review_date')->nullable();
            
            $table->string('status', 20)->default('active'); // active, expired, waived, removed
            $table->text('underwriting_notes')->nullable();
            $table->uuid('applied_by')->nullable();
            
            $table->timestamps();
            
            $table->index(['member_id', 'status']);
            $table->index('benefit_id');
        });

        // =====================================================================
        // MEMBER DOCUMENTS
        // =====================================================================
        Schema::create('med_member_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('member_id')->constrained('med_members')->cascadeOnDelete();
            
            $table->string('document_type', 30); // id_copy, passport, birth_certificate, marriage_certificate, medical_report, declaration_form, photo
            $table->string('title');
            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime_type', 50)->nullable();
            $table->integer('file_size')->nullable();
            
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->uuid('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            
            $table->uuid('uploaded_by')->nullable();
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            
            $table->index(['member_id', 'document_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('med_member_documents');
        Schema::dropIfExists('med_member_exclusions');
        Schema::dropIfExists('med_member_loadings');
        
        Schema::table('med_policies', function (Blueprint $table) {
            $table->dropForeign(['principal_member_id']);
        });
        
        Schema::table('med_members', function (Blueprint $table) {
            $table->dropForeign(['principal_id']);
        });
        
        Schema::dropIfExists('med_members');
    }
};