// libs/medical/feature/src/lib/dialogs/medical-member-benefits-dialog/medical-member-benefits-dialog.ts

import { Component, Inject, inject, OnInit, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatDialogRef, MAT_DIALOG_DATA, MatDialogModule } from '@angular/material/dialog';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatTooltipModule } from '@angular/material/tooltip';
import {
  Member,
  MemberBenefitUtilization,
  UtilizationCategory,
  MemberStore,
  formatCurrency,
} from 'medical-data';

interface DialogData {
  member?: Member;
  member_id?: string;
  member_name?: string;
  policy_number?: string;
}

@Component({
  selector: 'lib-medical-member-benefits-dialog',
  standalone: true,
  imports: [
    CommonModule,
    MatDialogModule,
    MatButtonModule,
    MatIconModule,
    MatProgressBarModule,
    MatProgressSpinnerModule,
    MatTooltipModule,
  ],
  templateUrl: './medical-member-benefits-dialog.html',
})
export class MedicalMemberBenefitsDialog implements OnInit {
  private readonly dialogRef = inject(MatDialogRef<MedicalMemberBenefitsDialog>);
  private readonly memberStore = inject(MemberStore);

  readonly memberId: string;
  readonly memberName: string;
  readonly memberNumber: string;
  readonly formatCurrency = formatCurrency;

  // State
  readonly selectedBenefit = signal<MemberBenefitUtilization | null>(null);
  readonly activeTab = signal<'overview' | 'history'>('overview');

  // Store selectors
  readonly summary = this.memberStore.benefitSummary;
  readonly isLoading = this.memberStore.benefitsLoading;
  readonly history = this.memberStore.benefitHistory;
  readonly historyLoading = this.memberStore.benefitHistoryLoading;

  // Computed
  readonly categories = computed((): UtilizationCategory[] => {
    const sum = this.summary();
    if (!sum) return [];
    return Object.values(sum.benefits_by_category);
  });

  readonly overallUtilization = computed(() => {
    const sum = this.summary();
    if (!sum || sum.total_benefits === 0) return 0;
    const benefits = sum.benefits || [];
    if (benefits.length === 0) return 0;
    const totalPercentage = benefits.reduce((acc, b) => acc + (b.utilization_percentage || 0), 0);
    return Math.round(totalPercentage / benefits.length);
  });

  constructor(@Inject(MAT_DIALOG_DATA) public data: DialogData) {
    if (data.member) {
      this.memberId = data.member.id;
      this.memberName = `${data.member.first_name} ${data.member.last_name}`;
      this.memberNumber = data.member.member_number || '';
    } else {
      this.memberId = data.member_id || '';
      this.memberName = data.member_name || '';
      this.memberNumber = data.policy_number || '';
    }
  }

  ngOnInit(): void {
    if (this.memberId) {
      this.loadBenefits();
    }
  }

  loadBenefits(): void {
    if (!this.memberId) return;
    this.memberStore.loadBenefitBalances(this.memberId).subscribe();
  }

  viewHistory(benefit: MemberBenefitUtilization): void {
    this.selectedBenefit.set(benefit);
    this.activeTab.set('history');
    this.memberStore.loadBenefitHistory(this.memberId, benefit.benefit_id).subscribe();
  }

  backToOverview(): void {
    this.selectedBenefit.set(null);
    this.activeTab.set('overview');
    this.memberStore.clearBenefits();
    this.loadBenefits();
  }

  getUtilizationBgClass(percentage: number): string {
    if (percentage >= 90) return 'bg-red-400';
    if (percentage >= 70) return 'bg-amber-400';
    return 'bg-emerald-400';
  }

  getUtilizationTextClass(percentage: number): string {
    if (percentage >= 90) return 'text-red-600';
    if (percentage >= 70) return 'text-amber-600';
    return 'text-emerald-600';
  }

  getStatusBadge(benefit: MemberBenefitUtilization): { label: string; class: string } {
    // "Light & Subtle" styling:
    // Very low opacity background, text color handles the contrast. No heavy borders.
    if (benefit.is_exhausted) {
      return { label: 'Depleted', class: 'bg-red-50 text-red-600' };
    }
    if (benefit.utilization_percentage >= 90) {
      return { label: 'Low', class: 'bg-amber-50 text-amber-600' };
    }
    if (benefit.utilization_percentage >= 70) {
      return { label: 'Active', class: 'bg-blue-50 text-blue-600' };
    }
    return { label: 'Good', class: 'bg-emerald-50 text-emerald-600' };
  }

  getLimitTypeIcon(type: string): string {
    switch (type) {
      case 'monetary':
        return 'payments';
      case 'count':
        return 'tag';
      case 'days':
        return 'event_available'; // Cleaner than calendar_today
      case 'unlimited':
        return 'all_inclusive';
      default:
        return 'help_outline';
    }
  }
  close(): void {
    this.memberStore.clearBenefits();
    this.dialogRef.close();
  }
}
