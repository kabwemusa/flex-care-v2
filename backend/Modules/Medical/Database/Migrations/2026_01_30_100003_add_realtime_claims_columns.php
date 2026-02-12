<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // =========================================================================
        // ADD COLUMNS TO med_claims FOR REAL-TIME PROCESSING
        // =========================================================================
        Schema::table('med_claims', function (Blueprint $table) {
            // Provider Relationship
            $table->uuid('provider_id_fk')->nullable()->after('member_id');

            // Pre-authorization Link
            $table->uuid('preauthorization_id')->nullable()->after('provider_id_fk');

            // Submission Source
            $table->string('submission_source', 20)->default('portal')->after('submission_type');
            // portal, api, mobile, paper, email

            // Processing Timestamps
            $table->timestamp('processing_started_at')->nullable()->after('received_at');
            $table->timestamp('processing_completed_at')->nullable()->after('processing_started_at');

            // Auto-Adjudication
            $table->boolean('auto_adjudicated')->default(false)->after('processing_completed_at');
            $table->json('auto_adjudication_result')->nullable()->after('auto_adjudicated');
            $table->string('auto_adjudication_status', 30)->nullable()->after('auto_adjudication_result');
            // passed, failed, partial, skipped

            // Fraud Detection (enhance existing fields)
            $table->timestamp('fraud_checked_at')->nullable()->after('fraud_flags');
            $table->string('fraud_check_version', 20)->nullable()->after('fraud_checked_at');
            $table->string('fraud_check_status', 20)->nullable()->after('fraud_check_version');
            // passed, flagged, blocked, pending

            // Queue Management
            $table->integer('queue_priority')->default(5)->after('fraud_check_status'); // 1-10, lower = higher priority
            $table->string('queue_name', 50)->nullable()->after('queue_priority');
            $table->integer('retry_count')->default(0)->after('queue_name');
            $table->json('processing_errors')->nullable()->after('retry_count');
            $table->timestamp('last_retry_at')->nullable()->after('processing_errors');

            // API Tracking
            $table->uuid('api_credential_id')->nullable()->after('last_retry_at');
            $table->string('api_request_id', 50)->nullable()->after('api_credential_id');

            // Foreign Keys
            $table->foreign('provider_id_fk')
                ->references('id')
                ->on('med_providers')
                ->onDelete('set null');

            // Indexes for queue processing
            $table->index(['status', 'queue_priority', 'created_at'], 'idx_claims_queue');
            $table->index('provider_id_fk');
            $table->index('preauthorization_id');
            $table->index('auto_adjudicated');
            $table->index('fraud_check_status');
        });

        // =========================================================================
        // ADD COLUMNS TO med_claim_lines
        // =========================================================================
        Schema::table('med_claim_lines', function (Blueprint $table) {
            // Auto-adjudication results
            $table->boolean('auto_adjudicated')->default(false)->after('adjudication_notes');
            $table->json('auto_adjudication_details')->nullable()->after('auto_adjudicated');

            // Tariff reference
            $table->uuid('tariff_entry_id')->nullable()->after('tariff_code');
        });

        // =========================================================================
        // ADD COLUMNS TO med_member_benefit_utilization FOR RESERVATIONS
        // =========================================================================
        Schema::table('med_member_benefit_utilization', function (Blueprint $table) {
            // Reserved amounts (for pre-authorizations)
            $table->decimal('reserved_amount', 15, 2)->default(0)->after('used_days');
            $table->integer('reserved_count')->default(0)->after('reserved_amount');
            $table->integer('reserved_days')->default(0)->after('reserved_count');

            // Active reservations count
            $table->integer('active_reservations_count')->default(0)->after('reserved_days');
        });

        // =========================================================================
        // ADD COLUMNS TO med_members FOR FRAUD TRACKING
        // =========================================================================
        Schema::table('med_members', function (Blueprint $table) {
            // Risk profile reference
            $table->integer('fraud_risk_score')->default(0)->after('declared_conditions');
            $table->string('fraud_risk_level', 20)->default('low')->after('fraud_risk_score');
            $table->boolean('is_fraud_watchlist')->default(false)->after('fraud_risk_level');
            $table->boolean('is_fraud_blacklist')->default(false)->after('is_fraud_watchlist');

            // Indexes
            $table->index('fraud_risk_level');
            $table->index('is_fraud_blacklist');
        });

        // =========================================================================
        // ADD COLUMNS TO med_policies FOR FRAUD TRACKING
        // =========================================================================
        Schema::table('med_policies', function (Blueprint $table) {
            // Risk indicators
            $table->integer('fraud_flags_count')->default(0)->after('status');
            $table->boolean('has_fraud_history')->default(false)->after('fraud_flags_count');
        });
    }

    public function down(): void
    {
        Schema::table('med_policies', function (Blueprint $table) {
            $table->dropColumn(['fraud_flags_count', 'has_fraud_history']);
        });

        Schema::table('med_members', function (Blueprint $table) {
            $table->dropIndex(['fraud_risk_level']);
            $table->dropIndex(['is_fraud_blacklist']);
            $table->dropColumn([
                'fraud_risk_score',
                'fraud_risk_level',
                'is_fraud_watchlist',
                'is_fraud_blacklist'
            ]);
        });

        Schema::table('med_member_benefit_utilization', function (Blueprint $table) {
            $table->dropColumn([
                'reserved_amount',
                'reserved_count',
                'reserved_days',
                'active_reservations_count'
            ]);
        });

        Schema::table('med_claim_lines', function (Blueprint $table) {
            $table->dropColumn([
                'auto_adjudicated',
                'auto_adjudication_details',
                'tariff_entry_id'
            ]);
        });

        Schema::table('med_claims', function (Blueprint $table) {
            $table->dropForeign(['provider_id_fk']);
            $table->dropIndex('idx_claims_queue');
            $table->dropIndex(['provider_id_fk']);
            $table->dropIndex(['preauthorization_id']);
            $table->dropIndex(['auto_adjudicated']);
            $table->dropIndex(['fraud_check_status']);

            $table->dropColumn([
                'provider_id_fk',
                'preauthorization_id',
                'submission_source',
                'processing_started_at',
                'processing_completed_at',
                'auto_adjudicated',
                'auto_adjudication_result',
                'auto_adjudication_status',
                'fraud_checked_at',
                'fraud_check_version',
                'fraud_check_status',
                'queue_priority',
                'queue_name',
                'retry_count',
                'processing_errors',
                'last_retry_at',
                'api_credential_id',
                'api_request_id'
            ]);
        });
    }
};
