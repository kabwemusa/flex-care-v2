import { Component, signal, computed, inject } from '@angular/core';
import { NavItem, Shell, filterNavigation } from 'shared';
import { RouterModule } from '@angular/router';
import { AuthService, MEDICAL_PERMISSIONS, MODULES } from 'core-auth';

@Component({
  selector: 'lib-medical-main',
  standalone: true,
  imports: [Shell, RouterModule],
  templateUrl: './medical-main.html',
  styleUrl: './medical-main.css',
})
export class MedicalMain {
  private readonly authService = inject(AuthService);

  private allNavItems = signal<NavItem[]>([
    {
      href: '/',
      label: 'Dashboard',
      icon: 'dashboard',
    },
    {
      href: '/admin/users',
      label: 'User Management',
      icon: 'group',
      requireSystemAdmin: true,
    },
    {
      href: '/admin/roles',
      label: 'Roles & Permissions',
      icon: 'badge',
      requireSystemAdmin: true,
    },
    {
      label: 'Product Setup',
      icon: 'category',
      requiredModule: MODULES.MEDICAL,
      children: [
        {
          href: '/schemes',
          label: 'Schemes',
          icon: 'account_tree',
          requiredPermissions: [MEDICAL_PERMISSIONS.SCHEMES_VIEW],
        },
        {
          href: '/plans',
          label: 'Plans',
          icon: 'description',
          requiredPermissions: [MEDICAL_PERMISSIONS.PLANS_VIEW],
        },
        {
          label: 'Plan Configuration',
          icon: 'tune',
          children: [
            {
              href: '/benefits',
              label: 'Benefits Catalog',
              requiredPermissions: [MEDICAL_PERMISSIONS.PLANS_VIEW], // Using plans as proxy since no separate benefits permission
            },
            {
              href: '/addons',
              label: 'Add-ons Catalog',
              requiredPermissions: [MEDICAL_PERMISSIONS.ADDONS_VIEW],
            },
          ],
        },
      ],
    },
    {
      label: 'Pricing & Rating',
      icon: 'payments',
      requiredModule: MODULES.MEDICAL,
      children: [
        {
          href: '/rate-cards',
          label: 'Rate Cards',
          icon: 'calculate',
          requiredPermissions: [MEDICAL_PERMISSIONS.RATE_CARDS_VIEW],
        },
        {
          href: '/discounts',
          label: 'Discount Rules',
          icon: 'local_offer',
          requiredPermissions: [MEDICAL_PERMISSIONS.PREMIUM_VIEW], // Using premium as proxy
        },
        {
          href: '/loading-rule',
          label: 'Loading Rules',
          icon: 'trending_up',
          requiredPermissions: [MEDICAL_PERMISSIONS.UNDERWRITING_VIEW], // Using underwriting as proxy
        },
      ],
    },
    {
      label: 'Sales & Underwriting',
      icon: 'assignment',
      requiredModule: MODULES.MEDICAL,
      children: [
        {
          href: '/applications',
          label: 'Applications',
          icon: 'post_add',
          requiredPermissions: [MEDICAL_PERMISSIONS.APPLICATIONS_VIEW],
        },
        {
          href: '/groups',
          label: 'Corporate Groups',
          icon: 'business',
          requiredPermissions: [MEDICAL_PERMISSIONS.GROUPS_VIEW],
        },
      ],
    },
    {
      label: 'Policy Management',
      icon: 'shield',
      requiredModule: MODULES.MEDICAL,
      children: [
        {
          href: '/policies',
          label: 'Policies',
          icon: 'policy',
          requiredPermissions: [MEDICAL_PERMISSIONS.POLICIES_VIEW],
        },
        {
          href: '/members',
          label: 'Members',
          icon: 'group',
          requiredPermissions: [MEDICAL_PERMISSIONS.MEMBERS_VIEW],
        },
        {
          href: '/claims',
          label: 'Claims',
          icon: 'receipt_long',
          requiredPermissions: [MEDICAL_PERMISSIONS.CLAIMS_VIEW],
        },
      ],
    },
    {
      label: 'Billing',
      icon: 'account_balance',
      requiredModule: MODULES.MEDICAL,
      children: [
        {
          href: '/billing/invoices',
          label: 'Invoices',
          icon: 'receipt',
          requiredPermissions: [MEDICAL_PERMISSIONS.BILLING_VIEW],
        },
        {
          href: '/billing/payments',
          label: 'Payments',
          icon: 'payments',
          requiredPermissions: [MEDICAL_PERMISSIONS.BILLING_VIEW],
        },
      ],
    },
  ]);

  // Filtered navigation items based on user permissions
  navItems = computed(() => filterNavigation(this.allNavItems(), this.authService));
}
