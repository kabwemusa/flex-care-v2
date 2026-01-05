import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatMenuModule } from '@angular/material/menu';
import { MatDividerModule } from '@angular/material/divider';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatButtonToggleModule } from '@angular/material/button-toggle';
import { MatDialog } from '@angular/material/dialog';

// Core/Shared Imports
import { RoleManagementStore, Role } from 'core-auth';
import { PageHeaderComponent, FeedbackService } from 'shared';

// Dialog Imports
import { RoleDialogComponent } from '../../dialogs/role-dialog/role-dialog.component';

@Component({
  selector: 'app-roles-permissions',
  standalone: true,
  imports: [
    CommonModule,
    MatIconModule,
    MatButtonModule,
    MatMenuModule,
    MatDividerModule,
    MatProgressSpinnerModule,
    MatButtonToggleModule,
    PageHeaderComponent,
  ],
  templateUrl: './roles-permissions.component.html',
})
export class RolesPermissionsComponent implements OnInit {
  readonly store = inject(RoleManagementStore);
  private readonly dialog = inject(MatDialog);
  private readonly feedback = inject(FeedbackService);

  selectedGuard = 'web';
  systemRoles = ['System Administrator', 'User Manager', 'Auditor'];

  ngOnInit() {
    this.loadData();
  }

  /**
   * Load roles and permissions for current guard
   */
  loadData() {
    this.store.loadRoles(this.selectedGuard).subscribe();
    this.store.loadPermissions(this.selectedGuard).subscribe();
  }

  /**
   * Handle guard change
   */
  onGuardChange() {
    this.loadData();
  }

  /**
   * Check if role is a system role
   */
  isSystemRole(role: Role): boolean {
    return this.systemRoles.includes(role.name);
  }

  /**
   * Get permission percentage for progress bar
   */
  getPermissionPercentage(role: Role): number {
    const totalPermissions = this.store.permissionCategories().length * 5; // Approximate
    const rolePermissions = role.permissions?.length || 0;
    return Math.min((rolePermissions / totalPermissions) * 100, 100);
  }

  /**
   * Format permission name for display
   */
  formatPermissionName(name: string): string {
    const parts = name.split('.');
    return parts[parts.length - 1].replace(/_/g, ' ');
  }

  /**
   * Open role create/edit dialog
   */
  openRoleDialog(role?: Role) {
    const dialogRef = this.dialog.open(RoleDialogComponent, {
      maxWidth: '90vw',
      maxHeight: '90vh',
      data: { role, guard: this.selectedGuard },
      disableClose: true,
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (result) {
        this.loadData();
      }
    });
  }

  /**
   * View all permissions for a role
   */
  viewPermissions(role: Role) {
    // Open the same dialog in view mode
    this.openRoleDialog(role);
  }

  /**
   * Delete a role
   */
  deleteRole(role: Role) {
    if (this.isSystemRole(role)) {
      this.feedback.error('Cannot delete system role');
      return;
    }

    this.feedback
      .confirm(
        'Delete Role',
        `Are you sure you want to delete the role "${role.name}"? This action cannot be undone.`
      )
      .then((confirmed) => {
        if (confirmed) {
          this.store.deleteRole(role.id).subscribe({
            next: () => {
              this.feedback.success('Role deleted successfully');
              this.loadData();
            },
            error: (error) => {
              this.feedback.error(error.error?.message || 'Failed to delete role');
            },
          });
        }
      });
  }

  /**
   * Export to CSV
   */
  exportToCsv() {
    this.feedback.error('CSV export coming soon');
  }
}
