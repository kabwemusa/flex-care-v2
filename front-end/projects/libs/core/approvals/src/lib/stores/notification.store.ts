// Notification Store

import { HttpClient, HttpParams } from '@angular/common/http';
import { computed, inject, Injectable, signal } from '@angular/core';
import { tap, interval } from 'rxjs';
import { Notification, NotificationResponse, UnreadCountResponse } from '../models/notification.models';

interface ApiResponse<T> {
  status: string;
  message: string;
  data: T;
}

interface NotificationState {
  items: Notification[];
  unreadCount: number;
  loading: boolean;
  currentPage: number;
  lastPage: number;
  total: number;
}

@Injectable({ providedIn: 'root' })
export class NotificationStore {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = '/api/notifications';

  private readonly state = signal<NotificationState>({
    items: [],
    unreadCount: 0,
    loading: false,
    currentPage: 1,
    lastPage: 1,
    total: 0,
  });

  // Selectors
  readonly notifications = computed(() => this.state().items);
  readonly unreadCount = computed(() => this.state().unreadCount);
  readonly isLoading = computed(() => this.state().loading);
  readonly currentPage = computed(() => this.state().currentPage);
  readonly lastPage = computed(() => this.state().lastPage);
  readonly total = computed(() => this.state().total);

  // Computed
  readonly hasUnread = computed(() => this.unreadCount() > 0);
  readonly unreadNotifications = computed(() => this.notifications().filter((n) => !n.read_at));
  readonly readNotifications = computed(() => this.notifications().filter((n) => !!n.read_at));
  readonly hasMore = computed(() => this.currentPage() < this.lastPage());

  /**
   * Load notifications
   */
  loadNotifications(page = 1, perPage = 20, unreadOnly = false) {
    this.state.update((s) => ({ ...s, loading: true }));

    let params = new HttpParams()
      .set('page', page.toString())
      .set('per_page', perPage.toString());

    if (unreadOnly) {
      params = params.set('unread_only', 'true');
    }

    return this.http.get<ApiResponse<NotificationResponse>>(this.apiUrl, { params }).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            items: page === 1 ? res.data.notifications : [...s.items, ...res.data.notifications],
            currentPage: res.data.meta.current_page,
            lastPage: res.data.meta.last_page,
            total: res.data.meta.total,
            loading: false,
          })),
        error: () => this.state.update((s) => ({ ...s, loading: false })),
      })
    );
  }

  /**
   * Load unread count
   */
  loadUnreadCount() {
    return this.http.get<ApiResponse<UnreadCountResponse>>(`${this.apiUrl}/unread-count`).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            unreadCount: res.data.count,
          })),
      })
    );
  }

  /**
   * Mark a notification as read
   */
  markAsRead(id: string) {
    return this.http.put<ApiResponse<{ id: string; read_at: string }>>(`${this.apiUrl}/${id}/read`, {}).pipe(
      tap({
        next: (res) =>
          this.state.update((s) => ({
            ...s,
            items: s.items.map((n) => (n.id === id ? { ...n, read_at: res.data.read_at } : n)),
            unreadCount: Math.max(0, s.unreadCount - 1),
          })),
      })
    );
  }

  /**
   * Mark all notifications as read
   */
  markAllAsRead() {
    return this.http.put<ApiResponse<null>>(`${this.apiUrl}/mark-all-read`, {}).pipe(
      tap({
        next: () =>
          this.state.update((s) => ({
            ...s,
            items: s.items.map((n) => ({ ...n, read_at: n.read_at || new Date().toISOString() })),
            unreadCount: 0,
          })),
      })
    );
  }

  /**
   * Delete a notification
   */
  delete(id: string) {
    return this.http.delete<ApiResponse<null>>(`${this.apiUrl}/${id}`).pipe(
      tap({
        next: () =>
          this.state.update((s) => {
            const notification = s.items.find((n) => n.id === id);
            const wasUnread = notification && !notification.read_at;
            return {
              ...s,
              items: s.items.filter((n) => n.id !== id),
              unreadCount: wasUnread ? Math.max(0, s.unreadCount - 1) : s.unreadCount,
              total: s.total - 1,
            };
          }),
      })
    );
  }

  /**
   * Delete all read notifications
   */
  deleteAllRead() {
    return this.http.delete<ApiResponse<null>>(`${this.apiUrl}/read/all`).pipe(
      tap({
        next: () =>
          this.state.update((s) => ({
            ...s,
            items: s.items.filter((n) => !n.read_at),
            total: s.unreadCount,
          })),
      })
    );
  }

  /**
   * Load more notifications (pagination)
   */
  loadMore() {
    if (this.hasMore() && !this.isLoading()) {
      return this.loadNotifications(this.currentPage() + 1);
    }
    return null;
  }

  /**
   * Refresh notifications
   */
  refresh() {
    this.loadUnreadCount().subscribe();
    return this.loadNotifications(1);
  }

  /**
   * Start polling for new notifications
   */
  startPolling(intervalMs = 60000) {
    return interval(intervalMs).pipe(
      tap(() => this.loadUnreadCount().subscribe())
    );
  }

  /**
   * Add a new notification to the store (for real-time updates)
   */
  addNotification(notification: Notification) {
    this.state.update((s) => ({
      ...s,
      items: [notification, ...s.items],
      unreadCount: s.unreadCount + 1,
      total: s.total + 1,
    }));
  }
}
