// libs/medical/feature/src/lib/medical-policy-detail/medical-policy-detail.ts

import { Component, OnInit, inject, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';

// Material Imports
import { MatTabsModule } from '@angular/material/tabs';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatMenuModule } from '@angular/material/menu';
import { MatDividerModule } from '@angular/material/divider';
import { MatChipsModule } from '@angular/material/chips';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatDialog } from '@angular/material/dialog';
import { MatTableModule } from '@angular/material/table';
import { MatTooltipModule } from '@angular/material/tooltip';

// Domain Imports
import {
  PolicyStore,
  Policy,
  Member,
  POLICY_STATUSES,
  POLICY_TYPES,
  MEMBER_TYPES,
  getLabelByValue,
  formatCurrency,
  getStatusConfig,
} from 'medical-data';
import { FeedbackService, PageHeaderComponent } from 'shared';
import { MedicalAddMemberToPolicyDialog } from '../dialogs/medical-add-member-to-policy-dialog/medical-add-member-to-policy-dialog';

@Component({
  selector: 'lib-medical-policy-detail',
  standalone: true,
  imports: [
    CommonModule,
    RouterLink,
    MatTabsModule,
    MatIconModule,
    MatButtonModule,
    MatMenuModule,
    MatDividerModule,
    MatChipsModule,
    MatProgressSpinnerModule,
    MatTableModule,
    MatTooltipModule,
    PageHeaderComponent,
  ],
  templateUrl: './medical-policy-detail.html',
})
export class MedicalPolicyDetail implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly store = inject(PolicyStore);
  private readonly dialog = inject(MatDialog);
  private readonly feedback = inject(FeedbackService);

  // Route param
  policyId = signal<string>('');
  activeTabIndex = signal(0);

  // Policy data from store
  policy = computed(() => this.store.selectedPolicy());
  isLoading = computed(() => this.store.isLoading());

  // Tab configuration
  readonly tabs = [
    { label: 'Overview', icon: 'info', key: 'overview' },
    { label: 'Members', icon: 'group', key: 'members' },
    { label: 'Add-ons', icon: 'add_circle', key: 'addons' },
    { label: 'Documents', icon: 'description', key: 'documents' },
  ];

  // Constants
  readonly POLICY_STATUSES = POLICY_STATUSES;
  readonly POLICY_TYPES = POLICY_TYPES;
  readonly MEMBER_TYPES = MEMBER_TYPES;
  readonly getLabelByValue = getLabelByValue;
  readonly formatCurrency = formatCurrency;
  readonly getStatusConfig = getStatusConfig;

  // Member table columns
  readonly memberColumns = ['name', 'type', 'dob', 'status', 'premium', 'loadings', 'exclusions'];

  ngOnInit() {
    this.route.paramMap.subscribe((params) => {
      const id = params.get('id');
      if (id) {
        this.policyId.set(id);
        this.loadPolicy(id);
      }
    });

    // Handle query params for tab selection
    this.route.queryParamMap.subscribe((params) => {
      const tab = params.get('tab');
      if (tab) {
        const tabIndex = this.tabs.findIndex((t) => t.key === tab);
        if (tabIndex !== -1) {
          this.activeTabIndex.set(tabIndex);
        }
      }
    });
  }

  loadPolicy(id: string) {
    this.store.loadOne(id).subscribe({
      error: () => {
        this.feedback.error('Failed to load policy details');
        this.router.navigate(['/policies']);
      },
    });
  }

  onTabChange(index: number) {
    this.activeTabIndex.set(index);
    const tab = this.tabs[index];
    if (tab) {
      this.router.navigate([], {
        relativeTo: this.route,
        queryParams: { tab: tab.key },
        queryParamsHandling: 'merge',
      });
    }
  }

  getStatusClasses(status: string): string {
    const config = getStatusConfig(this.POLICY_STATUSES, status);
    // Compose utility classes from the status config (bgColor + color)
    return `${config?.bgColor ?? ''} ${config?.color ?? ''}`.trim();
  }
  getStatusDotColor(status: string): string {
    const config = getStatusConfig(this.POLICY_STATUSES, status);
    // Use the configured color for the status dot if present
    return config?.color ?? '';
  }

  openAddMemberDialog() {
    const policy = this.policy();
    if (!policy) return;

    const dialogRef = this.dialog.open(MedicalAddMemberToPolicyDialog, {
      width: '60vw',
      minWidth: '60vw',
      maxHeight: '90vh',
      data: { policy },
      disableClose: true,
      panelClass: ['responsive-dialog', 'bg-white'],
      autoFocus: false,
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (!result) return;

      this.store.addMember(policy.id, result).subscribe({
        next: () => {
          this.feedback.success('Member added successfully with pro-rated premium');
          // Reload the policy
          this.loadPolicy(policy.id);
        },
        error: (err) => {
          this.feedback.error(err?.error?.message ?? 'Failed to add member');
        },
      });
    });
  }

  async activatePolicy(): Promise<void> {
    const policy = this.policy();
    if (!policy) return;

    if (
      await this.feedback.confirm('Activate Policy', `Activate policy ${policy.policy_number}?`)
    ) {
      this.store.activate(policy.id).subscribe({
        next: () => {
          this.feedback.success('Policy activated');
          this.loadPolicy(policy.id);
        },
        error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to activate policy'),
      });
    }
  }

  async suspendPolicy(): Promise<void> {
    const policy = this.policy();
    if (!policy) return;

    const reason = 'Administrative suspension';
    if (await this.feedback.confirm('Suspend Policy', `Suspend policy ${policy.policy_number}?`)) {
      this.store.suspend(policy.id, reason).subscribe({
        next: () => {
          this.feedback.success('Policy suspended');
          this.loadPolicy(policy.id);
        },
        error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to suspend policy'),
      });
    }
  }

  async reinstatePolicy(): Promise<void> {
    const policy = this.policy();
    if (!policy) return;

    if (
      await this.feedback.confirm('Reinstate Policy', `Reinstate policy ${policy.policy_number}?`)
    ) {
      this.store.reinstate(policy.id).subscribe({
        next: () => {
          this.feedback.success('Policy reinstated');
          this.loadPolicy(policy.id);
        },
        error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to reinstate policy'),
      });
    }
  }

  async cancelPolicy(): Promise<void> {
    const policy = this.policy();
    if (!policy) return;

    const reason = 'Policy cancellation';
    if (
      await this.feedback.confirm(
        'Cancel Policy',
        `Are you sure you want to cancel policy ${policy.policy_number}? This action cannot be undone.`
      )
    ) {
      this.store.cancel(policy.id, reason).subscribe({
        next: () => {
          this.feedback.success('Policy cancelled');
          this.loadPolicy(policy.id);
        },
        error: (err) => this.feedback.error(err?.error?.message ?? 'Failed to cancel policy'),
      });
    }
  }

  goBack() {
    this.router.navigate(['/policies']);
  }

  getMemberTypeBadgeClass(type: string): string {
    const typeMap: Record<string, string> = {
      principal: 'bg-blue-100 text-blue-700',
      spouse: 'bg-purple-100 text-purple-700',
      child: 'bg-green-100 text-green-700',
      dependent: 'bg-amber-100 text-amber-700',
    };
    return typeMap[type] || 'bg-slate-100 text-slate-700';
  }

  calculateAge(dob: string): number {
    const today = new Date();
    const birthDate = new Date(dob);
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
      age--;
    }
    return age;
  }
}
