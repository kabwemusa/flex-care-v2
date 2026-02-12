import { Component, signal } from '@angular/core';
import { Router, RouterModule } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { MemberAuthService } from '../../services/member-auth.service';

type LoginStep = 'email' | 'otp' | 'password';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [RouterModule, FormsModule],
  templateUrl: './login.html',
})
export class LoginPage {
  step = signal<LoginStep>('email');
  email = signal('');
  otp = signal('');
  password = signal('');
  loading = signal(false);
  error = signal('');
  otpExpiresIn = signal(0);
  otpCountdown = signal(0);
  canResend = signal(false);
  hasPassword = signal(false); // If member has set a password

  private countdownInterval: ReturnType<typeof setInterval> | null = null;

  constructor(
    private authService: MemberAuthService,
    private router: Router
  ) {}

  // ── Step 1: Request OTP ────────────────────────────────────────────────────

  requestOtp() {
    if (!this.email() || !this.isValidEmail(this.email())) {
      this.error.set('Please enter a valid email address');
      return;
    }

    this.loading.set(true);
    this.error.set('');

    this.authService.requestOtp(this.email()).subscribe({
      next: (res) => {
        this.otpExpiresIn.set(res.data.expires_in);
        this.startCountdown(res.data.expires_in);
        this.step.set('otp');
        this.loading.set(false);
      },
      error: (err) => {
        this.error.set(err.error?.message ?? 'Failed to send verification code');
        this.loading.set(false);
      },
    });
  }

  // ── Step 2: Verify OTP ─────────────────────────────────────────────────────

  verifyOtp() {
    if (!this.otp() || this.otp().length !== 6) {
      this.error.set('Please enter the 6-digit code');
      return;
    }

    this.loading.set(true);
    this.error.set('');

    this.authService.verifyOtp(this.email(), this.otp()).subscribe({
      next: () => {
        this.loading.set(false);
        this.router.navigate(['/portal']);
      },
      error: (err) => {
        this.error.set(err.error?.message ?? 'Invalid verification code');
        this.loading.set(false);
      },
    });
  }

  resendOtp() {
    if (!this.canResend()) return;
    this.otp.set('');
    this.requestOtp();
  }

  // ── Password Login (Alternative) ───────────────────────────────────────────

  showPasswordLogin() {
    this.step.set('password');
    this.error.set('');
  }

  showOtpLogin() {
    this.step.set('email');
    this.error.set('');
  }

  loginWithPassword() {
    if (!this.email() || !this.password()) {
      this.error.set('Please enter email and password');
      return;
    }

    this.loading.set(true);
    this.error.set('');

    this.authService.loginWithPassword(this.email(), this.password()).subscribe({
      next: () => {
        this.loading.set(false);
        this.router.navigate(['/portal']);
      },
      error: (err) => {
        this.error.set(err.error?.message ?? 'Invalid email or password');
        this.loading.set(false);
      },
    });
  }

  // ── Helpers ────────────────────────────────────────────────────────────────

  private isValidEmail(email: string): boolean {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  }

  private startCountdown(seconds: number) {
    this.stopCountdown();
    this.otpCountdown.set(seconds);
    this.canResend.set(false);

    this.countdownInterval = setInterval(() => {
      const current = this.otpCountdown();
      if (current <= 1) {
        this.stopCountdown();
        this.canResend.set(true);
      } else {
        this.otpCountdown.set(current - 1);
      }
    }, 1000);
  }

  private stopCountdown() {
    if (this.countdownInterval) {
      clearInterval(this.countdownInterval);
      this.countdownInterval = null;
    }
  }

  formatCountdown(): string {
    const seconds = this.otpCountdown();
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
  }

  goBack() {
    if (this.step() === 'otp') {
      this.step.set('email');
      this.otp.set('');
      this.stopCountdown();
    } else if (this.step() === 'password') {
      this.step.set('email');
      this.password.set('');
    }
    this.error.set('');
  }

  // Handle OTP input - auto-advance and only allow numbers
  onOtpInput(event: Event) {
    const input = event.target as HTMLInputElement;
    const value = input.value.replace(/\D/g, '').slice(0, 6);
    this.otp.set(value);
    input.value = value;

    // Auto-submit when 6 digits entered
    if (value.length === 6) {
      this.verifyOtp();
    }
  }
}
