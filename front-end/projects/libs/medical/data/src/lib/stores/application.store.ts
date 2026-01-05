// libs/medical/data/src/lib/stores/application.store.ts

import { HttpClient, HttpParams } from '@angular/common/http';
import { computed, inject, Injectable, signal } from '@angular/core';
import { tap } from 'rxjs';
import {
  ApiResponse,
  Application,
  ApplicationMember,
  ApplicationAddon,
  Policy,
  ApplicationStats,
} from '../models/medical-interfaces';

export interface UnderwritingDecision {
  decision: 'approve' | 'decline' | 'terms';
  loadings?: AppliedLoadingInput[];
  exclusions?: AppliedExclusionInput[];
  notes?: string;
}

export interface AppliedLoadingInput {
  loading_rule_id?: string;
  condition_name: string;
  icd10_code?: string;
  loading_type: string;
  value: number;
  duration_type?: string;
  duration_months?: number;
  notes?: string;
}

export interface AppliedExclusionInput {
  exclusion_name: string;
  exclusion_type?: string;
  benefit_id?: string;
  icd10_codes?: string[];
  description?: string;
  is_permanent?: boolean;
  end_date?: string;
  notes?: string;
}

interface ApplicationState {
  items: Application[];
  selected: Application | null;
  stats: ApplicationStats | null;
  loading: boolean;
  saving: boolean;
}

@Injectable({ providedIn: 'root' })
export class ApplicationStore {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = '/api/v1/medical/applications';

  private readonly state = signal<ApplicationState>({
    items: [],
    selected: null,
    stats: null,
    loading: false,
    saving: false,
  });

  // Selectors
  readonly applications = computed(() => this.state().items);
  readonly selectedApplication = computed(() => this.state().selected);
  readonly isLoading = computed(() => this.state().loading);
  readonly isSaving = computed(() => this.state().saving);
  readonly stats = computed(() => this.state().stats);

  // Members and addons from selected application
  readonly members = computed(() => this.state().selected?.members ?? []);
  readonly addons = computed(() => this.state().selected?.addons ?? []);

  // Computed selectors
  readonly draftApplications = computed(() =>
    this.applications().filter((a) => a.status === 'draft')
  );
  readonly quotedApplications = computed(() =>
    this.applications().filter((a) => a.status === 'quoted')
  );
  readonly pendingUnderwriting = computed(() =>
    this.applications().filter((a) => a.status === 'submitted' || a.status === 'underwriting')
  );
  readonly approvedApplications = computed(() =>
    this.applications().filter((a) => a.status === 'approved')
  );
  readonly acceptedApplications = computed(() =>
    this.applications().filter((a) => a.status === 'accepted')
  );

  readonly principalMembers = computed(() => this.members().filter((m) => m.is_principal));
  readonly dependentMembers = computed(() => this.members().filter((m) => m.is_dependent));
  readonly pendingUwMembers = computed(() =>
    this.members().filter((m) => m.underwriting_status === 'pending')
  );

  // =========================================================================
  // LOAD OPERATIONS
  // =========================================================================

  loadAll(filters?: {
    search?: string;
    status?: string;
    application_type?: string;
    policy_type?: string;
    scheme_id?: string;
    plan_id?: string;
    group_id?: string;
  }) {
    this.state.update((s) => ({ ...s, loading: true }));

    let params = new HttpParams();
    if (filters?.search) params = params.set('search', filters.search);
    if (filters?.status) params = params.set('status', filters.status);
    if (filters?.application_type)
      params = params.set('application_type', filters.application_type);
    if (filters?.policy_type) params = params.set('policy_type', filters.policy_type);
    if (filters?.scheme_id) params = params.set('scheme_id', filters.scheme_id);
    if (filters?.plan_id) params = params.set('plan_id', filters.plan_id);
    if (filters?.group_id) params = params.set('group_id', filters.group_id);

    this.http.get<ApiResponse<Application[]>>(this.apiUrl, { params }).subscribe({
      next: (res) =>
        this.state.update((s) => ({
          ...s,
          items: res.data,
          loading: false,
        })),
      error: () => this.state.update((s) => ({ ...s, loading: false })),
    });
  }

