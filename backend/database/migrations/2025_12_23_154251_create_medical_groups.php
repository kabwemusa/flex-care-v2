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
    //     Schema::create('med_groups', function (Blueprint $table) {
    //         // 1. Identity & System Keys
    //         $table->uuid('id')->primary();
    //         $table->string('code')->unique()->index(); // e.g., GRP-001. Essential for linking claims/members manually.
    //         $table->string('name')->index(); // Indexed for fast searching during underwriting.
            
    //         // 2. Compliance & Legal (KYC)
    //         $table->string('tpin')->unique()->nullable(); // Tax Payer ID
    //         $table->string('registration_number')->unique()->nullable(); // PACRA/Company Reg No.
    //         $table->string('industry_sector')->nullable(); // e.g., "Mining", "Banking" (Used for Risk Rating).
            
    //         // 3. Primary Contact & Communication
    //         $table->string('contact_person_name');
    //         $table->string('contact_person_role')->nullable(); // e.g., "HR Manager" or "Finance Director".
    //         $table->string('email')->index(); // Indexed for login or notifications.
    //         $table->string('phone_main');
    //         $table->string('phone_secondary')->nullable();
            
    //         // 4. Location Details
    //         $table->text('physical_address')->nullable();
    //         $table->text('postal_address')->nullable(); // Important for sending physical contracts/cards.
    //         $table->string('city')->nullable();
    //         $table->string('province')->nullable();

    //         // 5. Account Management & Dates
    //         // Instead of just 'is_active', use a status string to handle 'Suspended' (due to non-payment).
    //         $table->string('status')->default('active')->index(); // active, suspended, terminated, pending.
    //         $table->date('joined_date')->nullable(); // Actual start of the business relationship.
            
    //         // Optional: Internal link to the staff member managing this account
    //         // $table->foreignId('account_manager_id')->nullable()->constrained('users'); 

    //         // 6. System Timestamps
    //         $table->timestamps();
    //         $table->softDeletes(); // Auditing requirement: never hard delete corporate history.
    //     });
    // }

    // /**
    //  * Reverse the migrations.
    //  */
    // public function down(): void
    // {
    //     Schema::dropIfExists('med_groups');
    // }
    }
};