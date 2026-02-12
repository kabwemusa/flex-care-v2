import { Injectable, signal, computed } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { Observable, tap, catchError, of } from 'rxjs';

export interface MemberContext {
  id: string;
  member_number: string;
  first_name: string;
  last_name: string;
  full_name: string;
  email: string;
  phone: string | null;
  member_type: string;
  has_password: boolean;
  policy: {
    id: string;
    policy_number: string;
    status: string;
    plan_name: string;
    scheme_name: string;
    inception_date: string;
    expiry_date: string;
  } | null;
  dependents_count: number;
  card_status: string;
  card_number: string | null;
}

interface ApiResponse<T> {
  status: string;
  message: string;
  data: T;
}

interface LoginResponse {
  token: string;
  expires_at: string;
  member: MemberContext;
}

interface OtpResponse {
  expires_in: number;
}

const TOKEN_KEY = 'flex_member_token';
const MEMBER_KEY = 'flex_member';

@Injectable({
  providedIn: 'root',
})
export class MemberAuthService {
  private memberSignal = signal<MemberContext | null>(null);
  private loadingSignal = signal(false);
  private initializedSignal = signal(false);

  // Public signals
  member = this.memberSignal.asReadonly();
  loading = this.loadingSignal.asReadonly();
  isAuthenticated = computed(() => this.memberSignal() !== null);
  initialized = this.initializedSignal.asReadonly();

  constructor(
    private http: HttpClient,
    private router: Router
  ) {
    this.initializeFromStorage();
  }

  /**
   * Initialize auth state from localStorage.
   */
  private initializeFromStorage(): void {
    const token = localStorage.getItem(TOKEN_KEY);
    const memberJson = localStorage.getItem(MEMBER_KEY);

    if (token && memberJson) {
      try {
        const member = JSON.parse(memberJson);
        this.memberSignal.set(member);
      } catch {
        this.clearStorage();
      }
    }

    this.initializedSignal.set(true);
  }

  /**
   * Request OTP for login.
   */
  requestOtp(email: string): Observable<ApiResponse<OtpResponse>> {
    this.loadingSignal.set(true);

    return this.http
      .post<ApiResponse<OtpResponse>>('/v1/medical/member/auth/request-otp', { email })
      .pipe(
        tap(() => this.loadingSignal.set(false)),
        catchError((err) => {
          this.loadingSignal.set(false);
          throw err;
        })
      );
  }

  /**
   * Verify OTP and login.
   */
  verifyOtp(email: string, code: string): Observable<ApiResponse<LoginResponse>> {
    this.loadingSignal.set(true);

    return this.http
      .post<ApiResponse<LoginResponse>>('/v1/medical/member/auth/verify-otp', { email, code })
      .pipe(
        tap((res) => {
          this.handleLoginSuccess(res.data);
          this.loadingSignal.set(false);
        }),
        catchError((err) => {
          this.loadingSignal.set(false);
          throw err;
        })
      );
  }

  /**
   * Login with password.
   */
  loginWithPassword(email: string, password: string): Observable<ApiResponse<LoginResponse>> {
    this.loadingSignal.set(true);

    return this.http
      .post<ApiResponse<LoginResponse>>('/v1/medical/member/auth/login', { email, password })
      .pipe(
        tap((res) => {
          this.handleLoginSuccess(res.data);
          this.loadingSignal.set(false);
        }),
        catchError((err) => {
          this.loadingSignal.set(false);
          throw err;
        })
      );
  }

  /**
   * Set password for member.
   */
  setPassword(password: string, passwordConfirmation: string): Observable<ApiResponse<null>> {
    return this.http.post<ApiResponse<null>>('/v1/medical/member/auth/set-password', {
      password,
      password_confirmation: passwordConfirmation,
    });
  }

  /**
   * Request password reset.
   */
  forgotPassword(email: string): Observable<ApiResponse<null>> {
    return this.http.post<ApiResponse<null>>('/v1/medical/member/auth/forgot-password', { email });
  }

  /**
   * Reset password with OTP.
   */
  resetPassword(
    email: string,
    code: string,
    password: string,
    passwordConfirmation: string
  ): Observable<ApiResponse<null>> {
    return this.http.post<ApiResponse<null>>('/v1/medical/member/auth/reset-password', {
      email,
      code,
      password,
      password_confirmation: passwordConfirmation,
    });
  }

  /**
   * Get current member context.
   */
  getMe(): Observable<ApiResponse<MemberContext>> {
    return this.http.get<ApiResponse<MemberContext>>('/v1/medical/member/auth/me').pipe(
      tap((res) => {
        this.memberSignal.set(res.data);
        localStorage.setItem(MEMBER_KEY, JSON.stringify(res.data));
      })
    );
  }

  /**
   * Logout.
   */
  logout(): void {
    const token = localStorage.getItem(TOKEN_KEY);

    if (token) {
      this.http.post('/v1/medical/member/auth/logout', {}).subscribe({
        complete: () => this.handleLogout(),
        error: () => this.handleLogout(),
      });
    } else {
      this.handleLogout();
    }
  }

  /**
   * Get current token.
   */
  getToken(): string | null {
    return localStorage.getItem(TOKEN_KEY);
  }

  /**
   * Handle successful login.
   */
  private handleLoginSuccess(data: LoginResponse): void {
    localStorage.setItem(TOKEN_KEY, data.token);
    localStorage.setItem(MEMBER_KEY, JSON.stringify(data.member));
    this.memberSignal.set(data.member);
  }

  /**
   * Handle logout.
   */
  private handleLogout(): void {
    this.clearStorage();
    this.memberSignal.set(null);
    this.router.navigate(['/login']);
  }

  /**
   * Clear storage.
   */
  private clearStorage(): void {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(MEMBER_KEY);
  }
}
