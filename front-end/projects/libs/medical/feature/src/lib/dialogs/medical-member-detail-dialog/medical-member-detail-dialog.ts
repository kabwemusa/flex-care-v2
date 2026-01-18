// libs/medical/feature/src/lib/dialogs/medical-member-detail-dialog/medical-member-detail-dialog.ts

import { Component, Inject, inject, computed } from '@angular/core';
import { CommonModule } from '@angular/common';

import { MatDialogRef, MAT_DIALOG_DATA, MatDialogModule } from '@angular/material/dialog';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatDividerModule } from '@angular/material/divider';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatChipsModule } from '@angular/material/chips';

import {
  Member,
  MemberUtilService,
  formatCurrency,
  MEMBER_TYPE_STYLES,
  MEMBER_STATUS_STYLES,
  LOADING_TYPE_STYLES,
  EXCLUSION_TYPE_STYLES,
  DURATION_TYPE_STYLES,
  PREMIUM_INDICATORS,
} from 'medical-data';

interface DialogData {
  member: Member;
  policyNumber?: string;
}

@Component({
  selector: 'lib-medical-member-detail-dialog',
  standalone: true,
  imports: [
    CommonModule,
    MatDialogModule,
    MatButtonModule,
    MatIconModule,
    MatDividerModule,
    MatTooltipModule,
    MatChipsModule,
  ],
  templateUrl: './medical-member-detail-dialog.html',
})
export class MedicalMemberDetailDialog {
  readonly dialogRef = inject(MatDialogRef<MedicalMemberDetailDialog>);
  readonly memberUtil = inject(MemberUtilService);

  readonly member: Member;
  readonly policyNumber: string;

  // Constants
  readonly formatCurrency = formatCurrency;
  readonly MEMBER_TYPE_STYLES = MEMBER_TYPE_STYLES;
  readonly MEMBER_STATUS_STYLES = MEMBER_STATUS_STYLES;
  readonly LOADING_TYPE_STYLES = LOADING_TYPE_STYLES;
  readonly EXCLUSION_TYPE_STYLES = EXCLUSION_TYPE_STYLES;
  readonly DURATION_TYPE_STYLES = DURATION_TYPE_STYLES;
  readonly PREMIUM_INDICATORS = PREMIUM_INDICATORS;

  constructor(@Inject(MAT_DIALOG_DATA) public data: DialogData) {
    this.member = data.member;
    this.policyNumber = data.policyNumber || '';
  }

  // Computed helpers using MemberUtilService
  get fullName(): string {
    return this.memberUtil.getFullName(this.member);
  }

  get initials(): string {
    return this.memberUtil.getInitials(this.member);
  }

  get age(): number {
    return this.member.date_of_birth
      ? this.memberUtil.calculateAge(this.member.date_of_birth)
      : 0;
  }

  get memberTypeLabel(): string {
    return this.memberUtil.formatMemberType(this.member.member_type);
  }

  get totalPremium(): number {
    return this.memberUtil.getTotalPremium(this.member);
  }

  get hasLoadings(): boolean {
    return this.memberUtil.hasLoadings(this.member);
  }

  get hasExclusions(): boolean {
    return this.memberUtil.hasExclusions(this.member);
  }

  get loadingsCount(): number {
    return this.memberUtil.getLoadingsCount(this.member);
  }

  get exclusionsCount(): number {
    return this.memberUtil.getExclusionsCount(this.member);
  }

  get isProratedPremium(): boolean {
    return this.memberUtil.isProratedPremium(this.member);
  }

  get proratedMetadata() {
    return this.memberUtil.getProratedMetadata(this.member);
  }

  // Styling helpers
  getMemberTypeStyle(): { bg: string; text: string } {
    const style = MEMBER_TYPE_STYLES[this.member.member_type];
    return style || { bg: 'bg-gray-100', text: 'text-gray-700' };
  }

  getMemberStatusStyle(): { bg: string; text: string; dot: string } {
    const style = MEMBER_STATUS_STYLES[this.member.status];
    return style || { bg: 'bg-gray-100', text: 'text-gray-600', dot: 'bg-gray-400' };
  }

  getLoadingTypeStyle(type: string): { bg: string; text: string; label: string } {
    return LOADING_TYPE_STYLES[type] || { bg: 'bg-gray-100', text: 'text-gray-700', label: type };
  }

  getDurationTypeStyle(type: string): { bg: string; text: string; label: string } {
    return DURATION_TYPE_STYLES[type] || { bg: 'bg-gray-100', text: 'text-gray-700', label: type };
  }

  getExclusionTypeStyle(type: string): { bg: string; text: string; label: string } {
    return EXCLUSION_TYPE_STYLES[type] || { bg: 'bg-gray-100', text: 'text-gray-700', label: type };
  }

  close(): void {
    this.dialogRef.close();
  }
}
