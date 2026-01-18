// libs/medical/data/src/lib/stores/quote.store.ts

import { HttpClient } from '@angular/common/http';
import { computed, inject, Injectable, signal } from '@angular/core';
import { tap } from 'rxjs';
import { GroupQuoteRequest, GroupQuoteResult } from '../models/medical-interfaces';
import { ApiResponse } from 'medical-data';

interface QuoteState {
  groupQuote: GroupQuoteResult | null;
  loading: boolean;
  saving: boolean;
}

@Injectable({ providedIn: 'root' })
export class QuoteStore {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = '/api/v1/medical';

  private readonly state = signal<QuoteState>({
    groupQuote: null,
    loading: false,
    saving: false,
  });

  // Selectors
  readonly groupQuote = computed(() => this.state().groupQuote);
  readonly isLoading = computed(() => this.state().loading);
  readonly isSaving = computed(() => this.state().saving);

  // =========================================================================
  // GROUP QUOTE OPERATIONS
  // =========================================================================

  generateGroupQuote(request: GroupQuoteRequest) {
    this.state.update((s) => ({ ...s, loading: true }));

    return this.http
      .post<ApiResponse<GroupQuoteResult>>(`${this.apiUrl}/group-quotes`, request)
      .pipe(
        tap({
          next: (res) =>
            this.state.update((s) => ({
              ...s,
              groupQuote: res.data,
              loading: false,
            })),
          error: () => this.state.update((s) => ({ ...s, loading: false })),
        })
      );
  }

  // =========================================================================
  // UTILITY METHODS
  // =========================================================================

  clearGroupQuote() {
    this.state.update((s) => ({ ...s, groupQuote: null }));
  }
}
