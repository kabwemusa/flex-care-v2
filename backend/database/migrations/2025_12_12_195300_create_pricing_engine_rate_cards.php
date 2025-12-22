<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Rate Cards (The Metadata/Version Container)
        Schema::create('med_rate_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('med_plans')->onDelete('cascade');
            
            $table->string('name');            // e.g., "2025 Standard Rates - Zone A"
            $table->char('currency', 3)->default('ZMW'); // ISO Code
            $table->boolean('is_active')->default(false);
            
            // Versioning Logic
            $table->date('valid_from')->index();
            $table->date('valid_until')->nullable(); // Null = Indefinite
            
            $table->timestamps();
        });

        // 2. Rate Card Entries (The Actual Price Matrix)
        Schema::create('med_rate_card_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rate_card_id')->constrained('med_rate_cards')->onDelete('cascade');
            
            // --- The Variables (Inputs) ---
            $table->unsignedTinyInteger('min_age')->default(0);
            $table->unsignedTinyInteger('max_age')->default(100);
            
            // Nullable columns allow for "Any/All" logic
            $table->string('gender', 1)->nullable();       // 'M', 'F', or NULL (Any)
            $table->string('region_code')->nullable();     // e.g., 'LSK', or NULL (National)
            $table->string('member_type')->default('Principal'); // Principal, Spouse, Child
            
            // --- The Output ---
            $table->decimal('price', 15, 2);

            // --- Performance Indexes ---
            // This composite index allows the engine to scan ranges instantly
            $table->index(['rate_card_id', 'min_age', 'max_age']);
            $table->index('member_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('med_rate_card_entries');
        Schema::dropIfExists('med_rate_cards');
    }
};
