// libs/medical/feature/src/lib/dialogs/medical-member-benefits-dialog/medical-member-benefits-dialog.ts

import { Component, Inject, inject, OnInit, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatDialogRef, MAT_DIALOG_DATA, MatDialogModule } from '@angular/material/dialog';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { Member, MemberBenefitUtilization, UtilizationType, MemberStore } from 'medical-data';

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
    MatProgressSpinnerModule,
  ],
  templateUrl: './medical-member-benefits-dialog.html',
})
export class MedicalMemberBenefitsDialog implements OnInit {
  private readonly dialogRef = inject(MatDialogRef<MedicalMemberBenefitsDialog>);
  private readonly memberStore = inject(MemberStore);

  readonly memberId: string;
  readonly memberName: string;
  readonly memberNumber: string;

  // Store selectors
  readonly summary = this.memberStore.benefitSummary;
  readonly isLoading = this.memberStore.benefitsLoading;

  // Computed
  readonly benefitTypes = computed((): UtilizationType[] => {
    const sum = this.summary();
    if (!sum) return [];
    return Object.values(sum.benefits_by_type);
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

  getUtilizationBgClass(percentage: number): string {
    if (percentage >= 90) return 'bg-red-400';
    if (percentage >= 70) return 'bg-amber-400';
    return 'bg-emerald-400';
  }

  getStatusBadge(benefit: MemberBenefitUtilization): { label: string; class: string } {
    if (benefit.is_exhausted) {
      return { label: 'Exhausted', class: 'bg-red-100 text-red-700' };
    }
    if (benefit.utilization_percentage >= 90) {
      return { label: 'Low', class: 'bg-amber-100 text-amber-700' };
    }
    if (benefit.utilization_percentage >= 70) {
      return { label: 'Moderate', class: 'bg-blue-100 text-blue-700' };
    }
    if (benefit.utilization_percentage > 0) {
      return { label: 'Active', class: 'bg-emerald-100 text-emerald-700' };
    }
    return { label: 'Available', class: 'bg-slate-100 text-slate-600' };
  }

  getLimitTypeIcon(type: string): string {
    switch (type) {
      case 'monetary':
        return 'payments';
      case 'count':
        return 'tag';
      case 'days':
        return 'event_available';
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
