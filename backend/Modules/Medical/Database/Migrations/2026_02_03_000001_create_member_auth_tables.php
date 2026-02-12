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
        // OTPs for member verification (login, application verification, sensitive actions)
        Schema::create('med_member_otps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email')->index();
            $table->string('phone')->nullable()->index();
            $table->string('code', 6);
            $table->string('purpose', 50); // login, verify_email, verify_phone, confirm_action
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['email', 'purpose', 'expires_at']);
        });

        // Member sessions/tokens
        Schema::create('med_member_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('member_id')->constrained('med_members')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('device_name')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['member_id', 'expires_at']);
        });

        // Application verification tokens (for tracking without login)
        Schema::create('med_application_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('application_id')->constrained('med_applications')->cascadeOnDelete();
            $table->string('email')->index();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['email', 'expires_at']);
        });

        // Add portal-specific fields to members if not exists
        if (!Schema::hasColumn('med_members', 'portal_password')) {
            Schema::table('med_members', function (Blueprint $table) {
                $table->string('portal_password')->nullable()->after('has_portal_access');
                $table->timestamp('portal_password_set_at')->nullable()->after('portal_password');
                $table->timestamp('portal_last_login_at')->nullable()->after('portal_password_set_at');
                $table->string('portal_last_login_ip', 45)->nullable()->after('portal_last_login_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('med_application_tokens');
        Schema::dropIfExists('med_member_tokens');
        Schema::dropIfExists('med_member_otps');

        if (Schema::hasColumn('med_members', 'portal_password')) {
            Schema::table('med_members', function (Blueprint $table) {
                $table->dropColumn([
                    'portal_password',
                    'portal_password_set_at',
                    'portal_last_login_at',
                    'portal_last_login_ip',
                ]);
            });
        }
    }
};
