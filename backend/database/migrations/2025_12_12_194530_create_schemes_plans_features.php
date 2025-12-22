<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Schemes (The Umbrella Product)
        Schema::create('med_schemes', function (Blueprint $table) {
            $table->id();
            $table->string('name');             // e.g., "Corporate Health"
            $table->string('slug')->unique();   // e.g., "corporate-health"
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // 2. Plans (The Tiers)
        Schema::create('med_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheme_id')->constrained('med_schemes')->onDelete('cascade');
            $table->string('name');             // e.g., "Gold Plan"
            $table->string('code')->unique();   // e.g., "CORP-GOLD-001"
            $table->string('type')->index();    // e.g., "Individual", "Family", "SME"
            $table->timestamps();
        });

        // 3. Features (The Reusable Library)
        Schema::create('med_features', function (Blueprint $table) {
            $table->id();
            $table->string('name');             // e.g., "Dental Care"
            $table->string('category')->index(); // e.g., "Clinical", "In-Patient"
            $table->string('code')->unique();   // e.g., "F-DENT-01"
            $table->timestamps();
        });

        // 4. Feature_Plan (The Pivot with Limits)
        Schema::create('med_feature_plan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('med_plans')->onDelete('cascade');
            $table->foreignId('feature_id')->constrained('med_features')->onDelete('cascade');
            
            // Limit Logic (e.g., 5000 ZMW limit)
            $table->decimal('limit_amount', 15, 2)->nullable(); 
            $table->string('limit_description')->nullable(); // e.g., "Per family per year"
            
            // Ensure a feature is only added once per plan
            $table->unique(['plan_id', 'feature_id']); 
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('med_feature_plan');
        Schema::dropIfExists('med_features');
        Schema::dropIfExists('med_plans');
        Schema::dropIfExists('med_schemes');
    }
};
