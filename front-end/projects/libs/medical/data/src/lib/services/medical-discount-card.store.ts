// @medical/data/discount-card.store.ts
import { HttpClient } from '@angular/common/http';
import { Injectable, computed, inject, signal } from '@angular/core';
import { tap } from 'rxjs';
import { ApiResponse } from '../models/api-reponse';

export interface DiscountRule {
  field: string; // e.g., 'frequency', 'payment_method'
  operator: string; // e.g., '=', '>', 'IN'
  value: string; // e.g., 'annual', 'visa'
}

export interface MedicalDiscount {
  id?: number;
  plan_id?: number | null;
  name: string;
  code: string;
  type: 'percentage' | 'fixed';
  value: number;
  trigger_rule: DiscountRule;
  valid_from: string;
  valid_until?: string | null;
  plan?: { name: string };
}

@Injectable({ providedIn: 'root' })
export class DiscountCardStore {
  private http = inject(HttpClient);
  private apiUrl = '/api/v1/medical/discount-cards';

  private state = signal<{ items: MedicalDiscount[]; loading: boolean }>({
    items: [],
    loading: false,
  });

  readonly discounts = computed(() => this.state().items);
  readonly isLoading = computed(() => this.state().loading);

  loadAll() {
    this.state.update((s) => ({ ...s, loading: true }));
    this.http.get<ApiResponse<MedicalDiscount[]>>(this.apiUrl).subscribe((res) => {
      this.state.update((s) => ({ ...s, items: res.data, loading: false }));
    });
  }

  upsert(discount: Partial<MedicalDiscount>) {
    const request = discount.id
      ? this.http.put<ApiResponse<MedicalDiscount>>(`${this.apiUrl}/${discount.id}`, discount)
      : this.http.post<ApiResponse<MedicalDiscount>>(this.apiUrl, discount);

    return request.pipe(
      tap((res) => {
        this.state.update((s) => ({
          ...s,
          items: discount.id
            ? s.items.map((i) => (i.id === res.data.id ? res.data : i))
            : [res.data, ...s.items],
        }));
      })
    );
  }

  delete(id: number) {
    return this.http.delete(`${this.apiUrl}/${id}`).pipe(
      tap(() => {
        this.state.update((s) => ({
          ...s,
          items: s.items.filter((i) => i.id !== id),
        }));
      })
    );
  }
}
