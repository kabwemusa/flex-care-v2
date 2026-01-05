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
        Schema::create('med_users', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Link to global IAM user
            $table->foreignUuid('iam_user_id')->constrained('users')->onDelete('cascade');

            // Medical-specific user context
            $table->string('employee_number')->nullable()->index();
            $table->string('department')->nullable();
            $table->date('hire_date')->nullable();
            $table->string('job_title')->nullable();
            $table->uuid('supervisor_id')->nullable()->comment('Medical user ID of supervisor');

            // Context for row-level security (Corporate Group Admin)
            $table->uuid('context_group_id')->nullable()->comment('If set, user can only access this group');

            // Status
            $table->boolean('is_active')->default(true);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique('iam_user_id');
            $table->index('is_active');
            $table->index('context_group_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('med_users');
    }
};
