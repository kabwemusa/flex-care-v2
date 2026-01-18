// libs/medical/data/src/lib/stores/application.store.ts

import { HttpClient, HttpParams } from '@angular/common/http';
import { computed, inject, Injectable, signal } from '@angular/core';
import { tap } from 'rxjs';
import {
  Application,
  ApplicationMember,
  ApplicationAddon,
  Policy,
  ApplicationStats,
} from '../models/medical-interfaces';
import { ApiResponse, PaginatedResponse, PaginationMeta } from '../models/api-reponse';

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
  items: Application[]; // server page
  viewItems: Application[]; // filtered/sorted locally
  selected: Application | null;
  stats: ApplicationStats | null;
  loading: boolean;
  saving: boolean;
  planAddons: any[];
  loadingPlanAddons: boolean;
  pagination: PaginationMeta | null;
  members: ApplicationMember[];
  membersPagination: PaginationMeta | null;
}

@Injectable({ providedIn: 'root' })
export class ApplicationStore {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = '/api/v1/medical/applications';

  private readonly state = signal<ApplicationState>({
    items: [],
    viewItems: [],
    selected: null,
    stats: null,
    loading: false,
    saving: false,
    planAddons: [],
    loadingPlanAddons: false,
    pagination: null,
    members: [],
    membersPagination: null,
  });

  // Selectors
  readonly applications = computed(() => this.state().items);
  readonly selectedApplication = computed(() => this.state().selected);
  readonly isLoading = computed(() => this.state().loading);
  readonly isSaving = computed(() => this.state().saving);
  readonly stats = computed(() => this.state().stats);
  readonly viewItems = computed(() => this.state().viewItems);
  // Members and addons from selected application
  readonly members = computed(() => this.state().members);
  readonly membersPagination = computed(() => this.state().membersPagination);

  readonly addons = computed(() => this.state().selected?.addons ?? []);

  // Plan addons (configured for the plan)
  readonly planAddons = computed(() => this.state().planAddons);
  readonly isLoadingPlanAddons = computed(() => this.state().loadingPlanAddons);

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
  readonly pagination = computed(() => this.state().pagination);

  readonly currentPage = computed(() => this.pagination()?.current_page ?? 1);
  readonly totalPages = computed(() => this.pagination()?.last_page ?? 1);
  readonly totalItems = computed(() => this.pagination()?.total ?? 0);
  readonly pageSize = computed(() => this.pagination()?.per_page ?? 20);

  readonly hasNextPage = computed(() => this.currentPage() < this.totalPages());

  // =========================================================================
  // LOAD OPERATIONS
  // =========================================================================

  private syncViewItems(items: Application[]) {
    this.state.update((s) => ({
      ...s,
      viewItems: items,
    }));
  }

  loadNextPage() {
    if (!this.hasNextPage() || this.isLoading()) return;

    this.loadAll({
      page: this.currentPage() + 1,
      per_page: this.pageSize(),
    });
  }

  // Load specific page
  loadPage(page: number) {
    if (page < 1 || page > this.totalPages() || this.isLoading()) return;

    this.loadAll({
      page,
      per_page: this.pageSize(),
    });
  }

  // Change page size
  setPageSize(perPage: number) {
    this.loadAll({
      per_page: perPage,
      page: 1,
    });
  }

