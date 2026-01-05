import { Component, inject, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';
import { MatIconModule } from '@angular/material/icon';
import { AuthService, ModuleCode } from 'core-auth';

interface ModuleCard {
  code: ModuleCode;
  name: string;
  icon: string;
  description: string;
  route: string;
}

@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [CommonModule, MatIconModule],
  templateUrl: './dashboard.component.html',
})
export class DashboardComponent {
  private authService = inject(AuthService);
  private router = inject(Router);

  // All possible modules
  private allModules: ModuleCard[] = [
    {
      code: 'medical',
      name: 'Medical Insurance',
      icon: 'medical_services',
      description: 'Manage medical insurance policies, claims, and members',
      route: '/schemes',
    },
    {
      code: 'life',
      name: 'Life Insurance',
      icon: 'favorite',
      description: 'Handle life insurance policies and beneficiaries',
      route: '/life/policies',
    },
    {
      code: 'motor',
      name: 'Motor Insurance',
      icon: 'directions_car',
      description: 'Process motor vehicle insurance and claims',
      route: '/motor/policies',
    },
    {
      code: 'travel',
      name: 'Travel Insurance',
      icon: 'flight',
      description: 'Manage travel insurance coverage and claims',
      route: '/travel/policies',
    },
  ];

  // Get user's available modules
  availableModules = computed(() => {
    const userModules = this.authService.modules();
    const isAdmin = this.authService.isSystemAdmin();

    // System admins see all modules
    if (isAdmin) {
      return this.allModules;
    }

    // Regular users only see their assigned modules
    return this.allModules.filter((m) => userModules.includes(m.code));
  });

  isSystemAdmin = this.authService.isSystemAdmin;

  displayName = computed(() => {
    const user = this.authService.user();
    return user?.username || user?.email || 'User';
  });

  /**
   * Check if module is currently active/selected
   */
  isModuleActive(moduleCode: ModuleCode): boolean {
    // Could track current module in a service
    return false;
  }

  /**
   * Navigate to module
   */
  selectModule(moduleCode: ModuleCode): void {
    const module = this.allModules.find((m) => m.code === moduleCode);
    if (module) {
      this.router.navigate([module.route]);
    }
  }
}
