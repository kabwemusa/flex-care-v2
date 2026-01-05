import { Component, Input, Output, EventEmitter, inject, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatMenuModule } from '@angular/material/menu';
import { MatBadgeModule } from '@angular/material/badge';
import { MatDividerModule } from '@angular/material/divider';
import { MatTooltipModule } from '@angular/material/tooltip';
import { AuthService } from 'core-auth';
import { Router } from '@angular/router';

@Component({
  selector: 'lib-top-nav',
  standalone: true,
  imports: [
    CommonModule,
    MatButtonModule,
    MatIconModule,
    MatMenuModule,
    MatBadgeModule,
    MatDividerModule,
    MatTooltipModule,
  ],
  templateUrl: './top-nav.html',
  styles: [
    `
      :host {
        display: contents;
      }
    `,
  ],
})
export class TopNav {
  private authService = inject(AuthService);
  private router = inject(Router);

  @Input() sidebarCollapsed = false;
  @Output() toggleSidebar = new EventEmitter<void>();

  // Get user from AuthService
  user = this.authService.user;

  // Get user initials for avatar
  userInitials = computed(() => {
    const user = this.user();
    if (!user) return '??';

    const username = user.username || user.email;
    const parts = username.split(/[\s@.]+/);

    if (parts.length >= 2) {
      return (parts[0][0] + parts[1][0]).toUpperCase();
    }

    return username.substring(0, 2).toUpperCase();
  });

  // Get display name
  displayName = computed(() => {
    const user = this.user();
    return user?.username || user?.email || 'User';
  });

  // Get user role/type display
  userRole = computed(() => {
    const user = this.user();
    if (user?.is_system_admin) return 'System Admin';

    const modules = this.authService.modules();
    if (modules.length > 0) {
      return modules.map(m => m.charAt(0).toUpperCase() + m.slice(1)).join(', ');
    }

    return 'User';
  });

  /**
   * Handle logout
   */
  onLogout(): void {
    this.authService.logout().subscribe({
      next: () => {
        this.router.navigate(['/login']);
      },
      error: (error) => {
        console.error('Logout error:', error);
        // Still navigate to login even on error
        this.router.navigate(['/login']);
      }
    });
  }
}
