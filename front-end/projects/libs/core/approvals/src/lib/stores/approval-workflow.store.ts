// Approval Workflow Store

import { HttpClient, HttpParams } from '@angular/common/http';
import { computed, inject, Injectable, signal } from '@angular/core';
import { tap } from 'rxjs';
import {
  ApprovalWorkflow,
  ApprovalStep,
  CreateApprovalWorkflowRequest,
  UpdateApprovalWorkflowRequest,
  CreateStepRequest,
  UpdateStepRequest,
  ReorderStepsRequest,
  EntityType,
} from '../models/approval.models';

interface ApiResponse<T> {
  status: string;
  message: string;
  data: T;
}

interface ApprovalWorkflowState {
  items: ApprovalWorkflow[];
  selected: ApprovalWorkflow | null;
  entityTypes: Record<string, string>;
  loading: boolean;
  saving: boolean;
}

export interface ApprovalWorkflowFilters {
  search?: string;
  entity_type?: string;
  is_active?: boolean;
}

@Injectable({ providedIn: 'root' })
export class ApprovalWorkflowStore {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = '/api/settings/approval-workflows';

  private readonly state = signal<ApprovalWorkflowState>({
    items: [],
    selected: null,
    entityTypes: {},
    loading: false,
    saving: false,
  });

  // Selectors
  readonly workflows = computed(() => this.state().items);
  readonly selectedWorkflow = computed(() => this.state().selected);
  readonly entityTypes = computed(() => this.state().entityTypes);
  readonly isLoading = computed(() => this.state().loading);
  readonly isSaving = computed(() => this.state().saving);

  // Computed
  readonly activeWorkflows = computed(() => this.workflows().filter((w) => w.is_active));
  readonly entityTypeOptions = computed(() =>
    Object.entries(this.entityTypes()).map(([code, label]) => ({ code, label }))
  );

  /**
   * Load all approval workflows
   */
  loadAll(filters?: ApprovalWorkflowFilters) {
    this.state.update((s) => ({ ...s, loading: true }));

    let params = new HttpParams();
    if (filters?.search) params = params.set('search', filters.search);
    if (filters?.entity_type) params = params.set('entity_type', filters.entity_type);
    if (filters?.is_active !== undefined) params = params.set('is_active', filters.is_active.toString());

    return this.http.get<ApiResponse<ApprovalWorkflow[]>>(this.apiUrl, { params }).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            items: res.data,
            loading: false,
          })),
        error: () => this.state.update((s) => ({ ...s, loading: false })),
      })
    );
  }

  /**
   * Load a single approval workflow by ID
   */
  loadOne(id: string) {
    this.state.update((s) => ({ ...s, loading: true }));

    return this.http.get<ApiResponse<ApprovalWorkflow>>(`${this.apiUrl}/${id}`).pipe(
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

  /**
   * Load entity types
   */
  loadEntityTypes() {
    return this.http.get<ApiResponse<Record<string, string>>>(`${this.apiUrl}/entity-types`).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            entityTypes: res.data,
          })),
      })
    );
  }

  /**
   * Create a new approval workflow
   */
  create(data: CreateApprovalWorkflowRequest) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<ApprovalWorkflow>>(this.apiUrl, data).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            items: [...s.items, res.data],
            selected: res.data,
            saving: false,
          })),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  /**
   * Update an approval workflow
   */
  update(id: string, data: UpdateApprovalWorkflowRequest) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.put<ApiResponse<ApprovalWorkflow>>(`${this.apiUrl}/${id}`, data).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            items: s.items.map((w) => (w.id === id ? res.data : w)),
            selected: s.selected?.id === id ? res.data : s.selected,
            saving: false,
          })),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  /**
   * Delete an approval workflow
   */
  delete(id: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.delete<ApiResponse<null>>(`${this.apiUrl}/${id}`).pipe(
      tap({
        next: () =>
          this.state.update((s) => ({
            ...s,
            items: s.items.filter((w) => w.id !== id),
            selected: s.selected?.id === id ? null : s.selected,
            saving: false,
          })),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  /**
   * Add a step to a workflow
   */
  addStep(workflowId: string, data: CreateStepRequest) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.post<ApiResponse<ApprovalStep>>(`${this.apiUrl}/${workflowId}/steps`, data).pipe(
      tap({
        next: (res) => {
          // Reload the workflow to get updated steps
          this.loadOne(workflowId).subscribe();
          this.state.update((s) => ({ ...s, saving: false }));
        },
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  /**
   * Update a workflow step
   */
  updateStep(workflowId: string, stepId: string, data: UpdateStepRequest) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.put<ApiResponse<ApprovalStep>>(`${this.apiUrl}/${workflowId}/steps/${stepId}`, data).pipe(
      tap({
        next: (res) => {
          // Reload the workflow to get updated steps
          this.loadOne(workflowId).subscribe();
          this.state.update((s) => ({ ...s, saving: false }));
        },
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  /**
   * Delete a workflow step
   */
  deleteStep(workflowId: string, stepId: string) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.delete<ApiResponse<null>>(`${this.apiUrl}/${workflowId}/steps/${stepId}`).pipe(
      tap({
        next: () => {
          // Reload the workflow to get updated steps
          this.loadOne(workflowId).subscribe();
          this.state.update((s) => ({ ...s, saving: false }));
        },
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  /**
   * Reorder workflow steps
   */
  reorderSteps(workflowId: string, data: ReorderStepsRequest) {
    this.state.update((s) => ({ ...s, saving: true }));

    return this.http.put<ApiResponse<ApprovalWorkflow>>(`${this.apiUrl}/${workflowId}/steps/reorder`, data).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            items: s.items.map((w) => (w.id === workflowId ? res.data : w)),
            selected: s.selected?.id === workflowId ? res.data : s.selected,
            saving: false,
          })),
        error: () => this.state.update((s) => ({ ...s, saving: false })),
      })
    );
  }

  /**
   * Clear selected workflow
   */
  clearSelected() {
    this.state.update((s) => ({ ...s, selected: null }));
  }

  /**
   * Set selected workflow
   */
  setSelected(workflow: ApprovalWorkflow | null) {
    this.state.update((s) => ({ ...s, selected: workflow }));
  }
}
