<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // =========================================================================
        // GLOBAL PERMISSIONS (web guard - for admin panel)
        // =========================================================================

        $globalPermissions = [
            // User Management
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'users.activate',
            'users.deactivate',

            // Module Access Management
            'module_access.grant',
            'module_access.revoke',

            // Role & Permission Management
            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',
            'roles.assign',

            // Audit Trail
            'audit.view',
            'audit.export',

            // System Settings
            'settings.view',
            'settings.update',
        ];

        foreach ($globalPermissions as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'web']);
        }

        // =========================================================================
        // MEDICAL MODULE PERMISSIONS (medical guard)
        // =========================================================================

        $medicalPermissions = [
            // Scheme Management
            'medical.schemes.view',
            'medical.schemes.create',
            'medical.schemes.update',
            'medical.schemes.delete',
            'medical.schemes.activate',
            'medical.schemes.deactivate',

            // Plan Management
            'medical.plans.view',
            'medical.plans.create',
            'medical.plans.update',
            'medical.plans.delete',
            'medical.plans.configure',

            // Rate Card Management
            'medical.rate_cards.view',
            'medical.rate_cards.create',
            'medical.rate_cards.update',
            'medical.rate_cards.delete',
            'medical.rate_cards.activate',
            'medical.rate_cards.deactivate',

            // Addon Management
            'medical.addons.view',
            'medical.addons.create',
            'medical.addons.update',
            'medical.addons.delete',

            // Application/Quote Management
            'medical.applications.view',
            'medical.applications.create',
            'medical.applications.update',
            'medical.applications.delete',
            'medical.applications.submit',
            'medical.applications.quote',

            // Underwriting
            'medical.underwriting.view',
            'medical.underwriting.assess',
            'medical.underwriting.approve',
            'medical.underwriting.reject',
            'medical.underwriting.add_loading',
            'medical.underwriting.add_exclusion',

            // Policy Management
            'medical.policies.view',
            'medical.policies.create',
            'medical.policies.update',
            'medical.policies.renew',
            'medical.policies.suspend',
            'medical.policies.cancel',
            'medical.policies.reinstate',

            // Member Management
            'medical.members.view',
            'medical.members.add',
            'medical.members.update',
            'medical.members.remove',
            'medical.members.suspend',
            'medical.members.exit',

            // Group Management
            'medical.groups.view',
            'medical.groups.create',
            'medical.groups.update',
            'medical.groups.delete',

            // Claims (for future implementation)
            'medical.claims.view',
            'medical.claims.process',
            'medical.claims.approve',
            'medical.claims.reject',

            // Reports
            'medical.reports.view',
            'medical.reports.export',

            // Premium Calculation
            'medical.premium.view',
            'medical.premium.calculate',
            'medical.premium.override',
        ];

        foreach ($medicalPermissions as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'medical']);
        }

        // =========================================================================
        // LIFE MODULE PERMISSIONS (life guard) - For future
        // =========================================================================

        $lifePermissions = [
            'life.policies.view',
            'life.policies.create',
            'life.policies.update',
            'life.applications.view',
            'life.applications.create',
            'life.underwriting.assess',
        ];

        foreach ($lifePermissions as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'life']);
        }

        $this->command->info('Permissions created successfully!');
    }
}
