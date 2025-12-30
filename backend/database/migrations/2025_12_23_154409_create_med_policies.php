<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // /**
    //  * Run the migrations.
    //  */
    // public function up(): void
    // {
    //     Schema::create('med_policies', function (Blueprint $table) {
    //         // Primary Key
    //         $table->uuid('id')->primary();
        
    //         // --- Foreign Keys (UUID SAFE) ---
    //         $table->uuid('plan_id');
    //         $table->uuid('group_id')->nullable();
        
    //         $table->foreign('plan_id')
    //               ->references('id')
    //               ->on('med_plans');
        
    //         $table->foreign('group_id')
    //               ->references('id')
    //               ->on('med_groups');
        
    //         // --- Identifiers ---
    //         $table->string('policy_number')->unique()->index();
        
    //         // --- Dates (PAS Critical) ---
    //         $table->date('start_date');
    //         $table->date('end_date');
    //         $table->date('renewal_date')->index();
        
    //         // --- Financials ---
    //         $table->enum('billing_frequency', ['Monthly', 'Quarterly', 'Annually']);
    //         $table->string('currency', 3)->default('ZMW');
        
    //         // --- Workflow Status ---
    //         $table->enum('status', ['Draft', 'Active', 'Suspended', 'Expired', 'Cancelled'])
    //               ->default('Draft')
    //               ->index();
        
    //         $table->timestamps();
    //         $table->softDeletes();
    //     });
        
    // }

    // /**
    //  * Reverse the migrations.
    //  */
    // public function down(): void
    // {
    //     Schema::dropIfExists('med_policies');
    // }
};
