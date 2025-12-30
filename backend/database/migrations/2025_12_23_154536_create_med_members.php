<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // public function up(): void
    // {
    //     Schema::create('med_members', function (Blueprint $table) {
    //         $table->uuid('id')->primary();
            
    //         // --- Relationships ---
    //         $table->uuid('policy_id');
    //         $table->uuid('principal_id')->nullable(); // Only populated for Dependants

    //         // $table->foreign('policy_id')->references('id')->on('med_policies')->cascadeOnDelete();
    //         $table->foreign('principal_id')->references('id')->on('med_members');

    //         // --- Identity ---
    //         $table->string('member_number')->unique()->index(); // MEM-2025-XXXX
    //         $table->string('employee_number')->nullable()->index(); // Client's HR ID
            
    //         $table->string('title', 10)->nullable();
    //         $table->string('first_name');
    //         $table->string('last_name');
    //         $table->string('nrc')->nullable()->index(); // National ID
    //         $table->date('dob');
    //         $table->enum('gender', ['Male', 'Female']);
    //         $table->enum('marital_status', ['Single', 'Married', 'Divorced', 'Widowed'])->nullable();
            
    //         // --- Contact & Media ---
    //         $table->string('phone')->nullable();
    //         $table->string('email')->nullable();
    //         $table->string('profile_photo_path')->nullable(); // For Printing Cards

    //         // --- Insurance Logic ---
    //         $table->enum('member_type', ['Principal', 'Dependant'])->index();
    //         $table->string('relationship')->nullable(); // Self, Spouse, Child, Parent
            
    //         // --- Status & Dates ---
    //         $table->enum('status', ['Active', 'Suspended', 'Exited'])->default('Active');
    //         // $table->date('join_date');
    //         $table->date('exit_date')->nullable();

    //         $table->timestamps();
    //         $table->softDeletes();
    //     });
    // }

    // public function down(): void
    // {
    //     Schema::dropIfExists('med_members');
    // }
};