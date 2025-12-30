<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Billing & Payments
     * 
     * Tables:
     * - med_invoices: Premium invoices
     * - med_invoice_items: Line items on invoices
     * - med_payments: Payment records
     * - med_payment_allocations: How payments are allocated to invoices
     */
    public function up(): void
    {
        // =====================================================================
        // INVOICES
        // =====================================================================
        Schema::create('med_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('invoice_number', 30)->unique();
            
            // Links
            $table->foreignUuid('policy_id')->constrained('med_policies')->restrictOnDelete();
            $table->foreignUuid('group_id')->nullable()->constrained('med_corporate_groups')->nullOnDelete();
            
            // Invoice Type
            $table->string('invoice_type', 20); // premium, adjustment, endorsement, refund
            $table->string('billing_period_start')->nullable();
            $table->string('billing_period_end')->nullable();
            
            // Dates
            $table->date('invoice_date');
            $table->date('due_date');
            $table->date('paid_date')->nullable();
            
            // Amounts
            $table->string('currency', 3)->default('ZMW');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);
            
            // Status
            $table->string('status', 20)->default('draft'); // draft, sent, partially_paid, paid, overdue, cancelled, written_off
            $table->integer('days_overdue')->default(0);
            
            // Recipient
            $table->string('bill_to_name')->nullable();
            $table->string('bill_to_email')->nullable();
            $table->text('bill_to_address')->nullable();
            
            // Communication
            $table->timestamp('sent_at')->nullable();
            $table->string('sent_via', 20)->nullable(); // email, post, portal
            $table->integer('reminder_count')->default(0);
            $table->timestamp('last_reminder_at')->nullable();
            
            // Metadata
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->uuid('created_by')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('invoice_number');
            $table->index('status');
            $table->index('policy_id');
            $table->index('group_id');
            $table->index('due_date');
            $table->index(['status', 'due_date']);
        });

        // =====================================================================
        // INVOICE ITEMS
        // =====================================================================
        Schema::create('med_invoice_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('invoice_id')->constrained('med_invoices')->cascadeOnDelete();
            $table->foreignUuid('member_id')->nullable()->constrained('med_members')->nullOnDelete();
            
            $table->integer('line_number')->default(1);
            $table->string('item_type', 30); // base_premium, addon_premium, loading, member_premium, adjustment, tax, discount
            $table->string('description');
            
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('amount', 15, 2);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2);
            
            // Reference
            $table->string('reference_type', 30)->nullable(); // addon, member, loading, discount
            $table->uuid('reference_id')->nullable();
            
            $table->timestamps();
            
            $table->index('invoice_id');
        });

        // =====================================================================
        // PAYMENTS
        // =====================================================================
        Schema::create('med_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('payment_number', 30)->unique();
            
            // Links
            $table->foreignUuid('policy_id')->nullable()->constrained('med_policies')->nullOnDelete();
            $table->foreignUuid('group_id')->nullable()->constrained('med_corporate_groups')->nullOnDelete();
            
            // Payment Details
            $table->date('payment_date');
            $table->string('currency', 3)->default('ZMW');
            $table->decimal('amount', 15, 2);
            $table->decimal('allocated_amount', 15, 2)->default(0);
            $table->decimal('unallocated_amount', 15, 2)->default(0);
            
            // Payment Method
            $table->string('payment_method', 30); // bank_transfer, cheque, cash, mobile_money, card, direct_debit
            $table->string('payment_reference', 100)->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->string('cheque_number', 30)->nullable();
            $table->string('transaction_id', 100)->nullable();
            
            // Payer
            $table->string('payer_name')->nullable();
            $table->string('payer_reference', 50)->nullable();
            
            // Status
            $table->string('status', 20)->default('received'); // pending, received, confirmed, bounced, reversed, refunded
            $table->date('confirmed_date')->nullable();
            
            // Reconciliation
            $table->boolean('is_reconciled')->default(false);
            $table->uuid('reconciled_by')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->string('reconciliation_reference', 50)->nullable();
            
            $table->text('notes')->nullable();
            $table->uuid('received_by')->nullable();
            $table->uuid('created_by')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('payment_number');
            $table->index('status');
            $table->index('payment_date');
            $table->index('policy_id');
            $table->index('group_id');
        });

        // =====================================================================
        // PAYMENT ALLOCATIONS
        // =====================================================================
        Schema::create('med_payment_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_id')->constrained('med_payments')->cascadeOnDelete();
            $table->foreignUuid('invoice_id')->constrained('med_invoices')->restrictOnDelete();
            
            $table->decimal('amount', 15, 2);
            $table->date('allocation_date');
            
            $table->uuid('allocated_by')->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            $table->index('payment_id');
            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('med_payment_allocations');
        Schema::dropIfExists('med_payments');
        Schema::dropIfExists('med_invoice_items');
        Schema::dropIfExists('med_invoices');
    }
};