<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // public function up(): void
    // {
    //     // 1. Addons (Extra items sold with a plan)
    //     Schema::create('med_addons', function (Blueprint $table) {
    //         $table->uuid('id')->primary();
    //         // $table->foreignId('plan_id')->constrained('med_plans')->onDelete('cascade');
    //         $table->string('name');          // e.g., "Travel Cover"
    //         $table->decimal('price', 15, 2); // Simple pricing (add RateCard link if complex)
    //         $table->boolean('is_mandatory')->default(false);
    //         $table->timestamps();
    //     });

    //     // 2. Discount Cards (Rules for price reduction)
    //     Schema::create('med_discount_cards', function (Blueprint $table) {
    //         $table->uuid('id')->primary();
        
    //         // Nullable = global discount
    //         $table->uuid('plan_id')->nullable();
    //         $table->foreign('plan_id')
    //               ->references('id')
    //               ->on('med_plans')
    //               ->cascadeOnDelete();
        
    //         $table->string('name');            // e.g., "Annual Payment Discount"
    //         $table->string('code')->unique();  // e.g., DISC-ANNUAL-05
        
    //         $table->enum('type', ['percentage', 'fixed']);
    //         $table->decimal('value', 15, 2);
        
    //         // Rule engine (pricing context)
    //         $table->json('trigger_rule');
        
    //         $table->date('valid_from');
    //         $table->date('valid_until')->nullable();
        
    //         $table->timestamps();
    //     });
        
    // }

    // public function down(): void
    // {
    //     Schema::dropIfExists('med_discount_cards');
    //     Schema::dropIfExists('med_addons');
    // }
};
