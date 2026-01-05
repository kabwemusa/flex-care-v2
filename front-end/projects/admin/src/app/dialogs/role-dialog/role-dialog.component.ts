import { Component, Inject, inject, signal, computed, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  FormBuilder,
  FormGroup,
  ReactiveFormsModule,
  FormsModule,
  Validators,
} from '@angular/forms';

// Material Imports
import { MatDialogRef, MAT_DIALOG_DATA, MatDialogModule } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';

// Core Imports
import { RoleManagementStore, Role, Permission } from 'core-auth';
import { FeedbackService } from 'shared';

interface DialogData {
  role?: Role;
  guard?: string;
}

interface ModuleScope {
  value: string;
  label: string;
  icon: string;
}

@Component({
  selector: 'app-role-dialog',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    FormsModule,
    MatDialogModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatButtonModule,
    MatIconModule,
    MatProgressSpinnerModule,
  ],
  templateUrl: './role-dialog.component.html',
})
export class RoleDialogComponent implements OnInit {
  readonly store = inject(RoleManagementStore);
  private readonly fb = inject(FormBuilder);
  private readonly feedback = inject(FeedbackService);
  readonly dialogRef = inject(MatDialogRef<RoleDialogComponent>);

  // Configuration
  readonly moduleScopes: ModuleScope[] = [
    { value: 'web', label: 'Global (Web)', icon: 'public' },
    { value: 'medical', label: 'Medical', icon: 'medical_services' },
    { value: 'life', label: 'Life', icon: 'favorite' },
    { value: 'motor', label: 'Motor', icon: 'directions_car' },
  ];

  // State
  isEdit = false;
  roleForm!: FormGroup;
  searchQuery = '';
  expandedCategories = new Set<string>();

  private selectedPermissionsSignal = signal<string[]>([]);

  // Computed values
  selectedPermissions = computed(() => this.selectedPermissionsSignal());

  totalPermissionCount = computed(() => {
    let count = 0;
    Object.values(this.store.permissions()).forEach((perms) => {
      count += perms.length;
    });
    return count;
  });

  filteredCategories = computed(() => {
    const categories = this.store.permissionCategories();
    if (!this.searchQuery.trim()) {
      return categories;
    }

    const query = this.searchQuery.toLowerCase();
    return categories.filter((category) => {
      const categoryMatches = category.toLowerCase().includes(query);
      const permissionsMatch = this.getCategoryPermissions(category).some((p) =>
        p.name.toLowerCase().includes(query)
      );
      return categoryMatches || permissionsMatch;
    });
  });

  constructor(@Inject(MAT_DIALOG_DATA) public data: DialogData) {
    this.isEdit = !!data?.role;
  }

  ngOnInit(): void {
    this.initForm();

    const initialGuard = this.data.role?.guard_name || this.data.guard || 'web';
    this.roleForm.patchValue({ guard_name: initialGuard });

    if (this.isEdit && this.data.role) {
      this.patchForm(this.data.role);
    }

    // Load permissions and expand first category by default
    this.loadPermissionsForGuard(initialGuard);
  }

  private initForm(): void {
    this.roleForm = this.fb.group({
      name: ['', [Validators.required, Validators.minLength(2), Validators.maxLength(50)]],
      guard_name: ['web', Validators.required],
    });
  }

  private patchForm(role: Role): void {
    this.roleForm.patchValue({
      name: role.name,
      guard_name: role.guard_name,
    });

    if (role.permissions) {
      this.selectedPermissionsSignal.set(role.permissions.map((p) => p.name));
    }
  }

  onGuardChange(): void {
    const guard = this.roleForm.get('guard_name')?.value;
    if (guard) {
      this.loadPermissionsForGuard(guard);
      this.selectedPermissionsSignal.set([]);
      this.expandedCategories.clear();
    }
  }

  private loadPermissionsForGuard(guard: string): void {
    this.store.loadPermissions(guard).subscribe({
      next: () => {
        // Auto-expand first category for better UX
        const categories = this.store.permissionCategories();
        if (categories.length > 0) {
          this.expandedCategories.add(categories[0]);
        }
      },
      error: (error) => {
        this.feedback.error(error.error?.message || 'Failed to load permissions');
      },
    });
  }

  // Category methods
  toggleCategory(category: string): void {
    if (this.expandedCategories.has(category)) {
      this.expandedCategories.delete(category);
    } else {
      this.expandedCategories.add(category);
    }
  }

  getCategoryPermissions(category: string): Permission[] {
    return this.store.permissions()[category] || [];
  }

  getFilteredCategoryPermissions(category: string): Permission[] {
    const permissions = this.getCategoryPermissions(category);
    if (!this.searchQuery.trim()) {
      return permissions;
    }
    const query = this.searchQuery.toLowerCase();
    return permissions.filter((p) => p.name.toLowerCase().includes(query));
  }

