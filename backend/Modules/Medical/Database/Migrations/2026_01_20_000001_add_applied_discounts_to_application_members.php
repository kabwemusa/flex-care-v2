<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add applied_discounts column to med_application_members table
     * to support discount application during underwriting.
     */
    public function up(): void
    {
        Schema::table('med_application_members', function (Blueprint $table) {
            $table->json('applied_discounts')->nullable()->after('applied_exclusions');
        });
    }

    public function down(): void
    {
        Schema::table('med_application_members', function (Blueprint $table) {
            $table->dropColumn('applied_discounts');
        });
    }
};
