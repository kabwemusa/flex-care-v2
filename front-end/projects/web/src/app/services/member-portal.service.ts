import { Injectable, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, tap } from 'rxjs';

// ── API envelope ─────────────────────────────────────────────────────────────

interface ApiResponse<T> {
  status: string;
  message: string;
  data: T;
}

// ── Alert ─────────────────────────────────────────────────────────────────────

export interface Alert {
  id: string;
  type: 'info' | 'warning' | 'error' | 'success';
  title: string;
  message: string;
  action_url?: string;
  action_label?: string;
}

// ── Dashboard ─────────────────────────────────────────────────────────────────

export interface DashboardData {
  member: {
    id: string;
    member_number: string;
    first_name: string;
    last_name: string;
    email: string;
  };
  policy: {
    id: string;
    policy_number: string;
    plan_name: string;
    status: string;
    start_date: string;
    end_date: string;
  } | null;
  dependents: {
    id: string;
    first_name: string;
    last_name: string;
    relationship: string;
  }[];
  recent_claims: {
    id: string;
    claim_number: string;
    service_type: string;
    status: string;
    amount: number;
    approved_amount?: number;
    submitted_at: string;
  }[];
  benefits: {
    name: string;
    used: number;
    limit: number;
    percentage: number;
  }[];
  alerts: Alert[];
}

// ── Policy ────────────────────────────────────────────────────────────────────

export interface PolicyData {
  policy_number: string;
  plan_name: string;
  status: string;
  start_date: string;
  end_date: string;
  annual_premium: number;
  payment_status: string;
  benefits: {
    name: string;
    category: string;
    description?: string;
    limit: number;
    used: number;
    percentage: number;
  }[];
  members: {
    id: string;
    first_name: string;
    last_name: string;
    relationship: string;
    date_of_birth: string;
    is_primary: boolean;
  }[];
}

// ── Claims ────────────────────────────────────────────────────────────────────

export interface Claim {
  id: string;
  claim_number: string;
  service_type: string;
  status: string;
  provider_name: string;
  amount: number;
  approved_amount?: number;
  submitted_at: string;
}

export interface ClaimDetail {
  id: string;
  claim_number: string;
  service_type: string;
  status: string;
  provider_name: string;
  amount: number;
  approved_amount?: number;
  service_date: string;
  submitted_at: string;
  member_name: string;
  diagnosis?: string;
  benefit_category?: string;
  notes?: string;
  rejection_reason?: string;
  timeline?: { status: string; date: string; note?: string }[];
  documents?: { id: string; name: string; type: string; size: string; url: string }[];
  payment?: { amount: number; date: string; reference?: string };
}

// ── ID Cards ──────────────────────────────────────────────────────────────────

export interface IdCard {
  member_id: string;
  member_number: string;
  first_name: string;
  last_name: string;
  date_of_birth: string;
  plan_name: string;
  valid_until: string;
  is_primary: boolean;
}

export interface IdCardData {
  card: {
    member_number: string;
    card_number: string;
    full_name: string;
    date_of_birth: string;
    gender: string;
    relationship: string;
    card_issued_date: string;
    card_expiry_date: string;
  };
  policy: {
    policy_number: string;
    plan_name: string;
    scheme_name: string;
    inception_date: string;
    expiry_date: string;
  };
  issuer: {
    name: string;
    contact: string;
    email: string;
    website: string;
  };
}

// ── Profile ───────────────────────────────────────────────────────────────────

export interface ProfileData {
  id: string;
  member_number: string;
  first_name: string;
  last_name: string;
  date_of_birth: string;
  gender: string;
  email: string;
  phone?: string;
  address?: string;
  emergency_contact_name?: string;
  emergency_contact_phone?: string;
}

// ── Service ───────────────────────────────────────────────────────────────────

@Injectable({ providedIn: 'root' })
export class MemberPortalService {
  private readonly baseUrl = '/v1/medical/member/portal';

  /** Signal cache — components can read these for instant display without re-fetching. */
  readonly dashboardData = signal<DashboardData | null>(null);
  readonly policyData    = signal<PolicyData | null>(null);

  constructor(private http: HttpClient) {}

  // ── Dashboard ──────────────────────────────────────────────────────────────

  getDashboard(): Observable<ApiResponse<DashboardData>> {
    return this.http
      .get<ApiResponse<DashboardData>>(`${this.baseUrl}/dashboard`)
      .pipe(tap((res) => this.dashboardData.set(res.data)));
  }

  // ── Policy ─────────────────────────────────────────────────────────────────

  getPolicy(): Observable<ApiResponse<PolicyData>> {
    return this.http
      .get<ApiResponse<PolicyData>>(`${this.baseUrl}/policy`)
      .pipe(tap((res) => this.policyData.set(res.data)));
  }

  // ── Claims ─────────────────────────────────────────────────────────────────

  getClaims(page = 1): Observable<ApiResponse<Claim[]>> {
    return this.http.get<ApiResponse<Claim[]>>(`${this.baseUrl}/claims`, {
      params: { page: String(page) },
    });
  }

  getClaimDetail(id: string): Observable<ApiResponse<ClaimDetail>> {
    return this.http.get<ApiResponse<ClaimDetail>>(`${this.baseUrl}/claims/${id}`);
  }

  submitClaim(formData: FormData): Observable<ApiResponse<{ id: string }>> {
    return this.http.post<ApiResponse<{ id: string }>>(`${this.baseUrl}/claims`, formData);
  }

  // ── ID Cards ───────────────────────────────────────────────────────────────

  getIdCards(): Observable<ApiResponse<IdCard[]>> {
    return this.http.get<ApiResponse<IdCard[]>>(`${this.baseUrl}/id-cards`);
  }

  getIdCard(memberId?: string): Observable<ApiResponse<IdCardData>> {
    const url = memberId
      ? `${this.baseUrl}/id-card/${memberId}`
      : `${this.baseUrl}/id-card`;
    return this.http.get<ApiResponse<IdCardData>>(url);
  }

  downloadIdCard(memberId: string): Observable<Blob> {
    return this.http.get(`${this.baseUrl}/id-cards/${memberId}/download`, {
      responseType: 'blob',
    });
  }

  // ── Profile ────────────────────────────────────────────────────────────────

  getProfile(): Observable<ApiResponse<ProfileData>> {
    return this.http.get<ApiResponse<ProfileData>>(`${this.baseUrl}/profile`);
  }

  updateProfile(data: Partial<ProfileData>): Observable<ApiResponse<null>> {
    return this.http.put<ApiResponse<null>>(`${this.baseUrl}/profile`, data);
  }
}
