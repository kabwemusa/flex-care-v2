// Report Store - Signal-based state management for reports

import { HttpClient, HttpParams } from '@angular/common/http';
import { computed, inject, Injectable, signal } from '@angular/core';
import { tap } from 'rxjs';
import {
  ApprovalMetrics,
  BillingReports,
  DashboardSummary,
  PolicyAnalytics,
  ReportFilters,
} from '../models/report.models';

interface ApiResponse<T> {
  status: string;
  message?: string;
  data: T;
}

interface ReportState {
  // Data
  summary: DashboardSummary | null;
  approvalMetrics: ApprovalMetrics | null;
  policyAnalytics: PolicyAnalytics | null;
  billingReports: BillingReports | null;

  // Filters
  filters: ReportFilters;

  // Loading states
  loadingSummary: boolean;
  loadingApprovals: boolean;
  loadingPolicies: boolean;
  loadingBilling: boolean;

  // Error states
  error: string | null;
}

@Injectable({ providedIn: 'root' })
export class ReportStore {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = '/api/reports';

  private readonly state = signal<ReportState>({
    summary: null,
    approvalMetrics: null,
    policyAnalytics: null,
    billingReports: null,
    filters: {
      from_date: this.getDefaultFromDate(),
      to_date: this.getDefaultToDate(),
    },
    loadingSummary: false,
    loadingApprovals: false,
    loadingPolicies: false,
    loadingBilling: false,
    error: null,
  });

  // =========================================================================
  // SELECTORS - DATA
  // =========================================================================

  readonly summary = computed(() => this.state().summary);
  readonly approvalMetrics = computed(() => this.state().approvalMetrics);
  readonly policyAnalytics = computed(() => this.state().policyAnalytics);
  readonly billingReports = computed(() => this.state().billingReports);
  readonly filters = computed(() => this.state().filters);
  readonly error = computed(() => this.state().error);

  // =========================================================================
  // SELECTORS - LOADING STATES
  // =========================================================================

  readonly isLoadingSummary = computed(() => this.state().loadingSummary);
  readonly isLoadingApprovals = computed(() => this.state().loadingApprovals);
  readonly isLoadingPolicies = computed(() => this.state().loadingPolicies);
  readonly isLoadingBilling = computed(() => this.state().loadingBilling);
  readonly isLoading = computed(
    () =>
      this.state().loadingSummary ||
      this.state().loadingApprovals ||
      this.state().loadingPolicies ||
      this.state().loadingBilling
  );

  // =========================================================================
  // SELECTORS - DASHBOARD KPIs
  // =========================================================================

  readonly pendingApprovals = computed(() => this.summary()?.approvals.pending_count ?? 0);
  readonly approvedThisWeek = computed(() => this.summary()?.approvals.approved_this_week ?? 0);
  readonly approvalRate = computed(() => this.summary()?.approvals.approval_rate ?? 0);
  readonly avgProcessingDays = computed(() => this.summary()?.approvals.avg_processing_days ?? 0);

  readonly activePolicies = computed(() => this.summary()?.policies.active_count ?? 0);
  readonly totalPolicies = computed(() => this.summary()?.policies.total_policies ?? 0);
  readonly totalPremium = computed(() => this.summary()?.policies.total_premium ?? 0);
  readonly totalMembers = computed(() => this.summary()?.policies.total_members ?? 0);
  readonly forRenewalCount = computed(() => this.summary()?.policies.for_renewal_count ?? 0);

  readonly totalOutstanding = computed(() => this.summary()?.billing.total_outstanding ?? 0);
  readonly collectionRate = computed(() => this.summary()?.billing.collection_rate ?? 0);
  readonly overdueAmount = computed(() => this.summary()?.billing.overdue_amount ?? 0);
  readonly overdueCount = computed(() => this.summary()?.billing.overdue_count ?? 0);
  readonly unallocatedPayments = computed(() => this.summary()?.billing.unallocated_payments ?? 0);

  // =========================================================================
  // ACTIONS - LOAD DATA
  // =========================================================================

