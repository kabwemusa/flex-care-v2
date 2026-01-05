import { Component, Inject, inject, signal, computed, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  FormBuilder,
  FormGroup,
  ReactiveFormsModule,
  Validators,
  AbstractControl,
  ValidationErrors,
} from '@angular/forms';

// Material Imports
import { MatDialogRef, MAT_DIALOG_DATA, MatDialogModule } from '@angular/material/dialog';
import { MAT_FORM_FIELD_DEFAULT_OPTIONS, MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { MatDividerModule } from '@angular/material/divider';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';

// Core Imports
import {
  UserManagementStore,
  UserListItem,
  CreateUserRequest,
  UpdateUserRequest,
  ModuleCode,
} from 'core-auth';
import { FeedbackService } from 'shared';

interface DialogData {
  user?: UserListItem;
}

@Component({
  selector: 'app-user-dialog',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    MatDialogModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    MatIconModule,
    MatCheckboxModule,
    MatDividerModule,
    MatProgressSpinnerModule,
  ],
  providers: [{ provide: MAT_FORM_FIELD_DEFAULT_OPTIONS, useValue: { floatLabel: 'auto' } }],
  templateUrl: './user-dialog.component.html',
})
export class UserDialogComponent implements OnInit {
  readonly store = inject(UserManagementStore);
  private readonly fb = inject(FormBuilder);
  private readonly feedback = inject(FeedbackService);
  readonly dialogRef = inject(MatDialogRef<UserDialogComponent>);

  // State
  isEdit = false;
  hidePassword = signal(true);
  private selectedModulesSignal = signal<ModuleCode[]>([]);

  // Computed
  selectedModules = computed(() => this.selectedModulesSignal());

  // Form
  userForm!: FormGroup;

  constructor(@Inject(MAT_DIALOG_DATA) public data: DialogData) {
    this.isEdit = !!data?.user;
  }

  ngOnInit(): void {
    this.initForm();

    if (this.isEdit && this.data.user) {
      this.patchForm(this.data.user);
    }
  }

  private initForm(): void {
    this.userForm = this.fb.group(
      {
        email: ['', [Validators.required, Validators.email]],
        username: [''],
        password: ['', this.isEdit ? [] : [Validators.required, Validators.minLength(8)]],
        password_confirmation: [''],
        is_active: [true],
        is_system_admin: [false],
        mfa_enabled: [false],
      },
      {
        validators: this.isEdit ? [] : this.passwordMatchValidator,
      }
    );

    // Watch is_system_admin changes
    this.userForm.get('is_system_admin')?.valueChanges.subscribe(() => {
      // Can trigger UI updates for module access section
    });
  }

  private patchForm(user: UserListItem): void {
    this.userForm.patchValue({
      email: user.email,
      username: user.username,
      is_active: user.is_active,
      is_system_admin: user.is_system_admin,
      mfa_enabled: user.mfa_enabled,
    });

    // Load existing module access
    if (user.module_access && !user.is_system_admin) {
      const modules = user.module_access.map((ma) => ma.module_code as ModuleCode);
      this.selectedModulesSignal.set(modules);
    }
  }

  /**
   * Custom validator to check if passwords match
   */
  private passwordMatchValidator(control: AbstractControl): ValidationErrors | null {
    const password = control.get('password');
    const confirmation = control.get('password_confirmation');

    if (!password || !confirmation) {
      return null;
    }

    return password.value === confirmation.value ? null : { passwordMismatch: true };
  }

  /**
   * Check if module is selected
   */
  isModuleSelected(moduleCode: ModuleCode): boolean {
    return this.selectedModules().includes(moduleCode);
  }

  /**
   * Toggle module selection
   */
  toggleModule(moduleCode: ModuleCode, checked: boolean): void {
    const current = this.selectedModulesSignal();
    if (checked) {
      if (!current.includes(moduleCode)) {
        this.selectedModulesSignal.set([...current, moduleCode]);
      }
    } else {
      this.selectedModulesSignal.set(current.filter((m) => m !== moduleCode));
    }
  }

  /**
   * Save user
   */
  onSave(): void {
    if (!this.userForm.valid) {
      this.feedback.error('Please fill in all required fields correctly');
      return;
    }

    const formValue = this.userForm.value;

    // Remove password_confirmation from payload
    delete formValue.password_confirmation;

    // Remove empty password for edit
    if (this.isEdit && !formValue.password) {
      delete formValue.password;
    }

    // Remove empty username
    if (!formValue.username) {
      delete formValue.username;
    }

    // Add module access (only if not system admin)
    if (!formValue.is_system_admin) {
      formValue.module_access = this.selectedModules();
    }

    const operation = this.isEdit
      ? this.store.updateUser(this.data.user!.id, formValue as UpdateUserRequest)
      : this.store.createUser(formValue as CreateUserRequest);

    operation.subscribe({
      next: () => {
        this.feedback.success(
          this.isEdit ? 'User updated successfully' : 'User created successfully'
        );
        this.dialogRef.close(true);
      },
      error: (error) => {
        this.feedback.error(
          error.error?.message || (this.isEdit ? 'Failed to update user' : 'Failed to create user')
        );
      },
    });
  }

  /**
   * Cancel and close dialog
   */
  onCancel(): void {
    this.dialogRef.close(false);
  }
}
