<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Corporate Groups & Contacts
     * 
     * Tables:
     * - med_corporate_groups: Companies/organizations that purchase group policies
     * - med_group_contacts: HR contacts, brokers, administrators for a group
     */
    public function up(): void
    {
        // =====================================================================
        // CORPORATE GROUPS
        // =====================================================================
        Schema::create('med_corporate_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('trading_name')->nullable();
            $table->string('registration_number', 50)->nullable();
            $table->string('tax_number', 50)->nullable();
            
            // Industry & Size
            $table->string('industry', 100)->nullable();
            $table->string('company_size', 20)->nullable(); // sme, medium, large, enterprise
            $table->integer('employee_count')->nullable();
            
            // Contact Information
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('website')->nullable();
            
            // Address
            $table->text('physical_address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('country', 3)->default('ZMW');
            $table->string('postal_code', 20)->nullable();
            
            // Billing
            $table->string('billing_email')->nullable();
            $table->text('billing_address')->nullable();
            $table->string('payment_terms', 20)->default('30_days'); // immediate, 15_days, 30_days, 60_days
            $table->string('preferred_payment_method', 20)->nullable(); // bank_transfer, direct_debit, cheque
            
            // Relationship Management
            $table->uuid('account_manager_id')->nullable(); // FK to users
            $table->uuid('broker_id')->nullable(); // FK to brokers table if exists
            $table->decimal('broker_commission_rate', 5, 2)->nullable();
            
            // Status
            $table->string('status', 20)->default('prospect'); // prospect, active, suspended, terminated
            $table->date('onboarded_at')->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('status');
            $table->index('industry');
            $table->index('account_manager_id');
        });

        // =====================================================================
        // GROUP CONTACTS
        // =====================================================================
        Schema::create('med_group_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('group_id')->constrained('med_corporate_groups')->cascadeOnDelete();
            
            $table->string('contact_type', 20); // primary, hr, finance, broker, administrator
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('job_title', 100)->nullable();
            $table->string('email');
            $table->string('phone', 30)->nullable();
            $table->string('mobile', 30)->nullable();
            
            // Portal Access
            $table->boolean('has_portal_access')->default(false);
            $table->uuid('user_id')->nullable(); // FK to users table for portal login
            
            // Permissions (what this contact can do)
            $table->json('permissions')->nullable(); // ['view_members', 'add_members', 'view_claims', 'view_invoices']
            
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            $table->index(['group_id', 'contact_type']);
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('med_group_contacts');
        Schema::dropIfExists('med_corporate_groups');
    }
};