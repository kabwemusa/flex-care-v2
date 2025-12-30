<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // public function up(): void
    // {
    //     // 1. Rate Cards (The Metadata/Version Container)
    //     Schema::create('med_rate_cards', function (Blueprint $table) {
    //         $table->uuid('id')->primary();
        
    //         $table->uuid('plan_id');
    //         $table->foreign('plan_id')
    //               ->references('id')
    //               ->on('med_plans')
    //               ->cascadeOnDelete();
        
    //         $table->string('name');                     // e.g., "2025 Standard Rates - Zone A"
    //         $table->char('currency', 3)->default('ZMW');
    //         $table->boolean('is_active')->default(false);
        
    //         // Versioning
    //         $table->date('valid_from')->index();
    //         $table->date('valid_until')->nullable(); // NULL = indefinite
        
    //         $table->timestamps();
    //     });
        

    //     // 2. Rate Card Entries (The Actual Price Matrix)
    //     Schema::create('med_rate_card_entries', function (Blueprint $table) {
    //         $table->uuid('id')->primary();
        
    //         $table->uuid('rate_card_id');
    //         $table->foreign('rate_card_id')
    //               ->references('id')
    //               ->on('med_rate_cards')
    //               ->cascadeOnDelete();
        
    //         // --- Pricing Inputs ---
    //         $table->unsignedTinyInteger('min_age')->default(0);
    //         $table->unsignedTinyInteger('max_age')->default(100);
        
    //         $table->string('gender', 1)->nullable();          // M, F, NULL
    //         $table->string('region_code')->nullable();        // LSK, ND, NULL
    //         $table->string('member_type')->default('Principal');
        
    //         // --- Price Output ---
    //         $table->decimal('price', 15, 2);
        
    //         // --- Performance Indexes ---
    //         $table->index(['rate_card_id', 'min_age', 'max_age']);
    //         $table->index('member_type');
    //     });
        
    // }

    // public function down(): void
    // {
    //     Schema::dropIfExists('med_rate_card_entries');
    //     Schema::dropIfExists('med_rate_cards');
    // }
};
