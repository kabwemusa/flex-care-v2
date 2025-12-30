import { Component, signal } from '@angular/core';
import { NavItem, Shell } from 'shared';
import { RouterModule } from '@angular/router';
import { space } from 'postcss/lib/list';

@Component({
  selector: 'lib-medical-main',
  standalone: true,
  imports: [Shell, RouterModule],
  templateUrl: './medical-main.html',
  styleUrl: './medical-main.css',
})
export class MedicalMain {
  navItems = signal<NavItem[]>([
    {
      href: '/',
      label: 'Dashboard',
      icon: 'space_dashboard',
    },
    {
      label: 'Product Factory',
      icon: 'inventory_2',
      children: [
        { href: '/schemes', label: 'Schemes' },
        { href: '/plans', label: 'Insurance Plans' }, // Click plan → /plans/:id
        { href: '/benefits', label: 'Benefits Catalog' }, // Master list
        { href: '/addons', label: 'Addons Catalog' },
      ],
    },
    {
      label: 'Pricing Engine',
      icon: 'calculate',
      children: [
        { href: '/rate-cards', label: 'Rate Cards' },
        { href: '/discounts', label: 'Discounts' },
        { href: '/loading-rule', label: 'Loading Rules' },
      ],
    },
    {
      label: 'Operations',
      icon: 'groups',
      children: [
        { href: '/groups', label: 'Corporate Clients' },
        { href: '/policies', label: 'Policy Administration' },
        { href: '/members', label: 'Member Registry' },
        { href: '/claims', label: 'Claims (Coming Soon)' },
      ],
    },
  ]);
}
