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
    //     Schema::create('med_plan_addon', function (Blueprint $table) {
    //         $table->uuid('id')->primary();
    //         // Foreign Keys
    //         $table->uuid('plan_id')->nullable();
    //         $table->uuid('addon_id')->nullable();
    //         $table->foreign('plan_id')
    //               ->references('id')
    //               ->on('med_plans')
    //               ->cascadeOnDelete();
                  
    //         $table->foreign('addon_id')
    //                 ->references('id')
    //               ->on('med_addons')
    //               ->cascadeOnDelete();

    //         // Unique Constraint: Prevents the same addon being linked to the same plan twice
    //         $table->unique(['plan_id', 'addon_id']);

    //         $table->timestamps();
    //     });
    // }

    // /**
    //  * Reverse the migrations.
    //  */
    // public function down(): void
    // {
    //     Schema::dropIfExists('med_plan_addon');
    // }
};
