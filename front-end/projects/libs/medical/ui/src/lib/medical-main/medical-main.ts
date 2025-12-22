import { Component, signal } from '@angular/core';
import { NavItem, Shell } from 'shared';
import { RouterModule } from '@angular/router';

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
      icon: 'dashboard', // More modern than 'home' for enterprise apps
    },
    {
      href: '/schemes',
      label: 'Schemes',
      icon: 'account_balance', // Represents the "Institution" or Umbrella entity
    },
    {
      href: '/plans',
      label: 'Plans',
      icon: 'reorder', // Represents the stacked tiers (Gold, Silver, Bronze)
    },
    {
      href: '/features',
      label: 'Features',
      icon: 'medical_services', // Specifically represents clinical/medical benefits
    },
    {
      href: '/addons',
      label: 'Addons',
      icon: 'extension',
    },
    {
      href: '/rate-cards',
      label: 'Rate Cards',
      icon: 'request_quote',
    },
    {
      href: '/discounts',
      label: 'Discounts',
      icon: 'percent',
    },
  ]);
}
