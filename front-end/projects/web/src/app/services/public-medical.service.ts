import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

// ── Public-facing API interfaces ──────────────────────────────────────────────
// These mirror backend API contracts for unauthenticated quote/apply flows.

export interface WebPlan {
  id: string;
  code: string;
  name: string;
  plan_type: string;
  tier_level: number;
  description: string;
  scheme: { name: string };
  plan_benefits_count: number;
  plan_addons_count: number;
}

export interface WebAddon {
  id: string;
  code: string;
  name: string;
  description: string;
  availability: 'optional' | 'mandatory' | 'included' | 'conditional';
  availability_label: string;
  pricing_type: string;
  pricing_type_label: string;
  currency: string;
  amount: number | null;
  percentage: number | null;
}

export interface QuoteMember {
  member_type: string;
  age: number;
  gender: string;
  name?: string;
}

export interface QuoteResult {
  plan: { id: string; code: string; name: string };
  rate_card: { id: string; code: string; version: string };
  premium_basis: string;
  members: { member_type: string; age: number; gender: string | null; premium: number }[];
  base_premium: number;
  addon_premium: number;
  addons: { name: string; amount: number; is_included?: boolean }[];
  discounts: { name: string; amount: number }[];
  total_discount: number;
  promo_discount: number;
  loadings: { condition: string; amount: number }[];
  total_loading: number;
  /** Pre-tax total after all discounts and promos */
  net_premium: number;
  /** Tax/levy label (e.g. "VAT") */
  tax_name: string;
  /** Tax rate as a decimal (e.g. 0.05 = 5%) */
  tax_rate: number;
  /** Monetary tax amount applied to net_premium */
  tax_amount: number;
  /** Gross premium = net_premium + tax_amount — what the member actually pays */
  final_premium: number;
  currency: string;
  frequency: string;
  quote_date: string;
  valid_until: string;
}

export interface QuotePayload {
  plan_id: string;
  members: { member_type: string; age: number; gender: string | null }[];
  addon_ids: string[];
  promo_code?: string;
  discount_context?: { billing_frequency: string };
}

export interface ApplyMember {
  member_type: string;
  gender: string;
  first_name: string;
  last_name: string;
  date_of_birth: string;
  national_id?: string | null;
  email?: string | null;
  phone?: string | null;
}

export interface ApplicationPayload {
  plan_id: string;
  members: ApplyMember[];
  addon_ids: string[];
  contact_name?: string | null;
  contact_email: string;
  contact_phone?: string | null;
  proposed_start_date: string;
  billing_frequency: string;
}

export interface ApplicationResult {
  id: string;
  application_number: string;
  status: string;
  plan: { id: string; name: string };
  contact_email: string;
  proposed_start_date: string;
  member_count: number;
  total_premium: number;
}

interface ApiListResponse<T> {
  data: T[];
}

interface ApiItemResponse<T> {
  data: T;
}

/**
 * PublicMedicalService
 *
 * Handles all unauthenticated public API calls for the quote and apply flows.
 * Extracted from quote.ts and apply.ts where HttpClient was called directly,
 * which violated the service-layer architecture established by MemberPortalService.
 */
@Injectable({ providedIn: 'root' })
export class PublicMedicalService {
  private readonly base = '/v1/medical/public';

  constructor(private http: HttpClient) {}

  // ── Plans ─────────────────────────────────────────────────────────────────

  getPlans(perPage = 50): Observable<ApiListResponse<WebPlan>> {
    return this.http.get<ApiListResponse<WebPlan>>(`${this.base}/plans`, {
      params: { active_only: 'true', per_page: String(perPage) },
    });
  }

  getPlanAddons(planId: string): Observable<ApiListResponse<WebAddon>> {
    return this.http.get<ApiListResponse<WebAddon>>(`${this.base}/plans/${planId}/addons`);
  }

  // ── Quotes ────────────────────────────────────────────────────────────────

  calculateQuote(payload: QuotePayload): Observable<ApiItemResponse<QuoteResult>> {
    return this.http.post<ApiItemResponse<QuoteResult>>(`${this.base}/quotes`, payload);
  }

  // ── Applications ──────────────────────────────────────────────────────────

  submitApplication(payload: ApplicationPayload): Observable<ApiItemResponse<ApplicationResult>> {
    return this.http.post<ApiItemResponse<ApplicationResult>>(
      `${this.base}/applications`,
      payload
    );
  }
}
