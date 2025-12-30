<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Endorsements & Policy Changes
     * 
     * Tables:
     * - med_endorsements: Mid-term policy changes
     */
    public function up(): void
    {
        // =====================================================================
        // ENDORSEMENTS
        // =====================================================================
        Schema::create('med_endorsements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('endorsement_number', 30)->unique();
            $table->foreignUuid('policy_id')->constrained('med_policies')->restrictOnDelete();
            
            // Endorsement Type
            $table->string('endorsement_type', 30); // add_member, remove_member, upgrade_plan, downgrade_plan, add_addon, remove_addon, change_details, correction, cancellation, reinstatement
            $table->string('description');
            
            // Effective Period
            $table->date('effective_date');
            $table->date('request_date');
            
            // Financial Impact
            $table->decimal('premium_adjustment', 15, 2)->default(0); // Positive = increase, negative = decrease
            $table->decimal('prorated_amount', 15, 2)->default(0);
            $table->boolean('generates_invoice')->default(false);
            $table->boolean('generates_refund')->default(false);
            $table->foreignUuid('invoice_id')->nullable()->constrained('med_invoices')->nullOnDelete();
            
            // What Changed
            $table->json('changes')->nullable(); // Detailed change log
            $table->json('before_snapshot')->nullable(); // State before
            $table->json('after_snapshot')->nullable(); // State after
            
            // Related Records
            $table->foreignUuid('member_id')->nullable()->constrained('med_members')->nullOnDelete();
            $table->foreignUuid('addon_id')->nullable()->constrained('med_addons')->nullOnDelete();
            $table->foreignUuid('new_plan_id')->nullable()->constrained('med_plans')->nullOnDelete();
            
            // Status
            $table->string('status', 20)->default('pending'); // pending, approved, rejected, processed, cancelled
            $table->uuid('requested_by')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->uuid('processed_by')->nullable();
            $table->timestamp('processed_at')->nullable();
            
            $table->string('rejection_reason', 100)->nullable();
            $table->text('notes')->nullable();
            $table->json('supporting_documents')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('endorsement_number');
            $table->index('policy_id');
            $table->index('endorsement_type');
            $table->index('status');
            $table->index('effective_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('med_endorsements');
    }
};