import { Component, OnInit, AfterViewInit, ViewChild, inject, effect } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';

// Material Imports
import { MatTableDataSource, MatTableModule } from '@angular/material/table';
import { MatPaginator, MatPaginatorModule, PageEvent } from '@angular/material/paginator';
import { MatSort, MatSortModule } from '@angular/material/sort';
import { MatDialog } from '@angular/material/dialog';
import { MatMenuModule } from '@angular/material/menu';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatDividerModule } from '@angular/material/divider';
import { MatSelectModule } from '@angular/material/select';
import { MatFormFieldModule } from '@angular/material/form-field';

// Core/Shared Imports
import { UserManagementStore, UserListItem, AuthService } from 'core-auth';
import { PageHeaderComponent, FeedbackService } from 'shared';

// Dialog Imports
import { UserDialogComponent } from '../../dialogs/user-dialog/user-dialog.component';
import { RoleAssignmentDialogComponent } from '../../dialogs/role-assignment-dialog/role-assignment-dialog.component';

@Component({
  selector: 'app-user-management',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    MatTableModule,
    MatPaginatorModule,
    MatSortModule,
    MatMenuModule,
    MatIconModule,
    MatButtonModule,
    MatDividerModule,
    MatSelectModule,
    MatFormFieldModule,
    PageHeaderComponent,
  ],
  templateUrl: './user-management.component.html',
})
export class UserManagementComponent implements OnInit, AfterViewInit {
  readonly store = inject(UserManagementStore);
  private readonly authService = inject(AuthService);
  private readonly dialog = inject(MatDialog);
  private readonly feedback = inject(FeedbackService);

  displayedColumns = ['status', 'user', 'modules', 'roles', 'last_login', 'actions'];
  dataSource = new MatTableDataSource<UserListItem>([]);

  @ViewChild(MatPaginator) paginator!: MatPaginator;
  @ViewChild(MatSort) sort!: MatSort;

  // Filters
  searchQuery = '';
  statusFilter: boolean | undefined = undefined;
  moduleFilter: string | undefined = undefined;

  // Pagination
  pageSize = 10;

  constructor() {
    effect(() => {
      this.dataSource.data = this.store.users();
    });
  }

  ngOnInit() {
    this.loadUsers();
  }

  ngAfterViewInit() {
    this.dataSource.sort = this.sort;
  }

  /**
   * Load users with current filters
   */
  loadUsers() {
    this.store
      .loadUsers({
        search: this.searchQuery || undefined,
        is_active: this.statusFilter,
        module: this.moduleFilter,
        per_page: this.pageSize,
        page: this.store.currentPage(),
      })
      .subscribe();
  }

  /**
   * Apply filters
   */
  applyFilters() {
    this.loadUsers();
  }

  /**
   * Clear all filters
   */
  clearFilters() {
    this.searchQuery = '';
    this.statusFilter = undefined;
    this.moduleFilter = undefined;
    this.loadUsers();
  }

  /**
   * Handle page change
   */
  onPageChange(event: PageEvent) {
    this.pageSize = event.pageSize;
    this.store
      .loadUsers({
        search: this.searchQuery || undefined,
        is_active: this.statusFilter,
        module: this.moduleFilter,
        per_page: event.pageSize,
        page: event.pageIndex + 1,
      })
      .subscribe();
  }

  /**
   * Get user initials for avatar
   */
  getUserInitials(user: UserListItem): string {
    const name = user.username || user.email;
    const parts = name.split(/[\s@.]+/);

    if (parts.length >= 2) {
      return (parts[0][0] + parts[1][0]).toUpperCase();
    }

    return name.substring(0, 2).toUpperCase();
  }

  /**
   * Open create/edit user dialog
   */
  openUserDialog(user?: UserListItem) {
    const dialogRef = this.dialog.open(UserDialogComponent, {
      maxWidth: '90vw',
      data: { user },
      disableClose: true,
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (result) {
        this.loadUsers();
      }
    });
  }

