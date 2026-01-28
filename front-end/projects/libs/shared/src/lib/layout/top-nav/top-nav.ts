import { Component, Input, Output, EventEmitter, inject, computed, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatMenuModule } from '@angular/material/menu';
import { MatBadgeModule } from '@angular/material/badge';
import { MatDividerModule } from '@angular/material/divider';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { AuthService } from 'core-auth';
import { NotificationStore, Notification } from 'core-approvals';
import { Router } from '@angular/router';
import { Subscription } from 'rxjs';

@Component({
  selector: 'lib-top-nav',
  standalone: true,
  imports: [
    CommonModule,
    MatButtonModule,
    MatIconModule,
    MatMenuModule,
    MatBadgeModule,
    MatDividerModule,
    MatTooltipModule,
    MatProgressSpinnerModule,
  ],
  templateUrl: './top-nav.html',
  styles: [
    `
      :host {
        display: contents;
      }
    `,
  ],
})
export class TopNav implements OnInit, OnDestroy {
  private authService = inject(AuthService);
  private notificationStore = inject(NotificationStore);
  private router = inject(Router);
  private pollingSubscription?: Subscription;

  @Input() sidebarCollapsed = false;
  @Output() toggleSidebar = new EventEmitter<void>();

  // Get user from AuthService
  user = this.authService.user;

  // Notification selectors
  notifications = this.notificationStore.notifications;
  unreadCount = this.notificationStore.unreadCount;
  hasUnread = this.notificationStore.hasUnread;
  isLoadingNotifications = this.notificationStore.isLoading;

  // Get user initials for avatar
  userInitials = computed(() => {
    const user = this.user();
    if (!user) return '??';

    const username = user.username || user.email;
    const parts = username.split(/[\s@.]+/);

    if (parts.length >= 2) {
      return (parts[0][0] + parts[1][0]).toUpperCase();
    }

    return username.substring(0, 2).toUpperCase();
  });

  // Get display name
  displayName = computed(() => {
    const user = this.user();
    return user?.username || user?.email || 'User';
  });

  // Get user role/type display
  userRole = computed(() => {
    const user = this.user();
    if (user?.is_system_admin) return 'System Admin';

    const modules = this.authService.modules();
    if (modules.length > 0) {
      return modules.map(m => m.charAt(0).toUpperCase() + m.slice(1)).join(', ');
    }

    return 'User';
  });

  ngOnInit(): void {
    // Load initial notifications
    this.notificationStore.loadUnreadCount().subscribe();
    this.notificationStore.loadNotifications(1, 10).subscribe();

    // Start polling for new notifications every 60 seconds
    this.pollingSubscription = this.notificationStore.startPolling(60000).subscribe();
  }

  ngOnDestroy(): void {
    this.pollingSubscription?.unsubscribe();
  }

  /**
   * Handle notification menu opened
   */
  onNotificationMenuOpened(): void {
    // Refresh notifications when menu opens
    this.notificationStore.loadNotifications(1, 10).subscribe();
  }

  /**
   * Mark notification as read
   */
  markAsRead(notification: Notification, event: Event): void {
    event.stopPropagation();
    if (!notification.read_at) {
      this.notificationStore.markAsRead(notification.id).subscribe();
    }
  }

  /**
   * Mark all notifications as read
   */
  markAllAsRead(): void {
    this.notificationStore.markAllAsRead().subscribe();
  }

  /**
   * Navigate to notification target
   */
  onNotificationClick(notification: Notification): void {
    // Mark as read
    if (!notification.read_at) {
      this.notificationStore.markAsRead(notification.id).subscribe();
    }

    // Navigate based on notification type
    const data = notification.data;
    if (data.action_url) {
      // Use the action URL from the notification
      const url = data.action_url.replace(/^https?:\/\/[^/]+/, '');
      this.router.navigateByUrl(url);
    } else if (data.request_id) {
      // Navigate to approval details
      this.router.navigate(['/approvals', data.request_id]);
    }
  }

  /**
   * Get time ago string
   */
  getTimeAgo(dateString: string): string {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffHours < 24) return `${diffHours}h ago`;
    if (diffDays < 7) return `${diffDays}d ago`;
    return date.toLocaleDateString();
  }

  /**
   * Get notification icon based on type
   */
  getNotificationIcon(type: string): string {
    switch (type) {
      case 'approval_required':
        return 'pending_actions';
      case 'approval_completed':
        return 'check_circle';
      case 'approval_rejected':
        return 'cancel';
      case 'approval_returned':
        return 'reply';
      case 'approval_progress':
        return 'trending_up';
      default:
        return 'notifications';
    }
  }

  /**
   * Get notification icon color based on type
   */
  getNotificationIconColor(type: string): string {
    switch (type) {
      case 'approval_required':
        return 'text-amber-500';
      case 'approval_completed':
        return 'text-green-500';
      case 'approval_rejected':
        return 'text-red-500';
      case 'approval_returned':
        return 'text-blue-500';
      case 'approval_progress':
        return 'text-purple-500';
      default:
        return 'text-gray-500';
    }
  }

  /**
   * Navigate to all approvals
   */
  viewAllApprovals(): void {
    this.router.navigate(['/approvals']);
  }

  /**
   * Handle logout
   */
  onLogout(): void {
    this.authService.logout().subscribe({
      next: () => {
        this.router.navigate(['/login']);
      },
      error: (error) => {
        console.error('Logout error:', error);
        // Still navigate to login even on error
        this.router.navigate(['/login']);
      }
    });
  }
}
