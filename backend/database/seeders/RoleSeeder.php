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
        $systemAdmin = Role::create(['name' => 'System Administrator', 'guard_name' => 'web']);
        $systemAdmin->givePermissionTo(Permission::where('guard_name', 'web')->get());

        // Auditor - Read-only access to audit trails
        $auditor = Role::create(['name' => 'Auditor', 'guard_name' => 'web']);
        $auditor->givePermissionTo(['audit.view', 'audit.export']);

        // Data Protection Officer - Manage user data and compliance
        $dpo = Role::create(['name' => 'Data Protection Officer', 'guard_name' => 'web']);
        $dpo->givePermissionTo([
            'users.view',
            'audit.view',
            'audit.export',
        ]);

        // User Manager - Manage users and their module access
        $userManager = Role::create(['name' => 'User Manager', 'guard_name' => 'web']);
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
        $medicalAdmin = Role::create(['name' => 'Medical Administrator', 'guard_name' => 'medical']);
        $medicalAdmin->givePermissionTo(Permission::where('guard_name', 'medical')->get());

        // Medical Underwriter - Assess applications and manage underwriting
        $underwriter = Role::create(['name' => 'Medical Underwriter', 'guard_name' => 'medical']);
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
        $broker = Role::create(['name' => 'Medical Broker', 'guard_name' => 'medical']);
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
        $claimsOfficer = Role::create(['name' => 'Medical Claims Officer', 'guard_name' => 'medical']);
        $claimsOfficer->givePermissionTo([
            'medical.policies.view',
            'medical.members.view',
            'medical.claims.view',
            'medical.claims.process',
            'medical.claims.approve',
            'medical.claims.reject',
        ]);

        // Corporate Group Administrator - Manage their own corporate group
        $corporateAdmin = Role::create(['name' => 'Corporate Group Administrator', 'guard_name' => 'medical']);
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
        $productManager = Role::create(['name' => 'Medical Product Manager', 'guard_name' => 'medical']);
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
            'medical.rate_cards.view',
            'medical.rate_cards.create',
            'medical.rate_cards.update',
            'medical.rate_cards.activate',
            'medical.rate_cards.deactivate',
            'medical.addons.view',
            'medical.addons.create',
            'medical.addons.update',
        ]);

        // =========================================================================
        // LIFE MODULE ROLES (life guard) - For future
        // =========================================================================

        $lifeAdmin = Role::create(['name' => 'Life Administrator', 'guard_name' => 'life']);
        $lifeAdmin->givePermissionTo(Permission::where('guard_name', 'life')->get());

        // =========================================================================
        // CREATE DEFAULT SYSTEM ADMIN USER
        // =========================================================================

        $adminUser = User::create([
            'email' => 'admin@flexcare.zm',
            'username' => 'admin',
            'password' => bcrypt('password'), // Change in production!
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