  getCategoryPermissionCount(category: string): number {
    return this.getCategoryPermissions(category).length;
  }

  getSelectedInCategory(category: string): number {
    const categoryPerms = this.getCategoryPermissions(category).map((p) => p.name);
    return this.selectedPermissions().filter((p) => categoryPerms.includes(p)).length;
  }

  selectCategoryPermissions(category: string): void {
    const categoryPerms = this.getCategoryPermissions(category).map((p) => p.name);
    const current = new Set(this.selectedPermissionsSignal());
    categoryPerms.forEach((p) => current.add(p));
    this.selectedPermissionsSignal.set([...current]);
  }

  clearCategoryPermissions(category: string): void {
    const categoryPerms = new Set(this.getCategoryPermissions(category).map((p) => p.name));
    const filtered = this.selectedPermissions().filter((p) => !categoryPerms.has(p));
    this.selectedPermissionsSignal.set(filtered);
  }

  // Permission methods
  isPermissionSelected(permissionName: string): boolean {
    return this.selectedPermissions().includes(permissionName);
  }

  togglePermission(permissionName: string, checked: boolean): void {
    const current = this.selectedPermissionsSignal();
    if (checked) {
      if (!current.includes(permissionName)) {
        this.selectedPermissionsSignal.set([...current, permissionName]);
      }
    } else {
      this.selectedPermissionsSignal.set(current.filter((p) => p !== permissionName));
    }
  }

  selectAllPermissions(): void {
    const allPermissions: string[] = [];
    Object.values(this.store.permissions()).forEach((perms) => {
      perms.forEach((p: Permission) => allPermissions.push(p.name));
    });
    this.selectedPermissionsSignal.set(allPermissions);

    // Expand all categories
    this.store.permissionCategories().forEach((cat) => this.expandedCategories.add(cat));
  }

  clearAllPermissions(): void {
    this.selectedPermissionsSignal.set([]);
  }

  // Formatting helpers
  formatCategoryName(category: string): string {
    return category
      .split(/[._-]/)
      .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
      .join(' ');
  }

  getPermissionAction(permissionName: string): string {
    const parts = permissionName.split('.');
    const action = parts.length > 1 ? parts[parts.length - 1] : permissionName;
    return action.replace(/_/g, ' ');
  }

  getPermissionBadgeClass(permissionName: string): string {
    const action = this.getPermissionAction(permissionName).toLowerCase();

    const actionColors: Record<string, string> = {
      create: 'bg-green-100 text-green-700',
      view: 'bg-blue-100 text-blue-700',
      read: 'bg-blue-100 text-blue-700',
      update: 'bg-amber-100 text-amber-700',
      edit: 'bg-amber-100 text-amber-700',
      delete: 'bg-red-100 text-red-700',
      destroy: 'bg-red-100 text-red-700',
      manage: 'bg-purple-100 text-purple-700',
      export: 'bg-cyan-100 text-cyan-700',
      import: 'bg-teal-100 text-teal-700',
      approve: 'bg-emerald-100 text-emerald-700',
      reject: 'bg-rose-100 text-rose-700',
    };

    for (const [key, value] of Object.entries(actionColors)) {
      if (action.includes(key)) {
        return value;
      }
    }

    return 'bg-gray-100 text-gray-700';
  }

  // Actions
  onSave(): void {
    if (!this.roleForm.valid) {
      this.roleForm.markAllAsTouched();
      this.feedback.error('Please fill in all required fields');
      return;
    }

    if (this.selectedPermissions().length === 0) {
      this.feedback.error('Please select at least one permission');
      return;
    }

    const formValue = this.roleForm.value;
    const roleData = {
      name: formValue.name.trim(),
      guard_name: formValue.guard_name,
      permissions: this.selectedPermissions(),
    };

    const operation = this.isEdit
      ? this.store.updateRole(this.data.role!.id, roleData)
      : this.store.createRole(roleData);

    operation.subscribe({
      next: () => {
        this.feedback.success(
          this.isEdit ? 'Role updated successfully' : 'Role created successfully'
        );
        this.dialogRef.close(true);
      },
      error: (error) => {
        this.feedback.error(
          error.error?.message || (this.isEdit ? 'Failed to update role' : 'Failed to create role')
        );
      },
    });
  }

  // Add these to your RoleDialogComponent class

  getSelectedScopeLabel(): string {
    const currentVal = this.roleForm.get('guard_name')?.value;
    const scope = this.moduleScopes.find((s) => s.value === currentVal);
    return scope ? scope.label : '';
  }

  getSelectedScopeIcon(): string {
    const currentVal = this.roleForm.get('guard_name')?.value;
    const scope = this.moduleScopes.find((s) => s.value === currentVal);
    return scope ? scope.icon : '';
  }

  onCancel(): void {
    this.dialogRef.close(false);
  }
}
