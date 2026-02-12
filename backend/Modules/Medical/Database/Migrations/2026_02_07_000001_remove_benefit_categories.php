<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop foreign key and column from med_benefits
        Schema::table('med_benefits', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
        });

        // Drop the categories table
        Schema::dropIfExists('med_benefit_categories');
    }

    public function down(): void
    {
        // Recreate the categories table
        Schema::create('med_benefit_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon', 50)->nullable();
            $table->string('color', 20)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Re-add category_id column to med_benefits
        Schema::table('med_benefits', function (Blueprint $table) {
            $table->uuid('category_id')->nullable()->after('id');
            $table->foreign('category_id')
                ->references('id')
                ->on('med_benefit_categories')
                ->cascadeOnDelete();
        });
    }
};
