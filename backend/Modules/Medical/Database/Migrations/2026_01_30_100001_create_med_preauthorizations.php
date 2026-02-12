<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // =========================================================================
        // PRE-AUTHORIZATIONS
        // =========================================================================
        Schema::create('med_preauthorizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('preauth_number', 30)->unique();

            // Related Entities
            $table->uuid('provider_id')->nullable();
            $table->uuid('policy_id');
            $table->uuid('member_id');

            // Service Details
            $table->date('requested_service_date');
            $table->date('service_end_date')->nullable();
            $table->date('admission_date')->nullable();
            $table->date('discharge_date')->nullable();
            $table->integer('estimated_days')->nullable();

            // Diagnosis
            $table->string('primary_diagnosis', 255)->nullable();
            $table->string('primary_icd_code', 10)->nullable();
            $table->json('secondary_diagnoses')->nullable();
            $table->text('treatment_plan')->nullable();
            $table->text('clinical_notes')->nullable();

            // Provider Details (if not registered provider)
            $table->string('facility_name', 255)->nullable();
            $table->string('facility_type', 30)->nullable();
            $table->string('attending_doctor', 255)->nullable();
            $table->string('doctor_license', 50)->nullable();

            // Financial
            $table->string('currency', 3)->default('ZMW');
            $table->decimal('requested_amount', 15, 2);
            $table->decimal('approved_amount', 15, 2)->default(0);
            $table->decimal('reserved_amount', 15, 2)->default(0); // amount held against benefits

            // Primary Benefit (main benefit for pre-auth)
            $table->uuid('primary_benefit_id')->nullable();
            $table->decimal('benefit_balance_before', 15, 2)->nullable();

            // Status & Workflow
            $table->string('status', 30)->default('pending');
            // pending, auto_approved, under_review, approved, partially_approved, rejected, expired, cancelled, claimed
            $table->string('priority', 20)->default('standard'); // standard, urgent, emergency
            $table->boolean('requires_manual_review')->default(false);
            $table->text('review_reason')->nullable();

            // Decision
            $table->timestamp('decision_at')->nullable();
            $table->uuid('decision_by')->nullable();
            $table->text('decision_notes')->nullable();
            $table->string('rejection_reason', 50)->nullable();
            $table->text('rejection_details')->nullable();

            // Validity
            $table->integer('validity_hours')->default(72); // how long the preauth is valid
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_expired')->default(false);

            // Extensions
            $table->integer('extension_count')->default(0);
            $table->timestamp('last_extended_at')->nullable();
            $table->uuid('last_extended_by')->nullable();

            // Fraud Detection
            $table->integer('fraud_score')->default(0);
            $table->json('fraud_flags')->nullable();
            $table->timestamp('fraud_checked_at')->nullable();
            $table->string('fraud_check_version', 20)->nullable();

            // Linked Claim (when pre-auth is used)
            $table->uuid('claim_id')->nullable();
            $table->timestamp('claimed_at')->nullable();

            // Request Metadata
            $table->timestamp('requested_at');
            $table->string('requested_by_type', 20); // provider, member, staff
            $table->uuid('requested_by_id')->nullable();
            $table->string('submission_channel', 20)->default('portal'); // portal, api, phone, email

            // Documents
            $table->boolean('has_supporting_documents')->default(false);

            // Metadata
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Foreign Keys
            $table->foreign('provider_id')
                ->references('id')
                ->on('med_providers')
                ->onDelete('set null');

            $table->foreign('policy_id')
                ->references('id')
                ->on('med_policies')
                ->onDelete('cascade');

            $table->foreign('member_id')
                ->references('id')
                ->on('med_members')
                ->onDelete('cascade');

            $table->foreign('primary_benefit_id')
                ->references('id')
                ->on('med_benefits')
                ->onDelete('set null');

            // Indexes
            $table->index(['member_id', 'status']);
            $table->index(['provider_id', 'requested_at']);
            $table->index(['policy_id', 'status']);
            $table->index(['expires_at', 'status']);
            $table->index('status');
            $table->index('priority');
            $table->index('claim_id');
        });

        // =========================================================================
        // PRE-AUTHORIZATION LINES (Individual services/procedures)
        // =========================================================================
        Schema::create('med_preauthorization_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('preauthorization_id');
            $table->integer('line_number');

            // Service Details
            $table->uuid('benefit_id')->nullable();
            $table->string('service_code', 30)->nullable();
            $table->string('service_description', 255);
            $table->string('service_type', 50)->nullable(); // consultation, procedure, admission, medication, etc.

            // Quantities
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('unit', 20)->nullable(); // visit, day, procedure, item

            // Financial
            $table->decimal('unit_price', 15, 2)->nullable();
            $table->decimal('requested_amount', 15, 2);
            $table->decimal('approved_amount', 15, 2)->default(0);
            $table->decimal('reserved_amount', 15, 2)->default(0);

            // Benefit Info (captured at approval)
            $table->decimal('benefit_limit', 15, 2)->nullable();
            $table->decimal('benefit_used_before', 15, 2)->nullable();
            $table->decimal('benefit_available', 15, 2)->nullable();

            // Status
            $table->string('status', 20)->default('pending'); // pending, approved, partially_approved, rejected
            $table->string('rejection_reason', 50)->nullable();
            $table->text('notes')->nullable();

            // Decision
            $table->timestamp('decided_at')->nullable();
            $table->uuid('decided_by')->nullable();

            $table->timestamps();

            $table->foreign('preauthorization_id')
                ->references('id')
                ->on('med_preauthorizations')
                ->onDelete('cascade');

            $table->foreign('benefit_id')
                ->references('id')
                ->on('med_benefits')
                ->onDelete('set null');

            $table->index('preauthorization_id');
            $table->index('benefit_id');
            $table->unique(['preauthorization_id', 'line_number']);
        });

        // =========================================================================
        // PRE-AUTHORIZATION DOCUMENTS
        // =========================================================================
        Schema::create('med_preauthorization_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('preauthorization_id');

            $table->string('document_type', 50); // referral, medical_report, lab_result, prescription, other
            $table->string('file_path', 500);
            $table->string('file_name', 255);
            $table->string('mime_type', 100)->nullable();
            $table->integer('file_size')->nullable();

            // Verification
            $table->boolean('is_verified')->default(false);
            $table->uuid('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_notes')->nullable();

            // Upload Info
            $table->uuid('uploaded_by')->nullable();
            $table->string('upload_source', 20)->default('portal'); // portal, api, email

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('preauthorization_id')
                ->references('id')
                ->on('med_preauthorizations')
                ->onDelete('cascade');

            $table->index('preauthorization_id');
        });

        // =========================================================================
        // PRE-AUTHORIZATION NOTES/AUDIT TRAIL
        // =========================================================================
        Schema::create('med_preauthorization_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('preauthorization_id');

            $table->string('note_type', 30); // comment, status_change, escalation, query, response, system
            $table->text('content');
            $table->string('previous_status', 30)->nullable();
            $table->string('new_status', 30)->nullable();

            $table->boolean('is_internal')->default(true);
            $table->boolean('visible_to_provider')->default(false);

            $table->uuid('created_by')->nullable();
            $table->string('created_by_type', 20)->nullable(); // staff, system, provider

            $table->timestamp('created_at');

            $table->foreign('preauthorization_id')
                ->references('id')
                ->on('med_preauthorizations')
                ->onDelete('cascade');

            $table->index(['preauthorization_id', 'created_at']);
        });

        // =========================================================================
        // BENEFIT RESERVATIONS (Tracks reserved amounts for pre-auths)
        // =========================================================================
        Schema::create('med_benefit_reservations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('member_benefit_utilization_id');
            $table->uuid('preauthorization_id');
            $table->uuid('preauthorization_line_id')->nullable();

            // Reserved Amounts
            $table->decimal('reserved_amount', 15, 2)->default(0);
            $table->integer('reserved_count')->default(0);
            $table->integer('reserved_days')->default(0);

            // Timing
            $table->timestamp('reserved_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('claimed_at')->nullable();

            // Status
            $table->string('status', 20)->default('active'); // active, claimed, released, expired

            // Release Info
            $table->string('release_reason', 50)->nullable(); // expired, cancelled, claimed, manual
            $table->uuid('released_by')->nullable();
            $table->text('notes')->nullable();

            // Linked Claim
            $table->uuid('claim_id')->nullable();
            $table->uuid('claim_line_id')->nullable();

            $table->timestamps();

            $table->foreign('member_benefit_utilization_id')
                ->references('id')
                ->on('med_member_benefit_utilization')
                ->onDelete('cascade');

            $table->foreign('preauthorization_id')
                ->references('id')
                ->on('med_preauthorizations')
                ->onDelete('cascade');

            $table->index(['member_benefit_utilization_id', 'status']);
            $table->index(['preauthorization_id']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('med_benefit_reservations');
        Schema::dropIfExists('med_preauthorization_notes');
        Schema::dropIfExists('med_preauthorization_documents');
        Schema::dropIfExists('med_preauthorization_lines');
        Schema::dropIfExists('med_preauthorizations');
    }
};
