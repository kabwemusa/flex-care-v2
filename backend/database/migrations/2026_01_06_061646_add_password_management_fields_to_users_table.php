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
        Schema::table('users', function (Blueprint $table) {
            // Password management fields
            $table->timestamp('password_changed_at')->nullable()->after('password')
                ->comment('When the password was last changed');
            $table->timestamp('password_expires_at')->nullable()->after('password_changed_at')
                ->comment('When the password expires');
            $table->boolean('force_password_change')->default(false)->after('password_expires_at')
                ->comment('Force user to change password on next login');
            $table->unsignedInteger('failed_login_attempts')->default(0)->after('force_password_change')
                ->comment('Count of consecutive failed login attempts');
            $table->timestamp('locked_until')->nullable()->after('failed_login_attempts')
                ->comment('Account locked until this timestamp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'password_changed_at',
                'password_expires_at',
                'force_password_change',
                'failed_login_attempts',
                'locked_until',
            ]);
        });
    }
};
