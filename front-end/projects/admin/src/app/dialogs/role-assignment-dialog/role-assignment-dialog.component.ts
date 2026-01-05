import { Component, Inject, inject, signal, computed, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';

// Material Imports
import { MatDialogRef, MAT_DIALOG_DATA, MatDialogModule } from '@angular/material/dialog';
import { MAT_FORM_FIELD_DEFAULT_OPTIONS, MatFormFieldModule } from '@angular/material/form-field';
import { MatSelectModule } from '@angular/material/select';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { MatDividerModule } from '@angular/material/divider';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';

// Core Imports
import { UserManagementStore, UserListItem, Role } from 'core-auth';
import { FeedbackService } from 'shared';

interface DialogData {
  user: UserListItem;
}

@Component({
  selector: 'app-role-assignment-dialog',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    MatDialogModule,
    MatFormFieldModule,
    MatSelectModule,
    MatButtonModule,
    MatIconModule,
    MatCheckboxModule,
    MatDividerModule,
    MatProgressSpinnerModule,
  ],
  providers: [{ provide: MAT_FORM_FIELD_DEFAULT_OPTIONS, useValue: { floatLabel: 'auto' } }],
  templateUrl: './role-assignment-dialog.component.html',
})
export class RoleAssignmentDialogComponent implements OnInit {
  readonly store = inject(UserManagementStore);
  private readonly fb = inject(FormBuilder);
  private readonly feedback = inject(FeedbackService);
  readonly dialogRef = inject(MatDialogRef<RoleAssignmentDialogComponent>);

  readonly moduleScopes = [
    { value: 'web', label: 'Global (Web)', icon: 'public' },
    { value: 'medical', label: 'Medical', icon: 'medical_services' },
    { value: 'life', label: 'Life', icon: 'favorite' },
    { value: 'motor', label: 'Motor', icon: 'directions_car' },
    { value: 'travel', label: 'Travel', icon: 'flight' },
  ];

  user!: UserListItem;

  // Form
  roleForm!: FormGroup;

  // State
  private allRoles = signal<Role[]>([]);
  private selectedRolesSignal = signal<string[]>([]);

  // Computed
  availableRoles = computed(() => {
    const guard = this.roleForm?.get('guard')?.value || 'web';
    return this.allRoles().filter((role) => role.guard_name === guard);
  });

  selectedRoles = computed(() => this.selectedRolesSignal());

  constructor(@Inject(MAT_DIALOG_DATA) public data: DialogData) {
    this.user = data.user;
  }

  ngOnInit(): void {
    this.initForm();
    this.loadRolesForGuard('web');

    // Load user's current roles
    if (this.user.roles && this.user.roles.length > 0) {
      const roleNames = this.user.roles.map((r) => r.name);
      this.selectedRolesSignal.set(roleNames);
    }
  }

  private initForm(): void {
    this.roleForm = this.fb.group({
      guard: ['web', Validators.required],
    });
  }

  /**
   * Load roles when guard changes
   */
  onGuardChange(): void {
    const guard = this.roleForm.get('guard')?.value;
    if (guard) {
      this.loadRolesForGuard(guard);
    }
  }

  /**
   * Load roles for a specific guard
   */
  private loadRolesForGuard(guard: string): void {
    this.store.loadRoles(guard).subscribe({
      next: () => {
        this.allRoles.set(this.store.roles());
      },
      error: (error) => {
        this.feedback.error(error.error?.message || 'Failed to load roles');
      },
    });
  }

  /**
   * Check if a role is selected
   */
  isRoleSelected(roleName: string): boolean {
    return this.selectedRolesSignal().includes(roleName);
  }

  /**
   * Toggle role selection
   */
  toggleRole(roleName: string, checked: boolean): void {
    const current = this.selectedRolesSignal();
    if (checked) {
      if (!current.includes(roleName)) {
        this.selectedRolesSignal.set([...current, roleName]);
      }
    } else {
      this.selectedRolesSignal.set(current.filter((r) => r !== roleName));
    }
  }

  /**
   * Save role assignments
   */
  onSave(): void {
    const guard = this.roleForm.get('guard')?.value;

    this.store
      .assignRoles(this.user.id, {
        roles: this.selectedRolesSignal(),
        guard,
      })
      .subscribe({
        next: () => {
          this.feedback.success('Roles assigned successfully');
          this.dialogRef.close(true);
        },
        error: (error) => {
          this.feedback.error(error.error?.message || 'Failed to assign roles');
        },
      });
  }

  // Add to your component class

  // 1. Module Scopes Configuration

  // 2. Filter State
  searchQuery = signal('');
  filteredRoles = computed(() => {
    const query = this.searchQuery().toLowerCase();
    return this.availableRoles().filter((r) => r.name.toLowerCase().includes(query));
  });

  // 3. Helpers
  getUserInitials(): string {
    const name = this.user.username || this.user.email || '?';
    return name.substring(0, 2).toUpperCase();
  }

  getSelectedScopeLabel(): string {
    const currentVal = this.roleForm.get('guard')?.value;
    const scope = this.moduleScopes.find((s) => s.value === currentVal);
    return scope ? scope.label : '';
  }

  getSelectedScopeIcon(): string {
    const currentVal = this.roleForm.get('guard')?.value;
    const scope = this.moduleScopes.find((s) => s.value === currentVal);
    return scope ? scope.icon : '';
  }

  getPermissionsList(role: Role): string {
    if (!role.permissions?.length) return 'No permissions assigned';
    return role.permissions.map((p) => p.name).join(', ');
  }

  filterRoles(query: string) {
    this.searchQuery.set(query);
  }

  /**
   * Cancel and close dialog
   */
  onCancel(): void {
    this.dialogRef.close(false);
  }
}
