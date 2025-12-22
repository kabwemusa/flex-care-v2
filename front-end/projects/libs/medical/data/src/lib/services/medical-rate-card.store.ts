// @medical/data/rate-card.store.ts
import { inject, Injectable, signal, computed } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { tap } from 'rxjs';
import { ApiResponse } from '../models/api-reponse';

export interface RateCardEntry {
  min_age: number;
  max_age: number;
  member_type: 'Principal' | 'Spouse' | 'Child';
  price: number;
  gender?: string;
  region_code?: string;
}

export interface RateCard {
  id?: number;
  plan_id: number;
  name: string;
  currency: string;
  is_active: boolean;
  valid_from: string;
  valid_until?: string;
  entries?: RateCardEntry[];
  entries_count?: number;
}

@Injectable({ providedIn: 'root' })
export class RateCardStore {
  private http = inject(HttpClient);
  private apiUrl = '/api/v1/medical/rate-cards';

  private state = signal<{ items: RateCard[]; loading: boolean }>({
    items: [],
    loading: false,
  });

  readonly rateCards = computed(() => this.state().items);
  readonly isLoading = computed(() => this.state().loading);

  loadAll() {
    this.state.update((s) => ({ ...s, loading: true }));
    this.http.get<ApiResponse<RateCard[]>>(this.apiUrl).subscribe((res) => {
      this.state.update((s) => ({ ...s, items: res.data, loading: false }));
    });
  }

  // Handle Metadata CRUD
  upsert(card: Partial<RateCard>) {
    const req = card.id
      ? this.http.put<ApiResponse<RateCard>>(`${this.apiUrl}/${card.id}`, card)
      : this.http.post<ApiResponse<RateCard>>(this.apiUrl, card);

    return req.pipe(tap(() => this.loadAll()));
  }

  // Handle Matrix Synchronization
  syncPricing(cardId: number, entries: RateCardEntry[]) {
    return this.http
      .post<ApiResponse<any>>(`${this.apiUrl}/${cardId}/entries`, { entries })
      .pipe(tap(() => this.loadAll()));
  }

  delete(id: number) {
    return this.http
      .delete(`${this.apiUrl}/${id}`)
      .pipe(
        tap(() => this.state.update((s) => ({ ...s, items: s.items.filter((c) => c.id !== id) })))
      );
  }

  getRateCardDetails(id: number) {
    this.state.update((s) => ({ ...s, loading: true }));
    return this.http
      .get<ApiResponse<RateCard>>(`${this.apiUrl}/${id}`)
      .pipe(tap(() => this.state.update((s) => ({ ...s, loading: false }))));
  }
}
