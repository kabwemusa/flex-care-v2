<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('med_plan_addon', function (Blueprint $table) {
            $table->id();
            
            // Foreign Keys
            $table->foreignId('plan_id')
                  ->constrained('med_plans')
                  ->onDelete('cascade');
                  
            $table->foreignId('addon_id')
                  ->constrained('med_addons')
                  ->onDelete('cascade');

            // Unique Constraint: Prevents the same addon being linked to the same plan twice
            $table->unique(['plan_id', 'addon_id']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('med_plan_addon');
    }
};
