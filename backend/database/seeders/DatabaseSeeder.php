<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Medical\Database\Seeders\FraudRuleSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed permissions first, then roles (roles need permissions)
        $this->call([
            // PermissionSeeder::class,
            // RoleSeeder::class,
            FraudRuleSeeder::class,
        ]);
    }
}
