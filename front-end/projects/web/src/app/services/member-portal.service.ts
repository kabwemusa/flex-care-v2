import { Injectable, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, tap } from 'rxjs';

// ── Interfaces ───────────────────────────────────────────────────────────────

interface ApiResponse<T> {
  status: string;
  message: string;
  data: T;
}

export interface Alert {
  id: string;
  type: 'info' | 'warning' | 'error' | 'success';
  title: string;
  message: string;
  action_url?: string;
  action_label?: string;
}

export interface DashboardMember {
  id: string;
  member_number: string;
  full_name: string;
  email: string;
  card_number: string | null;
  card_status: string;
  card_status_label: string;
  is_in_waiting_period: boolean;
  waiting_days_remaining: number;
}

export interface DashboardPolicy {
  id: string;
  policy_number: string;
  status: string;
  status_label: string;
  plan_name: string;
  scheme_name: string;
  inception_date: string;
  expiry_date: string;
  days_until_expiry: number;
}

export interface DashboardDependent {
  id: string;
  full_name: string;
  relationship: string;
  age: number;
  status: string;
  card_status: string;
}

export interface RecentClaim {
  id: string;
  claim_number: string;
  status: string;
  status_label: string;
  claimed_amount: number;
  approved_amount: number | null;
  claim_date: string;
}

export interface BenefitUsage {
  used: number;
  limit: number;
  percentage: number;
}

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

export interface PolicyBenefit {
  name: string;
  category: string;
  is_covered: boolean;
  limit: string;
  copay: string | null;
}

export interface PolicyMember {
  id: string;
  member_number: string;
  full_name: string;
  relationship: string | null;
  member_type: string;
  date_of_birth: string;
  age: number;
  card_number: string | null;
  card_status: string;
  card_status_label: string;
  status: string;
}

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
  timeline?: {
    status: string;
    date: string;
    note?: string;
  }[];
  documents?: {
    id: string;
    name: string;
    type: string;
    size: string;
    url: string;
  }[];
  payment?: {
    amount: number;
    date: string;
    reference?: string;
  };
}

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

// ── Service ──────────────────────────────────────────────────────────────────

@Injectable({
  providedIn: 'root',
})
export class MemberPortalService {
  private readonly baseUrl = '/v1/medical/member/portal';

  // Cached data
  dashboardData = signal<DashboardData | null>(null);
  policyData = signal<PolicyData | null>(null);

  constructor(private http: HttpClient) {}

  // ── Dashboard ──────────────────────────────────────────────────────────────

  getDashboard(): Observable<ApiResponse<DashboardData>> {
    return this.http.get<ApiResponse<DashboardData>>(`${this.baseUrl}/dashboard`).pipe(
      tap((res) => this.dashboardData.set(res.data))
    );
  }

  // ── Policy ─────────────────────────────────────────────────────────────────

  getPolicy(): Observable<ApiResponse<PolicyData>> {
    return this.http.get<ApiResponse<PolicyData>>(`${this.baseUrl}/policy`).pipe(
      tap((res) => this.policyData.set(res.data))
    );
  }

  // ── ID Cards ───────────────────────────────────────────────────────────────

  getIdCard(memberId?: string): Observable<ApiResponse<IdCardData>> {
    const url = memberId ? `${this.baseUrl}/id-card/${memberId}` : `${this.baseUrl}/id-card`;
    return this.http.get<ApiResponse<IdCardData>>(url);
  }

  // ── Claims ─────────────────────────────────────────────────────────────────

  getClaims(page = 1): Observable<ApiResponse<Claim[]>> {
    return this.http.get<ApiResponse<Claim[]>>(
      `${this.baseUrl}/claims`,
      { params: { page: page.toString() } }
    );
  }

  getClaimDetail(id: string): Observable<ApiResponse<ClaimDetail>> {
    return this.http.get<ApiResponse<ClaimDetail>>(`${this.baseUrl}/claims/${id}`);
  }

  // ── Profile ────────────────────────────────────────────────────────────────

  getProfile(): Observable<ApiResponse<ProfileData>> {
    return this.http.get<ApiResponse<ProfileData>>(`${this.baseUrl}/profile`);
  }

  updateProfile(data: Partial<ProfileData>): Observable<ApiResponse<null>> {
    return this.http.put<ApiResponse<null>>(`${this.baseUrl}/profile`, data);
  }

  // ── ID Cards ───────────────────────────────────────────────────────────────

  getIdCards(): Observable<ApiResponse<IdCard[]>> {
    return this.http.get<ApiResponse<IdCard[]>>(`${this.baseUrl}/id-cards`);
  }

  downloadIdCard(memberId: string): Observable<Blob> {
    return this.http.get(`${this.baseUrl}/id-cards/${memberId}/download`, {
      responseType: 'blob',
    });
  }

  // ── Claims Submission ──────────────────────────────────────────────────────

  submitClaim(formData: FormData): Observable<ApiResponse<{ id: string }>> {
    return this.http.post<ApiResponse<{ id: string }>>(`${this.baseUrl}/claims`, formData);
  }
}
