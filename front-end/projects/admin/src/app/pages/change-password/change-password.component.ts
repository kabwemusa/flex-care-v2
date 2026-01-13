import { Component, inject, signal, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, ActivatedRoute } from '@angular/router';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatCardModule } from '@angular/material/card';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { AuthService } from 'core-auth';

@Component({
  selector: 'app-change-password',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    MatIconModule,
    MatCardModule,
    MatProgressSpinnerModule,
  ],
  templateUrl: './change-password.component.html',
  styleUrls: ['./change-password.component.scss'],
})
export class ChangePasswordComponent implements OnInit {
  private fb = inject(FormBuilder);
  private authService = inject(AuthService);
  private router = inject(Router);
  private route = inject(ActivatedRoute);

  passwordForm!: FormGroup;
  hideCurrentPassword = signal(true);
  hideNewPassword = signal(true);
  hideConfirmPassword = signal(true);
  errorMessage = signal<string | null>(null);
  successMessage = signal<string | null>(null);
  loading = signal(false);
  isForced = signal(false);
  reason = signal<string>('');

  ngOnInit() {
    const queryParams = this.route.snapshot.queryParams;
    this.isForced.set(queryParams['forced'] === 'true');
    this.reason.set(queryParams['reason'] || '');

    // Build form with conditional validation
    if (this.isForced() && this.reason() === 'required') {
      // New user - no current password needed
      this.passwordForm = this.fb.group({
        password: ['', [Validators.required, Validators.minLength(8)]],
        password_confirmation: ['', [Validators.required]],
        force_change: [true],
      }, { validators: this.passwordMatchValidator });
    } else {
      // Regular password change or expired
      this.passwordForm = this.fb.group({
        current_password: ['', [Validators.required]],
        password: ['', [Validators.required, Validators.minLength(8)]],
        password_confirmation: ['', [Validators.required]],
        force_change: [this.isForced()],
      }, { validators: this.passwordMatchValidator });
    }
  }

  passwordMatchValidator(form: FormGroup) {
    const password = form.get('password');
    const confirmPassword = form.get('password_confirmation');

    if (password && confirmPassword && password.value !== confirmPassword.value) {
      confirmPassword.setErrors({ passwordMismatch: true });
      return { passwordMismatch: true };
    }

    return null;
  }

  onSubmit(): void {
    if (this.passwordForm.invalid) {
      return;
    }

    this.errorMessage.set(null);
    this.successMessage.set(null);
    this.loading.set(true);

    this.authService.changePassword(this.passwordForm.value).subscribe({
      next: (response) => {
        this.loading.set(false);
        this.successMessage.set('Password changed successfully!');

        // Redirect to home after 2 seconds
        setTimeout(() => {
          this.router.navigate(['/']);
        }, 2000);
      },
      error: (error) => {
        this.loading.set(false);

        let message = 'Failed to change password. Please try again.';

        if (error.error?.errors) {
          // Extract first validation error
          const errors = error.error.errors;
          const firstError = Object.values(errors)[0];
          message = Array.isArray(firstError) ? firstError[0] : firstError as string;
        } else if (error.error?.message) {
          message = error.error.message;
        }

        this.errorMessage.set(message);
      },
    });
  }

  toggleCurrentPasswordVisibility(): void {
    this.hideCurrentPassword.update((v) => !v);
  }

  toggleNewPasswordVisibility(): void {
    this.hideNewPassword.update((v) => !v);
  }

  toggleConfirmPasswordVisibility(): void {
    this.hideConfirmPassword.update((v) => !v);
  }

  cancel(): void {
    if (this.isForced()) {
      // If password change is forced, logout instead
      this.authService.logout().subscribe(() => {
        this.router.navigate(['/login']);
      });
    } else {
      this.router.navigate(['/']);
    }
  }
}
