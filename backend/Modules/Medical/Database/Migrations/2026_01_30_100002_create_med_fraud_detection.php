<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // =========================================================================
        // FRAUD RULES (Configurable fraud detection rules)
        // =========================================================================
        Schema::create('med_fraud_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('rule_code', 50)->unique();
            $table->string('name', 255);
            $table->text('description')->nullable();

            // Category
            $table->string('category', 30);
            // frequency, amount, pattern, relationship, timing, provider, service, member, geographic

            // Severity & Points
            $table->string('severity', 20)->default('medium'); // low, medium, high, critical
            $table->integer('points')->default(10); // points added to fraud score if triggered

            // Rule Configuration (JSON)
            $table->json('rule_logic');
            /*
             * Example rule_logic structures:
             *
             * Frequency rule:
             * {
             *   "period": "24_hours",
             *   "threshold": 5,
             *   "comparison": "greater_than"
             * }
             *
             * Amount rule:
             * {
             *   "metric": "member_average_claim",
             *   "multiplier": 3,
             *   "min_claims_for_avg": 5
             * }
             *
             * Pattern rule:
             * {
             *   "pattern_type": "benefit_exhaustion",
             *   "days_threshold": 30,
             *   "utilization_threshold": 0.90
             * }
             */

            // Applicability
            $table->json('applies_to')->nullable(); // ['claim', 'preauth'] or ['both']
            $table->json('applies_to_claim_types')->nullable(); // ['in_patient', 'out_patient'], null = all
            $table->json('applies_to_provider_types')->nullable(); // null = all
            $table->json('applies_to_member_types')->nullable(); // null = all

            // Thresholds for different actions
            $table->boolean('triggers_flag')->default(true);
            $table->boolean('triggers_block')->default(false);
            $table->boolean('triggers_alert')->default(true);

            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_quick_check')->default(false); // If true, runs during real-time check
            $table->integer('priority')->default(50); // Higher = runs first
            $table->integer('sort_order')->default(0);

            // Metadata
            $table->text('notes')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('category');
            $table->index('is_active');
            // Note: Cannot index JSON column 'applies_to' with btree in PostgreSQL
            // Use GIN index if needed: CREATE INDEX idx_name ON table USING gin (applies_to);
            $table->index(['is_active', 'priority']);
        });

        // =========================================================================
        // FRAUD ALERTS (Generated when fraud is detected)
        // =========================================================================
        Schema::create('med_fraud_alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('alert_number', 30)->unique();

            // Entity that triggered the alert
            $table->string('entity_type', 30); // claim, preauthorization, member, provider
            $table->uuid('entity_id');

            // Related Entities
            $table->uuid('claim_id')->nullable();
            $table->uuid('preauthorization_id')->nullable();
            $table->uuid('member_id')->nullable();
            $table->uuid('policy_id')->nullable();
            $table->uuid('provider_id')->nullable();

            // Alert Details
            $table->integer('fraud_score');
            $table->string('severity', 20); // low, medium, high, critical
            $table->json('triggered_rules'); // array of rule_ids that triggered
            $table->json('flags'); // detailed flag descriptions
            $table->text('summary')->nullable();

            // Financial Impact
            $table->decimal('flagged_amount', 15, 2)->default(0);
            $table->decimal('prevented_amount', 15, 2)->default(0);
            $table->decimal('recovered_amount', 15, 2)->default(0);

            // Status & Workflow
            $table->string('status', 30)->default('open');
            // open, investigating, confirmed_fraud, false_positive, dismissed, escalated

            // Assignment
            $table->uuid('assigned_to')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->string('assigned_team', 50)->nullable();

            // Investigation
            $table->text('investigation_notes')->nullable();
            $table->json('investigation_findings')->nullable();
            $table->timestamp('investigation_started_at')->nullable();

            // Resolution
            $table->uuid('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution', 30)->nullable(); // confirmed_fraud, false_positive, insufficient_evidence
            $table->text('resolution_notes')->nullable();
            $table->json('actions_taken')->nullable();

            // Escalation
            $table->boolean('is_escalated')->default(false);
            $table->uuid('escalated_to')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->text('escalation_reason')->nullable();

            // SLA Tracking
            $table->timestamp('due_at')->nullable();
            $table->boolean('is_overdue')->default(false);

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['status', 'severity']);
            $table->index('member_id');
            $table->index('provider_id');
            $table->index('claim_id');
            $table->index(['assigned_to', 'status']);
            $table->index(['entity_type', 'entity_id']);
            $table->index('created_at');
        });

        // =========================================================================
        // FRAUD ALERT NOTES (Audit trail for fraud investigations)
        // =========================================================================
        Schema::create('med_fraud_alert_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('fraud_alert_id');

            $table->string('note_type', 30); // comment, status_change, assignment, evidence, action, escalation
            $table->text('content');
            $table->string('previous_status', 30)->nullable();
            $table->string('new_status', 30)->nullable();

            $table->json('attachments')->nullable(); // file paths for evidence

            $table->uuid('created_by')->nullable();
            $table->timestamp('created_at');

            $table->foreign('fraud_alert_id')
                ->references('id')
                ->on('med_fraud_alerts')
                ->onDelete('cascade');

            $table->index(['fraud_alert_id', 'created_at']);
        });

        // =========================================================================
        // FRAUD PATTERNS (For ML model training and pattern detection)
        // =========================================================================
        Schema::create('med_fraud_patterns', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Pattern Type
            $table->string('pattern_type', 50);
            // claim_burst, amount_spike, provider_collusion, benefit_exhaustion,
            // duplicate_services, geographic_anomaly, timing_anomaly, etc.

            // Entities Involved
            $table->uuid('member_id')->nullable();
            $table->uuid('provider_id')->nullable();
            $table->uuid('policy_id')->nullable();
            $table->json('related_claims')->nullable(); // array of claim IDs

            // Pattern Analysis Data
            $table->json('pattern_data');
            /*
             * Example pattern_data:
             * {
             *   "claim_count": 15,
             *   "time_period_days": 7,
             *   "total_amount": 50000,
             *   "unique_diagnoses": 3,
             *   "unique_providers": 5,
             *   "average_per_claim": 3333,
             *   "baseline_average": 1000,
             *   "deviation_factor": 3.33
             * }
             */

            // Time Window
            $table->date('period_start');
            $table->date('period_end');
            $table->integer('claim_count')->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);

            // Anomaly Scoring
            $table->decimal('anomaly_score', 5, 2)->default(0); // 0-100
            $table->decimal('confidence_score', 5, 4)->default(0); // 0-1

            // ML Model Info
            $table->string('model_version', 20)->nullable();
            $table->json('feature_importance')->nullable();
            $table->json('model_metadata')->nullable();

            // Verification
            $table->boolean('is_verified')->default(false);
            $table->string('verified_as', 20)->nullable(); // fraud, legitimate, unknown
            $table->uuid('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_notes')->nullable();

            // Linked Alert
            $table->uuid('fraud_alert_id')->nullable();

            $table->timestamps();

            $table->index(['pattern_type', 'created_at']);
            $table->index('member_id');
            $table->index('provider_id');
            $table->index(['is_verified', 'verified_as']);
            $table->index('anomaly_score');
        });

        // =========================================================================
        // MEMBER RISK PROFILES (Aggregated risk scores per member)
        // =========================================================================
        Schema::create('med_member_risk_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('member_id')->unique();

            // Risk Scores
            $table->integer('overall_risk_score')->default(0);
            $table->string('risk_level', 20)->default('low'); // low, medium, high, critical
            $table->decimal('fraud_probability', 5, 4)->default(0); // 0-1

            // Component Scores
            $table->integer('frequency_score')->default(0);
            $table->integer('amount_score')->default(0);
            $table->integer('pattern_score')->default(0);
            $table->integer('history_score')->default(0);

            // Statistics
            $table->integer('total_claims')->default(0);
            $table->integer('flagged_claims')->default(0);
            $table->integer('rejected_claims')->default(0);
            $table->integer('fraud_confirmed_count')->default(0);
            $table->decimal('average_claim_amount', 15, 2)->default(0);
            $table->decimal('total_claimed_amount', 18, 2)->default(0);

            // Behavioral Metrics
            $table->decimal('claims_per_month_avg', 8, 2)->default(0);
            $table->decimal('claim_amount_std_dev', 15, 2)->default(0);
            $table->integer('unique_providers_used')->default(0);
            $table->integer('unique_diagnoses_used')->default(0);

            // Flags
            $table->boolean('is_blacklisted')->default(false);
            $table->timestamp('blacklisted_at')->nullable();
            $table->string('blacklist_reason', 255)->nullable();
            $table->boolean('is_watchlist')->default(false);
            $table->timestamp('watchlist_added_at')->nullable();

            // Last Updated
            $table->timestamp('last_claim_at')->nullable();
            $table->timestamp('last_flag_at')->nullable();
            $table->timestamp('scores_updated_at')->nullable();

            $table->timestamps();

            $table->foreign('member_id')
                ->references('id')
                ->on('med_members')
                ->onDelete('cascade');

            $table->index('risk_level');
            $table->index('overall_risk_score');
            $table->index('is_blacklisted');
            $table->index('is_watchlist');
        });

        // =========================================================================
        // PROVIDER RISK PROFILES (Aggregated risk scores per provider)
        // =========================================================================
        Schema::create('med_provider_risk_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('provider_id')->unique();

            // Risk Scores
            $table->integer('overall_risk_score')->default(0);
            $table->string('risk_level', 20)->default('low');
            $table->decimal('fraud_probability', 5, 4)->default(0);

            // Component Scores
            $table->integer('rejection_rate_score')->default(0);
            $table->integer('claim_amount_score')->default(0);
            $table->integer('pattern_score')->default(0);
            $table->integer('network_score')->default(0); // relationships with other flagged entities

            // Statistics
            $table->integer('total_claims')->default(0);
            $table->integer('flagged_claims')->default(0);
            $table->integer('rejected_claims')->default(0);
            $table->decimal('rejection_rate', 5, 4)->default(0);
            $table->decimal('average_claim_amount', 15, 2)->default(0);
            $table->decimal('total_claimed_amount', 18, 2)->default(0);

            // Network Analysis
            $table->integer('unique_members_served')->default(0);
            $table->integer('flagged_members_served')->default(0);
            $table->decimal('flagged_member_ratio', 5, 4)->default(0);

            // Flags
            $table->boolean('is_blacklisted')->default(false);
            $table->timestamp('blacklisted_at')->nullable();
            $table->string('blacklist_reason', 255)->nullable();
            $table->boolean('is_watchlist')->default(false);
            $table->boolean('requires_review')->default(false);

            $table->timestamp('scores_updated_at')->nullable();

            $table->timestamps();

            $table->foreign('provider_id')
                ->references('id')
                ->on('med_providers')
                ->onDelete('cascade');

            $table->index('risk_level');
            $table->index('overall_risk_score');
            $table->index('is_blacklisted');
        });

        // =========================================================================
        // FRAUD BLACKLIST (Quick lookup for blocked entities)
        // =========================================================================
        Schema::create('med_fraud_blacklist', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('entity_type', 30); // member, provider, national_id, phone, email, card_number
            $table->string('entity_identifier', 255); // the ID or value being blacklisted
            $table->uuid('entity_id')->nullable(); // foreign key if applicable

            $table->string('reason', 50);
            $table->text('description')->nullable();

            $table->date('effective_from');
            $table->date('effective_until')->nullable(); // null = permanent
            $table->boolean('is_active')->default(true);

            // Source
            $table->uuid('fraud_alert_id')->nullable();
            $table->uuid('added_by')->nullable();

            // Approval
            $table->boolean('is_approved')->default(false);
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['entity_type', 'entity_identifier']);
            $table->index(['entity_type', 'is_active']);
            $table->index('entity_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('med_fraud_blacklist');
        Schema::dropIfExists('med_provider_risk_profiles');
        Schema::dropIfExists('med_member_risk_profiles');
        Schema::dropIfExists('med_fraud_patterns');
        Schema::dropIfExists('med_fraud_alert_notes');
        Schema::dropIfExists('med_fraud_alerts');
        Schema::dropIfExists('med_fraud_rules');
    }
};
