import { Component, signal, computed, inject } from '@angular/core';
import { NavItem, Shell, filterNavigation } from 'shared';
import { RouterModule } from '@angular/router';
import { AuthService } from 'core-auth';

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
      requiredModule: 'medical',
      children: [
        {
          href: '/schemes',
          label: 'Schemes',
          icon: 'account_tree',
          guard: 'medical',
          requiredPermissions: ['view schemes'],
        },
        {
          href: '/plans',
          label: 'Plans',
          icon: 'description',
          guard: 'medical',
          requiredPermissions: ['view plans'],
        },
        {
          label: 'Plan Configuration',
          icon: 'tune',
          children: [
            {
              href: '/benefits',
              label: 'Benefits Catalog',
              guard: 'medical',
              requiredPermissions: ['view benefits'],
            },
            {
              href: '/addons',
              label: 'Add-ons Catalog',
              guard: 'medical',
              requiredPermissions: ['view addons'],
            },
          ],
        },
      ],
    },
    {
      label: 'Pricing & Rating',
      icon: 'payments',
      requiredModule: 'medical',
      children: [
        {
          href: '/rate-cards',
          label: 'Rate Cards',
          icon: 'calculate',
          guard: 'medical',
          requiredPermissions: ['view rate cards'],
        },
        {
          href: '/discounts',
          label: 'Discount Rules',
          icon: 'local_offer',
          guard: 'medical',
          requiredPermissions: ['view discounts'],
        },
        {
          href: '/loading-rule',
          label: 'Loading Rules',
          icon: 'trending_up',
          guard: 'medical',
          requiredPermissions: ['view loading rules'],
        },
      ],
    },
    {
      label: 'Sales & Underwriting',
      icon: 'assignment',
      requiredModule: 'medical',
      children: [
        {
          href: '/applications',
          label: 'Applications',
          icon: 'post_add',
          guard: 'medical',
          requiredPermissions: ['view applications'],
        },
        {
          href: '/groups',
          label: 'Corporate Groups',
          icon: 'business',
          guard: 'medical',
          requiredPermissions: ['view groups'],
        },
      ],
    },
    {
      label: 'Policy Management',
      icon: 'shield',
      requiredModule: 'medical',
      children: [
        {
          href: '/policies',
          label: 'Policies',
          icon: 'policy',
          guard: 'medical',
          requiredPermissions: ['view policies'],
        },
        {
          href: '/members',
          label: 'Members',
          icon: 'group',
          guard: 'medical',
          requiredPermissions: ['view members'],
        },
      ],
    },
  ]);

  // Filtered navigation items based on user permissions
  navItems = computed(() => filterNavigation(this.allNavItems(), this.authService));
}