  loadOne(id: string) {
    this.state.update((s) => ({ ...s, loading: true }));

    return this.http.get<ApiResponse<Application>>(`${this.apiUrl}/${id}`).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            selected: res.data,
            loading: false,
          })),
        error: () => this.state.update((s) => ({ ...s, loading: false })),
      })
    );
  }

  // =========================================================================
  // CRUD OPERATIONS
  // =========================================================================

  create(data: Partial<Application> & { members?: Partial<ApplicationMember>[] }) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<Application>>(this.apiUrl, data).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            items: [res.data, ...s.items],
            selected: res.data,
            saving: false,
          })),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  update(id: string, changes: Partial<Application>) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.patch<ApiResponse<Application>>(`${this.apiUrl}/${id}`, changes).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            items: s.items.map((item) => (item.id === id ? res.data : item)),
            selected: s.selected?.id === id ? res.data : s.selected,
            saving: false,
          })),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  delete(id: string) {
    return this.http.delete<ApiResponse<void>>(`${this.apiUrl}/${id}`).pipe(
      tap(() =>
        this.state.update((s) => ({
          ...s,
          items: s.items.filter((item) => item.id !== id),
          selected: s.selected?.id === id ? null : s.selected,
        }))
      )
    );
  }

  // =========================================================================
  // WORKFLOW ACTIONS
  // =========================================================================

  calculatePremium(id: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<Application>>(`${this.apiUrl}/${id}/calculate-premium`, {})
      .pipe(
        tap({
          next: (res) => this.updateApplicationInState(id, res.data),
          error: () => this.state.update((s) => ({ ...s, saving: false })),
        })
      );
  }

  markAsQuoted(id: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<Application>>(`${this.apiUrl}/${id}/quote`, {}).pipe(
      tap({
        next: (res) => this.updateApplicationInState(id, res.data),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  submit(id: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<Application>>(`${this.apiUrl}/${id}/submit`, {}).pipe(
      tap({
        next: (res) => this.updateApplicationInState(id, res.data),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  startUnderwriting(id: string, underwriterId?: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<Application>>(`${this.apiUrl}/${id}/start-underwriting`, {
        underwriter_id: underwriterId,
      })
      .pipe(
        tap({
          next: (res) => this.updateApplicationInState(id, res.data),
          error: () => this.state.update((s) => ({ ...s, saving: false })),
        })
      );
  }

  approve(id: string, notes?: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<Application>>(`${this.apiUrl}/${id}/approve`, { notes }).pipe(
      tap({
        next: (res) => this.updateApplicationInState(id, res.data),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  decline(id: string, reason: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<Application>>(`${this.apiUrl}/${id}/decline`, { reason })
      .pipe(
        tap({
          next: (res) => this.updateApplicationInState(id, res.data),
          error: () => this.state.update((s) => ({ ...s, saving: false })),
        })
      );
  }

  refer(id: string, reason: string, underwriterId?: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<Application>>(`${this.apiUrl}/${id}/refer`, {
        reason,
        underwriter_id: underwriterId,
      })
      .pipe(
        tap({
          next: (res) => this.updateApplicationInState(id, res.data),
          error: () => this.state.update((s) => ({ ...s, saving: false })),
        })
      );
  }

  accept(id: string, acceptanceReference?: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<Application>>(`${this.apiUrl}/${id}/accept`, {
        acceptance_reference: acceptanceReference,
      })
      .pipe(
        tap({
          next: (res) => this.updateApplicationInState(id, res.data),
          error: () => this.state.update((s) => ({ ...s, saving: false })),
        })
      );
  }

  convert(id: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<Policy>>(`${this.apiUrl}/${id}/convert`, {}).pipe(
      tap({
        next: (res) => {
          // Update the application status to converted
          this.state.update((s) => ({
            ...s,
            items: s.items.map((item) =>
              item.id === id
                ? { ...item, status: 'converted', converted_policy_id: res.data.id }
                : item
            ),
            selected:
              s.selected?.id === id
                ? { ...s.selected, status: 'converted', converted_policy_id: res.data.id }
                : s.selected,
            saving: false,
          }));
        },
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  // =========================================================================
  // QUOTE OPERATIONS
  // =========================================================================

  downloadQuote(id: string) {
    return this.http.get<any>(`${this.apiUrl}/${id}/quote/download`);
  }

  emailQuote(id: string, email: string, message?: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<any>>(`${this.apiUrl}/${id}/quote/email`, {
        email,
        message,
      })
      .pipe(
        tap({
          next: () => this.state.update((s) => ({ ...s, saving: false })),
          error: () => this.state.update((s) => ({ ...s, saving: false })),
        })
      );
  }

  // =========================================================================
  // MEMBER OPERATIONS
  // =========================================================================

  addMember(applicationId: string, member: Partial<ApplicationMember>) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<Application>>(`${this.apiUrl}/${applicationId}/members`, member)
      .pipe(
        tap({
          next: (res) => this.updateApplicationInState(applicationId, res.data),
          error: () => this.state.update((s) => ({ ...s, saving: false })),
        })
      );
  }

  updateMember(applicationId: string, memberId: string, changes: Partial<ApplicationMember>) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .patch<ApiResponse<Application>>(
        `${this.apiUrl}/${applicationId}/members/${memberId}`,
        changes
      )
      .pipe(
        tap({
          next: (res) => this.updateApplicationInState(applicationId, res.data),
          error: () => this.state.update((s) => ({ ...s, saving: false })),
        })
      );
  }

  removeMember(applicationId: string, memberId: string) {
    return this.http
      .delete<ApiResponse<Application>>(`${this.apiUrl}/${applicationId}/members/${memberId}`)
      .pipe(
        tap({
          next: (res) => this.updateApplicationInState(applicationId, res.data),
        })
      );
  }

  underwriteMember(applicationId: string, memberId: string, decision: UnderwritingDecision) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<Application>>(
        `${this.apiUrl}/${applicationId}/members/${memberId}/underwrite`,
        decision
      )
      .pipe(
        tap({
          next: (res) => this.updateApplicationInState(applicationId, res.data),
          error: () => this.state.update((s) => ({ ...s, saving: false })),
        })
      );
  }

  // =========================================================================
  // ADDON OPERATIONS
  // =========================================================================

  addAddon(applicationId: string, addonId: string, addonRateId?: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<ApiResponse<Application>>(`${this.apiUrl}/${applicationId}/addons`, {
        addon_id: addonId,
        addon_rate_id: addonRateId,
      })
      .pipe(
        tap({
          next: (res) => this.updateApplicationInState(applicationId, res.data),
          error: () => this.state.update((s) => ({ ...s, saving: false })),
        })
      );
  }

  removeAddon(applicationId: string, addonId: string) {
    return this.http
      .delete<ApiResponse<Application>>(`${this.apiUrl}/${applicationId}/addons/${addonId}`)
      .pipe(
        tap({
          next: (res) => this.updateApplicationInState(applicationId, res.data),
        })
      );
  }

  // Add this to your store class to sync stats into the signal state
  loadStats() {
    this.http.get<ApiResponse<ApplicationStats>>(`${this.apiUrl}/stats`).subscribe({
      next: (res) => {
        this.state.update((s) => ({
          ...s,
          stats: res.data, // Updates the signal, triggering the component
        }));
      },
      error: (err) => console.error('Failed to load stats', err),
    });
  }

  // =========================================================================
  // DOCUMENT OPERATIONS
  // =========================================================================

  loadDocuments(applicationId: string) {
    return this.http.get<ApiResponse<any[]>>(`${this.apiUrl}/${applicationId}/documents`);
  }

  uploadDocument(applicationId: string, formData: FormData) {
    return this.http
      .post<ApiResponse<any>>(`${this.apiUrl}/${applicationId}/documents`, formData)
      .pipe(
        tap({
          next: () => {
            // Reload the application to get updated documents
            this.loadOne(applicationId).subscribe();
          },
        })
      );
  }

  // =========================================================================
  // UTILITY METHODS
  // =========================================================================

  clearSelected() {
    this.state.update((s) => ({ ...s, selected: null }));
  }

  private updateApplicationInState(id: string, data: Application) {
    this.state.update((s) => ({
      ...s,
      items: s.items.map((item) => (item.id === id ? data : item)),
      selected: s.selected?.id === id ? data : s.selected,
      saving: false,
    }));
  }
}