  /**
   * Open role assignment dialog
   */
  openRoleDialog(user: UserListItem) {
    const dialogRef = this.dialog.open(RoleAssignmentDialogComponent, {
      maxWidth: '90vw',
      data: { user },
      disableClose: true,
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (result) {
        this.loadUsers();
      }
    });
  }

  /**
   * Open module access dialog
   */
  openModuleAccessDialog(user: UserListItem) {
    // TODO: Open module access dialog
    // this.feedback.showInfo('Module access dialog coming soon');
  }

  /**
   * Toggle user active status
   */
  async toggleUserStatus(user: UserListItem) {
    const action = user.is_active ? 'deactivate' : 'activate';

    const confirmed = await this.feedback.confirm(
      `${action.charAt(0).toUpperCase() + action.slice(1)} User`,
      `Are you sure you want to ${action} this user?`
    );
    if (!confirmed) {
      return;
    }

    const observable = user.is_active
      ? this.store.deactivateUser(user.id)
      : this.store.activateUser(user.id);

    observable.subscribe({
      next: () => {
        this.feedback.success(`User ${action}d successfully`);
        this.loadUsers();
      },
      error: (error) => {
        this.feedback.error(error.error?.message || `Failed to ${action} user`);
      },
    });
  }

  /**
   * Reset user password (admin only)
   */
  async resetPassword(user: UserListItem) {
    const confirmed = await this.feedback.confirm(
      'Reset Password',
      `Are you sure you want to reset the password for ${
        user.username || user.email
      }? A temporary password will be generated.`
    );

    if (!confirmed) {
      return;
    }

    this.store.resetPassword(user.id).subscribe({
      next: async (response) => {
        const tempPassword = response.data.temporary_password;
        this.feedback.success('Password reset successfully!');

        // Show temporary password with option to copy
        const shouldCopy = await this.feedback.confirm(
          'Temporary Password Generated',
          `Temporary Password: ${tempPassword}\n\nIMPORTANT: Copy this password and share it securely with the user. The user will be required to change it on their next login.\n\nClick "Confirm" to copy to clipboard.`
        );

        if (shouldCopy && navigator.clipboard) {
          try {
            await navigator.clipboard.writeText(tempPassword);
            this.feedback.success('Password copied to clipboard!');
          } catch (err) {
            this.feedback.error('Failed to copy to clipboard. Please copy manually.');
          }
        }

        this.loadUsers();
      },
      error: (error) => {
        this.feedback.error(error.error?.message || 'Failed to reset password');
      },
    });
  }

  /**
   * Delete user
   */
  async deleteUser(user: UserListItem) {
    // Prevent deletion of active users
    if (user.is_active) {
      this.feedback.error('Cannot delete an active user. Please deactivate the user first.');
      return;
    }

    const confirmed = await this.feedback.confirm(
      'Delete User',
      `Are you sure you want to delete ${
        user.username || user.email
      }? This action cannot be undone.`
    );

    if (!confirmed) {
      return;
    }

    this.store.deleteUser(user.id).subscribe({
      next: () => {
        this.feedback.success('User deleted successfully');
        this.loadUsers();
      },
      error: (error) => {
        this.feedback.error(error.error?.message || 'Failed to delete user');
      },
    });
  }

  /**
   * Check if user can perform action based on permissions
   */
  canPerformAction(action: string): boolean {
    // System admins can do everything
    if (this.authService.isSystemAdmin()) {
      return true;
    }

    // Map actions to required permissions
    const actionPermissions: Record<string, string[]> = {
      create: ['users.create'],
      edit: ['users.update'],
      delete: ['users.delete'],
      manage_roles: ['roles.assign'],
      reset_password: ['users.update'], // Requires user update permission
      toggle_status: ['users.update'],
    };

    const requiredPermissions = actionPermissions[action] || [];
    return this.authService.hasAnyPermission(requiredPermissions);
  }

  /**
   * Export to CSV
   */
  exportToCsv() {
    // TODO: Implement CSV export
    // this.feedback.showInfo('CSV export coming soon');
  }
}
