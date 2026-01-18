// libs/medical/data/src/lib/services/member-util.service.ts

import { Injectable } from '@angular/core';
import { Member, MemberMetadata } from '../models/medical-interfaces';

/**
 * Utility service for member-related calculations and helpers
 */
@Injectable({
  providedIn: 'root',
})
export class MemberUtilService {
  /**
   * Calculate age from date of birth
   */
  calculateAge(dob: string | Date, referenceDate: Date = new Date()): number {
    const birthDate = typeof dob === 'string' ? new Date(dob) : dob;
    const reference = new Date(referenceDate);

    let age = reference.getFullYear() - birthDate.getFullYear();
    const monthDiff = reference.getMonth() - birthDate.getMonth();

    if (monthDiff < 0 || (monthDiff === 0 && reference.getDate() < birthDate.getDate())) {
      age--;
    }

    return age;
  }

  /**
   * Check if member's premium was prorated
   */
  isProratedPremium(member: Member): boolean {
    if (!member.metadata) return false;

    // Check if metadata contains prorated premium info
    const metadata =
      typeof member.metadata === 'string' ? JSON.parse(member.metadata) : member.metadata;

    return !!(metadata?.prorated_premium || metadata?.pro_rata_method);
  }

  /**
   * Get prorated premium metadata
   */
  getProratedMetadata(member: Member): MemberMetadata | null {
    if (!member.metadata) return null;

    const metadata =
      typeof member.metadata === 'string' ? JSON.parse(member.metadata) : member.metadata;

    if (!this.isProratedPremium(member)) return null;

    return {
      annual_premium: metadata.annual_premium,
      prorated_premium: metadata.prorated_premium,
      effective_date: metadata.effective_date,
      days_remaining: metadata.days_remaining,
      pro_rata_method: metadata.pro_rata_method,
      calculated_at: metadata.calculated_at,
    };
  }

  /**
   * Get full name of member
   */
  getFullName(member: Member): string {
    const parts = [member.title, member.first_name, member.middle_name, member.last_name].filter(
      Boolean
    );

    return parts.join(' ');
  }

  /**
   * Get initials from member name
   */
  getInitials(member: Member): string {
    const first = member.first_name?.charAt(0).toUpperCase() || '';
    const last = member.last_name?.charAt(0).toUpperCase() || '';
    return `${first}${last}`;
  }

  /**
   * Format member type for display
   */
  formatMemberType(type: string): string {
    const typeMap: Record<string, string> = {
      principal: 'Principal',
      spouse: 'Spouse',
      child: 'Child',
      parent: 'Parent',
      dependent: 'Dependent',
    };
    return typeMap[type] || type;
  }

  /**
   * Get member's total premium (base + loadings)
   */
  getTotalPremium(member: Member): number {
    const base = member.premium || 0;
    const loadings = member.loading_amount || 0;
    return base + loadings;
  }

  /**
   * Check if member has active loadings
   */
  hasLoadings(member: Member): boolean {
    // Ensure boolean result by evaluating conditions to booleans explicitly
    const hasLoadingArray = Array.isArray(member.loadings) && member.loadings.length > 0;
    const hasLoadingAmount = (member.loading_amount ?? 0) > 0;
    return hasLoadingArray || hasLoadingAmount;
  }

  /**
   * Check if member has active exclusions
   */
  hasExclusions(member: Member): boolean {
    return (member.exclusions?.length ?? 0) > 0;
  }

  /**
   * Get count of active loadings
   */
  getLoadingsCount(member: Member): number {
    if (!member.loadings) return 0;
    return member.loadings.filter((l) => l.status === 'active').length;
  }

  /**
   * Get count of active exclusions
   */
  getExclusionsCount(member: Member): number {
    if (!member.exclusions) return 0;
    return member.exclusions.filter((e) => e.status === 'active').length;
  }
}
