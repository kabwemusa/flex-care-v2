<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Simplifies addon structure by:
     * 1. Recreating med_addons table with pricing fields
     * 2. med_addon_rates, med_addon_rate_entries, med_addon_benefits are no longer needed
     */
    // public function up(): void
    // {
    //     // Recreate med_addons table with simplified structure
    //     Schema::create('med_addons', function (Blueprint $table) {
    //         $table->uuid('id')->primary();

    //         // --- Identity ---
    //         $table->string('code')->unique();
    //         $table->string('name');

    //         // --- Classification ---
    //         $table->string('addon_type')->default('optional');
    //         /*
    //             optional: Customer chooses to add
    //             mandatory: Required with certain plans
    //             conditional: Required under certain conditions
    //         */

    //         // --- Pricing (Simplified - moved from addon_rates) ---
    //         $table->string('pricing_type')->default('fixed');
    //         /*
    //             fixed: Flat amount
    //             per_member: Per covered member
    //             percentage: % of base premium
    //         */
    //         $table->string('currency', 10)->default('ZMW');
    //         $table->decimal('amount', 15, 2)->nullable();
    //         $table->decimal('percentage', 5, 2)->nullable();
    //         $table->string('percentage_basis')->nullable(); // base_premium, total_premium

    //         // --- Description ---
    //         $table->text('description')->nullable();
    //         $table->text('terms_conditions')->nullable();

    //         // --- Availability ---
    //         $table->boolean('is_active')->default(true);
    //         $table->date('effective_from')->nullable();
    //         $table->date('effective_to')->nullable();

    //         // --- Display ---
    //         $table->string('icon')->nullable();
    //         $table->unsignedInteger('sort_order')->default(0);

    //         // --- Audit ---
    //         $table->timestamps();
    //         $table->softDeletes();
    //     });
    // }

    // /**
    //  * Reverse the migrations.
    //  */
    // public function down(): void
    // {
    //     Schema::dropIfExists('med_addons');
    // }
};