  loadSummary(filters?: Partial<ReportFilters>) {
    const currentFilters = { ...this.filters(), ...filters };
    this.state.update((s) => ({ ...s, loadingSummary: true, filters: currentFilters, error: null }));

    const params = this.buildParams(currentFilters);

    return this.http.get<ApiResponse<DashboardSummary>>(`${this.apiUrl}/summary`, { params }).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            summary: res.data,
            loadingSummary: false,
          })),
        error: (err) =>
          this.state.update((s) => ({
            ...s,
            loadingSummary: false,
            error: err.error?.message || 'Failed to load summary',
          })),
      })
    );
  }

  loadApprovalMetrics(filters?: Partial<ReportFilters>) {
    const currentFilters = { ...this.filters(), ...filters };
    this.state.update((s) => ({ ...s, loadingApprovals: true, filters: currentFilters, error: null }));

    const params = this.buildParams(currentFilters);

    return this.http.get<ApiResponse<ApprovalMetrics>>(`${this.apiUrl}/approvals`, { params }).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            approvalMetrics: res.data,
            loadingApprovals: false,
          })),
        error: (err) =>
          this.state.update((s) => ({
            ...s,
            loadingApprovals: false,
            error: err.error?.message || 'Failed to load approval metrics',
          })),
      })
    );
  }

  loadPolicyAnalytics(filters?: Partial<ReportFilters>) {
    const currentFilters = { ...this.filters(), ...filters };
    this.state.update((s) => ({ ...s, loadingPolicies: true, filters: currentFilters, error: null }));

    const params = this.buildParams(currentFilters);

    return this.http.get<ApiResponse<PolicyAnalytics>>(`${this.apiUrl}/policies`, { params }).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            policyAnalytics: res.data,
            loadingPolicies: false,
          })),
        error: (err) =>
          this.state.update((s) => ({
            ...s,
            loadingPolicies: false,
            error: err.error?.message || 'Failed to load policy analytics',
          })),
      })
    );
  }

  loadBillingReports(filters?: Partial<ReportFilters>) {
    const currentFilters = { ...this.filters(), ...filters };
    this.state.update((s) => ({ ...s, loadingBilling: true, filters: currentFilters, error: null }));

    const params = this.buildParams(currentFilters);

    return this.http.get<ApiResponse<BillingReports>>(`${this.apiUrl}/billing`, { params }).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            billingReports: res.data,
            loadingBilling: false,
          })),
        error: (err) =>
          this.state.update((s) => ({
            ...s,
            loadingBilling: false,
            error: err.error?.message || 'Failed to load billing reports',
          })),
      })
    );
  }

  loadAllReports(filters?: Partial<ReportFilters>) {
    this.loadSummary(filters).subscribe();
    this.loadApprovalMetrics(filters).subscribe();
    this.loadPolicyAnalytics(filters).subscribe();
    this.loadBillingReports(filters).subscribe();
  }

  // =========================================================================
  // ACTIONS - FILTERS
  // =========================================================================

  setFilters(filters: Partial<ReportFilters>) {
    this.state.update((s) => ({
      ...s,
      filters: { ...s.filters, ...filters },
    }));
  }

  setDateRange(fromDate: string, toDate: string) {
    this.setFilters({ from_date: fromDate, to_date: toDate });
  }

  setDatePreset(days: number | 'ytd' | 'mtd' | 'qtd') {
    const toDate = new Date();
    let fromDate: Date;

    if (days === 'ytd') {
      fromDate = new Date(toDate.getFullYear(), 0, 1);
    } else if (days === 'mtd') {
      fromDate = new Date(toDate.getFullYear(), toDate.getMonth(), 1);
    } else if (days === 'qtd') {
      const quarter = Math.floor(toDate.getMonth() / 3);
      fromDate = new Date(toDate.getFullYear(), quarter * 3, 1);
    } else {
      fromDate = new Date();
      fromDate.setDate(fromDate.getDate() - days);
    }

    this.setDateRange(
      fromDate.toISOString().split('T')[0],
      toDate.toISOString().split('T')[0]
    );
  }

  clearFilters() {
    this.state.update((s) => ({
      ...s,
      filters: {
        from_date: this.getDefaultFromDate(),
        to_date: this.getDefaultToDate(),
      },
    }));
  }

  // =========================================================================
  // ACTIONS - EXPORT
  // =========================================================================

  exportApprovals(format: 'csv' | 'pdf' = 'csv') {
    const params = this.buildParams(this.filters());
    params.append('format', format);

    return this.http.get(`${this.apiUrl}/export/approvals`, {
      params,
      responseType: 'blob',
    });
  }

  exportPolicies(format: 'csv' | 'pdf' = 'csv') {
    const params = this.buildParams(this.filters());
    params.append('format', format);

    return this.http.get(`${this.apiUrl}/export/policies`, {
      params,
      responseType: 'blob',
    });
  }

  exportBilling(format: 'csv' | 'pdf' = 'csv') {
    const params = this.buildParams(this.filters());
    params.append('format', format);

    return this.http.get(`${this.apiUrl}/export/billing`, {
      params,
      responseType: 'blob',
    });
  }

  // =========================================================================
  // UTILITY METHODS
  // =========================================================================

  private buildParams(filters: ReportFilters): HttpParams {
    let params = new HttpParams();

    if (filters.from_date) params = params.set('from_date', filters.from_date);
    if (filters.to_date) params = params.set('to_date', filters.to_date);
    if (filters.scheme_id) params = params.set('scheme_id', filters.scheme_id);
    if (filters.plan_id) params = params.set('plan_id', filters.plan_id);
    if (filters.policy_type) params = params.set('policy_type', filters.policy_type);
    if (filters.group_id) params = params.set('group_id', filters.group_id);
    if (filters.entity_type) params = params.set('entity_type', filters.entity_type);
    if (filters.workflow_id) params = params.set('workflow_id', filters.workflow_id);

    return params;
  }

  private getDefaultFromDate(): string {
    const date = new Date();
    date.setDate(date.getDate() - 90); // Last 90 days by default
    return date.toISOString().split('T')[0];
  }

  private getDefaultToDate(): string {
    return new Date().toISOString().split('T')[0];
  }

  clearError() {
    this.state.update((s) => ({ ...s, error: null }));
  }
}
