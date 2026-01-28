<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ==========================================================================
     * MEDICAL BENEFIT CATALOG
     * ==========================================================================
     * 
     * Benefits are defined ONCE in a catalog, then LINKED to plans with limits.
     * 
     * Structure:
     * ----------
     * Category (In-Patient, Out-Patient, Dental...)
     *    └── Benefit (Surgery, Room & Board, Dental Extraction...)
     *           └── Sub-Benefit (Major Surgery, Minor Surgery...) [via parent_id]
     * 
     * This separates WHAT is covered (catalog) from HOW MUCH (plan_benefits)
     * 
     * ==========================================================================
     */

    public function up(): void
    {
        // ======================================================================
        // 1. BENEFIT CATEGORIES - Top-level groupings
        // ======================================================================
        // Examples: In-Patient, Out-Patient, Maternity, Dental, Optical, Wellness
        
        Schema::create('med_benefit_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            $table->string('code')->unique();                // CAT-INP, CAT-OPD, CAT-MAT
            $table->string('name');                          // "In-Patient Services"
            
            $table->text('description')->nullable();
            $table->string('icon')->nullable();              // For UI: "hospital-bed"
            $table->string('color')->nullable();             // For UI: "#4CAF50"
            
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            $table->softDeletes();
        });

        // ======================================================================
        // 2. BENEFITS - Master Benefit Library
        // ======================================================================
        // Reusable definitions across all medical plans
        
        Schema::create('med_benefits', function (Blueprint $table) {
            // --- Primary Key ---
            $table->uuid('id');
            $table->primary('id');
        
            // --- Category Reference ---
            $table->uuid('category_id');
            
            // --- Hierarchy ---
            $table->uuid('parent_id')->nullable();
        
            // --- Identity ---
            $table->string('code')->unique()->index();
            $table->string('name');
            $table->string('short_name')->nullable();
        
            // --- Classification ---
            $table->string('benefit_type')->index();
        
            // --- Default Limits ---
            $table->string('limit_type')->default('amount');
            $table->string('limit_frequency')->default('annual');
            $table->string('limit_basis')->default('individual');
        
            // --- Waiting Period ---
            $table->string('waiting_period_type')->default('general');
        
            // --- Claims Rules ---
            $table->boolean('requires_preauth')->default(false);
            $table->boolean('requires_referral')->default(false);
            $table->json('required_documents')->nullable();
        
            // --- Member Applicability ---
            $table->json('applicable_member_types')->nullable();
        
            // --- Display ---
            $table->text('description')->nullable();
            $table->text('terms_conditions')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
        
            // --- Audit ---
            $table->timestamps();
            $table->softDeletes();
        
            // --- Indexes ---
            $table->index(['category_id', 'is_active']);
            $table->index('parent_id');
        
            // --- Foreign Keys (DEFINED LAST) ---
            $table->foreign('category_id')
                ->references('id')
                ->on('med_benefit_categories')
                ->cascadeOnDelete();
        
            $table->foreign('parent_id')
                ->references('id')
                ->on('med_benefits')
                ->nullOnDelete();
        });
        
    }

    public function down(): void
    {
        Schema::dropIfExists('med_benefits');
        Schema::dropIfExists('med_benefit_categories');
    }
};