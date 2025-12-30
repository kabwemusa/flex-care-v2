<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Claims Processing
     * 
     * Tables:
     * - med_claims: Main claims table
     * - med_claim_lines: Individual line items in a claim
     * - med_claim_documents: Supporting documents for claims
     * - med_claim_notes: Audit trail / notes on claims
     */
    public function up(): void
    {
        // =====================================================================
        // CLAIMS
        // =====================================================================
        Schema::create('med_claims', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('claim_number', 30)->unique();
            
            // Links
            $table->foreignUuid('policy_id')->constrained('med_policies')->restrictOnDelete();
            $table->foreignUuid('member_id')->constrained('med_members')->restrictOnDelete();
            
            // Claim Type & Source
            $table->string('claim_type', 20); // in_patient, out_patient, dental, optical, maternity, chronic
            $table->string('submission_type', 20); // provider, member, employer
            $table->string('submission_channel', 20)->nullable(); // portal, email, paper, api
            
            // Service Details
            $table->date('service_date');
            $table->date('service_end_date')->nullable(); // For in-patient
            $table->date('admission_date')->nullable();
            $table->date('discharge_date')->nullable();
            $table->integer('days_admitted')->nullable();
            
            // Provider Information
            $table->uuid('provider_id')->nullable(); // FK to providers table if exists
            $table->string('provider_name')->nullable();
            $table->string('provider_type', 30)->nullable(); // hospital, clinic, pharmacy, lab, optical
            $table->string('provider_invoice_number', 50)->nullable();
            
            // Diagnosis
            $table->string('primary_diagnosis')->nullable();
            $table->string('primary_icd_code', 20)->nullable();
            $table->json('secondary_diagnoses')->nullable();
            $table->text('diagnosis_notes')->nullable();
            
            // Amounts
            $table->string('currency', 3)->default('ZMW');
            $table->decimal('claimed_amount', 15, 2);
            $table->decimal('approved_amount', 15, 2)->default(0);
            $table->decimal('copay_amount', 15, 2)->default(0);
            $table->decimal('deductible_amount', 15, 2)->default(0);
            $table->decimal('excess_amount', 15, 2)->default(0); // Amount beyond limit
            $table->decimal('excluded_amount', 15, 2)->default(0);
            $table->decimal('payable_amount', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            
            // Payment Details
            $table->string('payment_method', 20)->nullable(); // eft, cheque, mobile_money
            $table->string('payment_reference', 50)->nullable();
            $table->date('payment_date')->nullable();
            $table->string('paid_to', 20)->nullable(); // provider, member
            $table->string('bank_name', 100)->nullable();
            $table->string('account_number', 50)->nullable();
            
            // Pre-authorization
            $table->boolean('requires_preauth')->default(false);
            $table->string('preauth_number', 30)->nullable();
            $table->string('preauth_status', 20)->nullable(); // pending, approved, denied
            $table->decimal('preauth_amount', 15, 2)->nullable();
            $table->timestamp('preauth_at')->nullable();
            $table->uuid('preauth_by')->nullable();
            
            // Status & Workflow
            $table->string('status', 20)->default('submitted'); // submitted, pending_documents, in_review, pending_approval, approved, partially_approved, rejected, paid, closed
            $table->string('substatus', 30)->nullable();
            $table->integer('priority')->default(5); // 1-10, lower = higher priority
            
            // Assignment
            $table->uuid('assigned_to')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->uuid('processed_by')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            
            // Rejection
            $table->string('rejection_reason', 100)->nullable();
            $table->text('rejection_notes')->nullable();
            $table->uuid('rejected_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            
            // Turnaround Time
            $table->timestamp('received_at')->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('tat_days')->nullable(); // Calculated TAT
            
            // Fraud & Audit
            $table->integer('fraud_score')->nullable(); // 0-100
            $table->json('fraud_flags')->nullable();
            $table->boolean('is_flagged')->default(false);
            $table->string('flag_reason', 100)->nullable();
            $table->boolean('requires_audit')->default(false);
            $table->uuid('audited_by')->nullable();
            $table->timestamp('audited_at')->nullable();
            
            $table->json('metadata')->nullable();
            $table->text('internal_notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('claim_number');
            $table->index('status');
            $table->index('claim_type');
            $table->index('policy_id');
            $table->index('member_id');
            $table->index('service_date');
            $table->index('assigned_to');
            $table->index(['status', 'assigned_to']);
        });

        // =====================================================================
        // CLAIM LINES
        // =====================================================================
        Schema::create('med_claim_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('claim_id')->constrained('med_claims')->cascadeOnDelete();
            $table->foreignUuid('benefit_id')->nullable()->constrained('med_benefits')->nullOnDelete();
            
            $table->integer('line_number')->default(1);
            $table->string('service_code', 30)->nullable(); // CPT, procedure code
            $table->string('service_description');
            $table->date('service_date');
            
            // Quantities
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('unit', 20)->nullable(); // days, units, visits
            $table->decimal('unit_price', 15, 2)->default(0);
            
            // Amounts
            $table->decimal('claimed_amount', 15, 2);
            $table->decimal('approved_amount', 15, 2)->default(0);
            $table->decimal('copay_amount', 15, 2)->default(0);
            $table->decimal('deductible_amount', 15, 2)->default(0);
            $table->decimal('excess_amount', 15, 2)->default(0);
            $table->decimal('excluded_amount', 15, 2)->default(0);
            $table->decimal('payable_amount', 15, 2)->default(0);
            
            // Adjudication
            $table->string('status', 20)->default('pending'); // pending, approved, partially_approved, rejected
            $table->string('rejection_reason', 100)->nullable();
            $table->text('adjudication_notes')->nullable();
            
            // Benefit Tracking
            $table->decimal('benefit_limit', 15, 2)->nullable();
            $table->decimal('benefit_used_before', 15, 2)->nullable();
            $table->decimal('benefit_remaining', 15, 2)->nullable();
            
            // Tariff
            $table->decimal('tariff_amount', 15, 2)->nullable();
            $table->string('tariff_code', 30)->nullable();
            
            $table->timestamps();
            
            $table->index('claim_id');
            $table->index('benefit_id');
        });

        // =====================================================================
        // CLAIM DOCUMENTS
        // =====================================================================
        Schema::create('med_claim_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('claim_id')->constrained('med_claims')->cascadeOnDelete();
            
            $table->string('document_type', 30); // invoice, receipt, prescription, medical_report, lab_result, referral, preauth, discharge_summary, id_copy
            $table->string('title');
            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime_type', 50)->nullable();
            $table->integer('file_size')->nullable();
            
            $table->boolean('is_required')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->uuid('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            
            $table->uuid('uploaded_by')->nullable();
            $table->string('upload_source', 20)->nullable(); // portal, email, scan
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            
            $table->index(['claim_id', 'document_type']);
        });

        // =====================================================================
        // CLAIM NOTES / AUDIT TRAIL
        // =====================================================================
        Schema::create('med_claim_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('claim_id')->constrained('med_claims')->cascadeOnDelete();
            
            $table->string('note_type', 20); // comment, status_change, assignment, escalation, query, response, system
            $table->text('content');
            
            $table->string('old_status', 20)->nullable();
            $table->string('new_status', 20)->nullable();
            $table->string('old_assignee')->nullable();
            $table->string('new_assignee')->nullable();
            
            $table->boolean('is_internal')->default(true); // Internal notes vs customer-facing
            $table->boolean('is_system')->default(false);
            
            $table->uuid('created_by')->nullable();
            $table->timestamps();
            
            $table->index('claim_id');
            $table->index('note_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('med_claim_notes');
        Schema::dropIfExists('med_claim_documents');
        Schema::dropIfExists('med_claim_lines');
        Schema::dropIfExists('med_claims');
    }
};