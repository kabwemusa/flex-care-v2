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
        // Approval Requests - runtime approval instances
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workflow_id');
            $table->string('entity_type'); // Polymorphic: Application, Claim, etc.
            $table->uuid('entity_id');
            $table->uuid('current_step_id')->nullable(); // Current step waiting for approval
            $table->string('status')->default('pending'); // pending, approved, rejected, cancelled
            $table->uuid('initiated_by');
            $table->timestamp('completed_at')->nullable();
            $table->text('final_remarks')->nullable();
            $table->timestamps();

            $table->foreign('workflow_id')->references('id')->on('approval_workflows')->restrictOnDelete();
            $table->foreign('current_step_id')->references('id')->on('approval_steps')->nullOnDelete();
            $table->foreign('initiated_by')->references('id')->on('users')->restrictOnDelete();

            $table->index(['entity_type', 'entity_id']);
            $table->index('status');
            $table->index('initiated_by');
            $table->index('current_step_id');
        });

        // Approval Histories - audit trail of all approval actions
        Schema::create('approval_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('request_id');
            $table->uuid('step_id');
            $table->string('action'); // approved, rejected, returned
            $table->uuid('actioned_by');
            $table->text('comments')->nullable();
            $table->timestamp('actioned_at');
            $table->timestamps();

            $table->foreign('request_id')->references('id')->on('approval_requests')->cascadeOnDelete();
            $table->foreign('step_id')->references('id')->on('approval_steps')->restrictOnDelete();
            $table->foreign('actioned_by')->references('id')->on('users')->restrictOnDelete();

            $table->index('request_id');
            $table->index('step_id');
            $table->index('actioned_by');
            $table->index('action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_histories');
        Schema::dropIfExists('approval_requests');
    }
};
