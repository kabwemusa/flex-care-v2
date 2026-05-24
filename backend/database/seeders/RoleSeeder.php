<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use App\Models\UserModuleAccess;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // =========================================================================
        // GLOBAL ROLES (web guard - for admin panel)
        // =========================================================================

        // System Administrator - God mode, access to everything
        $systemAdmin = Role::findOrCreate( 'System Administrator',  'web');
        $systemAdmin->givePermissionTo(Permission::where('guard_name', 'web')->get());

        // Auditor - Read-only access to audit trails
        $auditor = Role::findOrCreate( 'Auditor','web');
        $auditor->givePermissionTo(['audit.view', 'audit.export']);

        // Data Protection Officer - Manage user data and compliance
        $dpo = Role::findOrCreate('Data Protection Officer','web');
        $dpo->givePermissionTo([
            'users.view',
            'audit.view',
            'audit.export',
        ]);

        // User Manager - Manage users and their module access
        $userManager = Role::findOrCreate('User Manager', 'web');
        $userManager->givePermissionTo([
            'users.view',
            'users.create',
            'users.update',
            'users.activate',
            'users.deactivate',
            'module_access.grant',
            'module_access.revoke',
            'roles.view',
            'roles.assign',
        ]);

        // =========================================================================
        // MEDICAL MODULE ROLES (medical guard)
        // =========================================================================

        // Medical Administrator - Full access to medical module
        $medicalAdmin = Role::findOrCreate( 'Medical Administrator', 'medical');
        $medicalAdmin->givePermissionTo(Permission::where('guard_name', 'medical')->get());

        // Medical Underwriter - Assess applications and manage underwriting
        $underwriter = Role::findOrCreate('Medical Underwriter','medical');
        $underwriter->givePermissionTo([
            'medical.applications.view',
            'medical.applications.update',
            'medical.underwriting.view',
            'medical.underwriting.assess',
            'medical.underwriting.approve',
            'medical.underwriting.reject',
            'medical.underwriting.add_loading',
            'medical.underwriting.add_exclusion',
            'medical.policies.view',
            'medical.policies.create',
            'medical.members.view',
            'medical.groups.view',
            'medical.premium.view',
            'medical.premium.calculate',
        ]);

        // Medical Broker - Create quotes and applications
        $broker = Role::findOrCreate('Medical Broker',  'medical');
        $broker->givePermissionTo([
            'medical.schemes.view',
            'medical.plans.view',
            'medical.addons.view',
            'medical.applications.view',
            'medical.applications.create',
            'medical.applications.update',
            'medical.applications.quote',
            'medical.applications.submit',
            'medical.groups.view',
            'medical.groups.create',
            'medical.premium.view',
            'medical.premium.calculate',
        ]);

        // Medical Claims Officer - Process claims (for future)
        $claimsOfficer = Role::findOrCreate( 'Medical Claims Officer', 'medical');
        $claimsOfficer->givePermissionTo([
            'medical.policies.view',
            'medical.members.view',
            'medical.claims.view',
            'medical.claims.process',
            'medical.claims.approve',
            'medical.claims.reject',
        ]);

        // Corporate Group Administrator - Manage their own corporate group
        $corporateAdmin = Role::findOrCreate( 'Corporate Group Administrator', 'medical');
        $corporateAdmin->givePermissionTo([
            'medical.policies.view',
            'medical.members.view',
            'medical.members.add',
            'medical.members.update',
            'medical.members.remove',
            'medical.groups.view',
            'medical.groups.update',
        ]);

        // Medical Product Manager - Configure schemes, plans, rates
        $productManager = Role::findOrCreate('Medical Product Manager', 'medical');
        $productManager->givePermissionTo([
            'medical.schemes.view',
            'medical.schemes.create',
            'medical.schemes.update',
            'medical.schemes.activate',
            'medical.schemes.deactivate',
            'medical.plans.view',
            'medical.plans.create',
            'medical.plans.update',
            'medical.plans.configure',
            'medical.benefits.view',
            'medical.benefits.create',
            'medical.benefits.update',
            'medical.rate_cards.view',
            'medical.rate_cards.create',
            'medical.rate_cards.update',
            'medical.rate_cards.activate',
            'medical.rate_cards.deactivate',
            'medical.addons.view',
            'medical.addons.create',
            'medical.addons.update',
            'medical.discounts.view',
            'medical.discounts.create',
            'medical.discounts.update',
            'medical.loading_rules.view',
            'medical.loading_rules.create',
            'medical.loading_rules.update',
        ]);

        // =========================================================================
        // LIFE MODULE ROLES (life guard) - For future
        // =========================================================================

        $lifeAdmin = Role::findOrCreate( 'Life Administrator','life');
        $lifeAdmin->givePermissionTo(Permission::where('guard_name', 'life')->get());

        // =========================================================================
        // CREATE DEFAULT SYSTEM ADMIN USER
        // =========================================================================

        $adminUser = User::create([
            'email' => 'admin@flexcare.zm',
            'username' => 'admin',
            'password' => bcrypt('Testing01!'), // Change in production!
            'is_active' => true,
            'is_system_admin' => true,
        ]);

        $adminUser->assignRole($systemAdmin);

        // Grant admin access to all modules
        UserModuleAccess::create([
            'user_id' => $adminUser->id,
            'module_code' => 'admin',
            'is_active' => true,
            'granted_by' => $adminUser->id,
        ]);

        UserModuleAccess::create([
            'user_id' => $adminUser->id,
            'module_code' => 'medical',
            'is_active' => true,
            'granted_by' => $adminUser->id,
        ]);

        $this->command->info('Roles created successfully!');
        $this->command->info('Default admin user created: admin@flexcare.zm / password');
    }
}
