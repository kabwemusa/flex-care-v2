import { Injectable, signal } from '@angular/core';

export type ToastType = 'success' | 'error' | 'warning' | 'info';

export interface Toast {
  id: string;
  type: ToastType;
  message: string;
  title?: string;
  /** Auto-dismiss after this many milliseconds (default: 4000). Pass 0 to persist. */
  duration: number;
}

/**
 * ToastService
 *
 * Centralized, non-blocking user feedback. Replaces inline `@if(error())` divs
 * scattered across components. Add <app-toast></app-toast> once to the root
 * layout (portal-layout, web-layout) and inject this service wherever you need
 * to show feedback.
 *
 * Usage:
 *   this.toast.success('Application submitted!');
 *   this.toast.error('Failed to load data', 'Network error');
 *   this.toast.warning('Your session will expire in 5 minutes');
 */
@Injectable({ providedIn: 'root' })
export class ToastService {
  readonly toasts = signal<Toast[]>([]);

  success(message: string, title?: string, duration = 4000): void {
    this.add({ type: 'success', message, title, duration });
  }

  error(message: string, title?: string, duration = 6000): void {
    this.add({ type: 'error', message, title, duration });
  }

  warning(message: string, title?: string, duration = 5000): void {
    this.add({ type: 'warning', message, title, duration });
  }

  info(message: string, title?: string, duration = 4000): void {
    this.add({ type: 'info', message, title, duration });
  }

  dismiss(id: string): void {
    this.toasts.update((list) => list.filter((t) => t.id !== id));
  }

  private add(toast: Omit<Toast, 'id'>): void {
    const id = `toast-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`;
    this.toasts.update((list) => [...list, { ...toast, id }]);

    if (toast.duration > 0) {
      setTimeout(() => this.dismiss(id), toast.duration);
    }
  }
}
