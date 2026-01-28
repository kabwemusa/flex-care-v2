import { Component, OnInit, inject, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';
import { FormsModule } from '@angular/forms';

import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatMenuModule } from '@angular/material/menu';
import { MatDividerModule } from '@angular/material/divider';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatDialogModule, MatDialog } from '@angular/material/dialog';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatChipsModule } from '@angular/material/chips';
import { MatSelectModule } from '@angular/material/select';
import { MatTabsModule } from '@angular/material/tabs';
import { MatBadgeModule } from '@angular/material/badge';

import {
  ApprovalStore,
  PendingApproval,
  ApprovalHistoryItem,
  ENTITY_TYPES,
} from 'core-approvals';
import { FeedbackService } from 'shared';

@Component({
  selector: 'app-pending-approvals',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    MatIconModule,
    MatButtonModule,
    MatMenuModule,
    MatDividerModule,
    MatProgressSpinnerModule,
    MatDialogModule,
    MatTooltipModule,
    MatChipsModule,
    MatSelectModule,
    MatTabsModule,
    MatBadgeModule,
  ],
  templateUrl: './pending-approvals.component.html',
})
export class PendingApprovalsComponent implements OnInit {
  readonly store = inject(ApprovalStore);
  private readonly router = inject(Router);
  private readonly dialog = inject(MatDialog);
  private readonly feedback = inject(FeedbackService);

  readonly entityTypes = ENTITY_TYPES;
  readonly selectedEntityType = signal<string>('');
  readonly activeTab = signal<'pending' | 'history'>('pending');

  // Computed filtered lists
  readonly filteredPendingApprovals = computed(() => {
    const approvals = this.store.pendingApprovals();
    const entityType = this.selectedEntityType();
    if (!entityType) return approvals;
    return approvals.filter((a) => a.entity_type === entityType);
  });

  // Stats computed
  readonly statsByEntityType = computed(() => {
    const approvals = this.store.pendingApprovals();
    const stats: Record<string, number> = {};
    approvals.forEach((a) => {
      stats[a.entity_type] = (stats[a.entity_type] || 0) + 1;
    });
    return stats;
  });

  ngOnInit() {
    this.loadData();
  }

  loadData() {
    this.store.loadPending(this.selectedEntityType() || undefined).subscribe();
    this.store.loadHistory(50).subscribe();
  }

  filterByEntityType(entityType: string) {
    this.selectedEntityType.set(entityType);
    this.loadData();
  }

  clearFilter() {
    this.selectedEntityType.set('');
    this.loadData();
  }

  onTabChange(tab: 'pending' | 'history') {
    this.activeTab.set(tab);
  }

  navigateToEntity(approval: PendingApproval) {
    const routes: Record<string, string> = {
      application: '/applications',
      claim: '/claims',
      payment: '/billing/payments',
      invoice: '/billing/invoices',
      policy_activation: '/policies',
      scheme: '/schemes',
      endorsement: '/policies',
      refund: '/billing/payments',
    };

    const basePath = routes[approval.entity_type] || '/';
    this.router.navigate([basePath, approval.entity_id]);
  }

  async approveItem(approval: PendingApproval, event: Event) {
    event.stopPropagation();

    const confirmed = await this.feedback.confirm(
      'Approve Request',
      `Are you sure you want to approve this ${this.getEntityTypeLabel(approval.entity_type)}?`
    );

    if (!confirmed) return;

    this.store.approve(approval.id).subscribe({
      next: () => {
        this.feedback.success('Request approved successfully');
        this.loadData();
      },
      error: (err) => {
        this.feedback.error(err?.error?.message || 'Failed to approve request');
      },
    });
  }

  async rejectItem(approval: PendingApproval, event: Event) {
    event.stopPropagation();

    const reason = await this.feedback.prompt(
      'Reject Request',
      'Please provide a reason for rejecting this request:',
      'textarea'
    );

    if (!reason) return;

    this.store.reject(approval.id, { reason }).subscribe({
      next: () => {
        this.feedback.success('Request rejected');
        this.loadData();
      },
      error: (err) => {
        this.feedback.error(err?.error?.message || 'Failed to reject request');
      },
    });
  }

  async returnItem(approval: PendingApproval, event: Event) {
    event.stopPropagation();

    const reason = await this.feedback.prompt(
      'Return for Amendment',
      'Please describe what amendments are needed:',
      'textarea'
    );

    if (!reason) return;

    this.store.returnForAmendment(approval.id, { reason }).subscribe({
      next: () => {
        this.feedback.success('Request returned for amendment');
        this.loadData();
      },
      error: (err) => {
        this.feedback.error(err?.error?.message || 'Failed to return request');
      },
    });
  }

  // Helper methods
  getEntityTypeLabel(code: string): string {
    const type = this.entityTypes.find((t) => t.code === code);
    return type?.label || code;
  }

  getEntityTypeIcon(code: string): string {
    const icons: Record<string, string> = {
      application: 'description',
      claim: 'medical_services',
      payment: 'payments',
      invoice: 'receipt_long',
      policy_activation: 'verified_user',
      scheme: 'category',
      endorsement: 'edit_note',
      refund: 'currency_exchange',
    };
    return icons[code] || 'folder';
  }

  getEntityTypeColor(code: string): string {
    const colors: Record<string, string> = {
      application: 'bg-blue-50 text-blue-600',
      claim: 'bg-purple-50 text-purple-600',
      payment: 'bg-green-50 text-green-600',
      invoice: 'bg-amber-50 text-amber-600',
      policy_activation: 'bg-emerald-50 text-emerald-600',
      scheme: 'bg-indigo-50 text-indigo-600',
      endorsement: 'bg-cyan-50 text-cyan-600',
      refund: 'bg-rose-50 text-rose-600',
    };
    return colors[code] || 'bg-slate-50 text-slate-600';
  }

  getActionBadgeColor(action: string): string {
    const colors: Record<string, string> = {
      approved: 'bg-green-100 text-green-700',
      rejected: 'bg-red-100 text-red-700',
      returned: 'bg-amber-100 text-amber-700',
    };
    return colors[action] || 'bg-slate-100 text-slate-600';
  }

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

  formatDate(date: string): string {
    return new Date(date).toLocaleDateString('en-ZM', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  }

  trackById(index: number, item: PendingApproval | ApprovalHistoryItem): string {
    return item.id;
  }
}
