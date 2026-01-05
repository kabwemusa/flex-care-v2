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
        Schema::create('user_module_access', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->onDelete('cascade');
            $table->enum('module_code', ['medical', 'life', 'motor', 'travel', 'admin'])
                  ->comment('Module the user can access');
            $table->boolean('is_active')->default(true);
            $table->timestamp('granted_at')->useCurrent();
            $table->uuid('granted_by')->nullable()->comment('User ID who granted access');
            $table->timestamps();

            $table->unique(['user_id', 'module_code']);
            $table->index('module_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_module_access');
    }
};
