import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, ActivatedRoute } from '@angular/router';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { AuthService } from 'core-auth';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    MatIconModule,
    MatProgressSpinnerModule,
  ],
  templateUrl: './login.component.html',
  styleUrls: ['./login.component.scss'],
})
export class LoginComponent {
  private fb = inject(FormBuilder);
  private authService = inject(AuthService);
  private router = inject(Router);
  private route = inject(ActivatedRoute);

  loginForm: FormGroup;
  hidePassword = signal(true);
  errorMessage = signal<string | null>(null);
  isWarning = signal(false); // Track if message is a warning vs error
  loading = this.authService.loading;

  currentYear = new Date().getFullYear();

  constructor() {
    this.loginForm = this.fb.group({
      email: ['', [Validators.required, Validators.email]],
      password: ['', [Validators.required, Validators.minLength(6)]],
    });

    // Check for session expiration message
    const queryParams = this.route.snapshot.queryParams;
    if (queryParams['expired']) {
      const reason = queryParams['reason'];
      if (reason === 'inactivity') {
        this.errorMessage.set('Your session expired due to inactivity. Please login again.');
      } else {
        this.errorMessage.set('Your session has expired. Please login again.');
      }
      this.isWarning.set(false);
    }
  }

  onSubmit(): void {
    if (this.loginForm.invalid) {
      return;
    }

    this.errorMessage.set(null);
    this.isWarning.set(false);

    this.authService.login(this.loginForm.value).subscribe({
      next: (response) => {
        // Check if password change is required
        const passwordStatus = this.authService.passwordStatus();

        if (passwordStatus?.force_change || passwordStatus?.expired) {
          // Redirect to change password page
          this.router.navigate(['/change-password'], {
            queryParams: {
              forced: true,
              reason: passwordStatus.force_change ? 'required' : 'expired'
            }
          });
          return;
        }

        // Show warning if password expiring soon
        if (passwordStatus?.expiring_soon && passwordStatus.days_until_expiration) {
          // Could show a snackbar/toast here
          console.warn(`Your password will expire in ${passwordStatus.days_until_expiration} days`);
        }

        // Get return URL or default to home
        const returnUrl = this.route.snapshot.queryParams['returnUrl'] || '/';
        this.router.navigateByUrl(returnUrl);
      },
      error: (error) => {
        // Extract error message from Laravel validation error structure
        let message = 'Login failed. Please check your credentials.';

        // Check for Laravel validation errors
        if (error.error?.errors?.email) {
          // Laravel validation error format: { errors: { email: ["error message"] } }
          message = Array.isArray(error.error.errors.email)
            ? error.error.errors.email[0]
            : error.error.errors.email;
        } else if (error.error?.message) {
          // Direct message from backend
          message = error.error.message;
        }

        // Check if this is a warning message (contains "Warning:" or mentions remaining attempts)
        const isWarningMessage =
          message.includes('Warning:') ||
          message.includes('remaining') ||
          message.includes('attempt(s) remaining');

        this.isWarning.set(isWarningMessage);
        this.errorMessage.set(message);
      },
    });
  }

  togglePasswordVisibility(): void {
    this.hidePassword.update((v) => !v);
  }
}