  loadAll(filters?: {
    search?: string;
    status?: string;
    application_type?: string;
    policy_type?: string;
    scheme_id?: string;
    plan_id?: string;
    group_id?: string;
    page?: number;
    per_page?: number;
  }) {
    this.state.update((s) => ({ ...s, loading: true }));

    let params = new HttpParams();

    Object.entries(filters ?? {}).forEach(([key, value]) => {
      if (value !== undefined && value !== null) {
        params = params.set(key, value);
      }
    });

    this.http.get<PaginatedResponse<Application>>(this.apiUrl, { params }).subscribe({
      next: (res) =>
        this.state.update((s) => {
          const isFirstPage = !res.meta || res.meta.current_page === 1;

          return {
            ...s,
            items: isFirstPage ? res.data : [...s.items, ...res.data],
            viewItems: isFirstPage ? res.data : [...s.viewItems, ...res.data],
            pagination: res.meta,
            loading: false,
          };
        }),
      error: () =>
        this.state.update((s) => ({
          ...s,
          loading: false,
        })),
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

  filterLocally(filters: {
    search?: string;
    status?: string;
    application_type?: string;
    policy_type?: string;
  }) {
    this.state.update((s) => {
      let data = [...s.items];

      if (filters.search) {
        const q = filters.search.toLowerCase();
        data = data.filter(
          (a) =>
            a.application_number.toLowerCase().includes(q) ||
            a.contact_name?.toLowerCase().includes(q) ||
            a.contact_email?.toLowerCase().includes(q)
        );
      }

      if (filters.status) {
        data = data.filter((a) => a.status === filters.status);
      }

      if (filters.application_type) {
        data = data.filter((a) => a.application_type === filters.application_type);
      }

      if (filters.policy_type) {
        data = data.filter((a) => a.policy_type === filters.policy_type);
      }

      return {
        ...s,
        viewItems: data,
      };
    });
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
  loadMembers(applicationId: string, page = 1, perPage = 10) {
    this.state.update((s) => ({ ...s, loading: true }));

    this.http
      .get<PaginatedResponse<ApplicationMember>>(`${this.apiUrl}/${applicationId}/members`, {
        params: { page, per_page: perPage },
      })
      .subscribe({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            members: res.data,
            membersPagination: res.meta,
            loading: false,
          })),
        error: () => this.state.update((s) => ({ ...s, loading: false })),
      });
  }

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

  /**
   * Load available plan addons for a given plan.
   * Updates state with configured addons (mandatory/optional/included).
   */
  loadPlanAddons(planId: string) {
    this.state.update((s) => ({ ...s, loadingPlanAddons: true }));

    this.http.get<ApiResponse<any[]>>(`/api/v1/medical/plans/${planId}/addons`).subscribe({
      next: (res) => {
        this.state.update((s) => ({
          ...s,
          planAddons: res.data || [],
          loadingPlanAddons: false,
        }));
      },
      error: () => {
        this.state.update((s) => ({
          ...s,
          planAddons: [],
          loadingPlanAddons: false,
        }));
      },
    });
  }

  /**
   * Clear plan addons from state
   */
  clearPlanAddons() {
    this.state.update((s) => ({ ...s, planAddons: [], loadingPlanAddons: false }));
  }

  /**
   * Add an addon to the application.
   * Triggers premium recalculation on backend.
   */
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

  /**
   * Remove an addon from the application.
   * Triggers premium recalculation on backend.
   */
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

  // =========================================================================
  // CORPORATE CENSUS IMPORT
  // =========================================================================

  /**
   * Import and validate census file (Step 1: Upload & Validate).
   * Returns import_key for creating application.
   */
  importCensus(formData: FormData) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<any>>(`${this.apiUrl}/import-census`, formData).pipe(
      tap({
        next: () => this.state.update((s) => ({ ...s, saving: false })),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  /**
   * Create application from imported census (Step 2: Create Application).
   * Uses import_key from importCensus.
   */
  createFromCensus(data: {
    import_key: string;
    scheme_id: string;
    plan_id: string;
    rate_card_id: string;
    inception_date: string;
    billing_frequency: string;
  }) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<Application>>(`${this.apiUrl}/create-from-census`, data).pipe(
      tap({
        next: (res) => {
          this.state.update((s) => ({
            ...s,
            items: [res.data, ...s.items],
            selected: res.data,
            saving: false,
          }));
        },
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  /**
   * Create multiple applications from imported census (Multi-Plan Support).
   * Uses import_key from importCensus and plan_mapping.
   */
  createMultiPlanFromCensus(data: {
    import_key: string;
    group_id: string;
    scheme_id: string;
    rate_card_id: string;
    inception_date: string;
    billing_frequency: string;
    plan_mapping: Record<string, string>; // salary_band/department => plan_id
    mapping_type: 'salary_band' | 'department' | 'job_title';
  }) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http
      .post<
        ApiResponse<{
          applications: Array<{
            application_id: string;
            plan_id: string;
            plan_name: string;
            member_count: number;
          }>;
          total_members: number;
          plans_used: number;
        }>
      >(`${this.apiUrl}/create-multi-plan-from-census`, data)
      .pipe(
        tap({
          next: (res) => {
            this.state.update((s) => ({
              ...s,
              saving: false,
            }));
            // Reload applications list to show new ones
            this.loadAll({ group_id: data.group_id });
          },
          error: () => this.state.update((s) => ({ ...s, saving: false })),
        })
      );
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
