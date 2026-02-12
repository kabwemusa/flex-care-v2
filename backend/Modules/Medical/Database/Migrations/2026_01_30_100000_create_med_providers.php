<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // =========================================================================
        // HEALTHCARE PROVIDERS (Hospitals, Clinics, Pharmacies, etc.)
        // =========================================================================
        Schema::create('med_providers', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Identity
            $table->string('provider_code', 20)->unique();
            $table->string('name', 255);
            $table->string('trading_name', 255)->nullable();
            $table->string('provider_type', 30); // hospital, clinic, pharmacy, lab, optical, dental, specialist
            $table->string('license_number', 50)->nullable();
            $table->string('tax_id', 50)->nullable();
            $table->string('registration_number', 50)->nullable();

            // Contact
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('region', 100)->nullable();
            $table->string('country', 100)->default('Zambia');
            $table->string('postal_code', 20)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('website', 255)->nullable();

            // Primary Contact Person
            $table->string('contact_person', 255)->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->string('contact_email', 255)->nullable();

            // Network Status
            $table->boolean('is_network_provider')->default(false);
            $table->string('network_tier', 20)->nullable(); // tier1, tier2, tier3
            $table->date('contract_start_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->json('contracted_services')->nullable(); // services covered under contract
            $table->decimal('discount_percentage', 5, 2)->nullable(); // network discount

            // Banking Details (for claim payments)
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_branch', 100)->nullable();
            $table->string('account_name', 255)->nullable();
            $table->string('account_number', 50)->nullable();
            $table->string('swift_code', 20)->nullable();

            // Status
            $table->string('status', 20)->default('pending'); // pending, active, suspended, terminated
            $table->timestamp('status_changed_at')->nullable();
            $table->string('status_reason', 255)->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();

            // Fraud & Risk Metrics
            $table->decimal('fraud_risk_score', 5, 2)->default(0);
            $table->string('fraud_risk_level', 20)->default('low'); // low, medium, high
            $table->timestamp('fraud_score_updated_at')->nullable();
            $table->json('fraud_flags')->nullable();

            // Statistics (updated periodically)
            $table->integer('total_claims_count')->default(0);
            $table->decimal('total_claims_amount', 18, 2)->default(0);
            $table->integer('approved_claims_count')->default(0);
            $table->decimal('approved_claims_amount', 18, 2)->default(0);
            $table->integer('rejected_claims_count')->default(0);
            $table->decimal('average_claim_amount', 15, 2)->default(0);
            $table->decimal('rejection_rate', 5, 4)->default(0);
            $table->timestamp('stats_updated_at')->nullable();

            // Metadata
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('provider_type');
            $table->index('status');
            $table->index('is_network_provider');
            $table->index('fraud_risk_level');
            $table->index(['status', 'provider_type']);
        });

        // =========================================================================
        // PROVIDER API CREDENTIALS (For hospital API authentication)
        // =========================================================================
        Schema::create('med_provider_api_credentials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('provider_id');

            // API Key & Secret
            $table->string('api_key', 64)->unique();
            $table->string('api_secret', 128); // hashed
            $table->string('name', 100)->nullable(); // e.g., "Production API", "Test API"
            $table->text('description')->nullable();

            // Security
            $table->json('allowed_ips')->nullable(); // array of allowed IP addresses, null = any
            $table->json('scopes')->nullable(); // ['eligibility', 'preauth', 'claims', 'member_lookup']

            // Rate Limiting
            $table->integer('rate_limit_per_minute')->default(60);
            $table->integer('rate_limit_per_hour')->default(1000);
            $table->integer('rate_limit_per_day')->default(10000);

            // Usage Tracking
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();
            $table->integer('total_requests')->default(0);
            $table->integer('failed_requests')->default(0);

            // Validity
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason', 255)->nullable();

            // Audit
            $table->uuid('created_by')->nullable();
            $table->uuid('revoked_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('provider_id')
                ->references('id')
                ->on('med_providers')
                ->onDelete('cascade');

            $table->index(['provider_id', 'is_active']);
            $table->index('expires_at');
        });

        // =========================================================================
        // PROVIDER API LOGS (For audit trail and fraud detection)
        // =========================================================================
        Schema::create('med_provider_api_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('provider_id');
            $table->uuid('credential_id')->nullable();

            // Request Details
            $table->string('endpoint', 100);
            $table->string('method', 10);
            $table->json('request_headers')->nullable();
            $table->json('request_payload')->nullable();
            $table->text('request_query')->nullable();

            // Response Details
            $table->integer('response_status');
            $table->json('response_body')->nullable();
            $table->integer('response_time_ms');

            // Client Info
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();

            // Related Entities (for easier querying)
            $table->uuid('member_id')->nullable();
            $table->uuid('claim_id')->nullable();
            $table->uuid('preauth_id')->nullable();
            $table->uuid('policy_id')->nullable();

            // Error Tracking
            $table->boolean('is_error')->default(false);
            $table->string('error_code', 50)->nullable();
            $table->text('error_message')->nullable();

            // Rate Limit Info
            $table->boolean('rate_limited')->default(false);
            $table->integer('rate_limit_remaining')->nullable();

            $table->timestamp('created_at');

            $table->foreign('provider_id')
                ->references('id')
                ->on('med_providers')
                ->onDelete('cascade');

            $table->foreign('credential_id')
                ->references('id')
                ->on('med_provider_api_credentials')
                ->onDelete('set null');

            // Indexes for common queries
            $table->index(['provider_id', 'created_at']);
            $table->index(['member_id', 'created_at']);
            $table->index(['claim_id']);
            $table->index(['preauth_id']);
            $table->index(['created_at']);
            $table->index(['endpoint', 'created_at']);
            $table->index(['is_error', 'created_at']);
        });

        // =========================================================================
        // PROVIDER CONTACTS (Multiple contact persons per provider)
        // =========================================================================
        Schema::create('med_provider_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('provider_id');

            $table->string('name', 255);
            $table->string('title', 100)->nullable();
            $table->string('department', 100)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('mobile', 30)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_billing_contact')->default(false);
            $table->boolean('is_technical_contact')->default(false);
            $table->boolean('receives_notifications')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('provider_id')
                ->references('id')
                ->on('med_providers')
                ->onDelete('cascade');

            $table->index('provider_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('med_provider_contacts');
        Schema::dropIfExists('med_provider_api_logs');
        Schema::dropIfExists('med_provider_api_credentials');
        Schema::dropIfExists('med_providers');
    }
};
