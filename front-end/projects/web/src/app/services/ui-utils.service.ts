import { Injectable } from '@angular/core';

/**
 * UiUtilsService
 *
 * Centralises all repeated UI-helper methods that were previously copy-pasted
 * across dashboard, claim-detail, claims-list, policy, and portal-layout.
 * Inject this service instead of duplicating these helpers in every component.
 */
@Injectable({ providedIn: 'root' })
export class UiUtilsService {
  // ── Status classes (badge background + text) ──────────────────────────────

  /**
   * Returns Tailwind badge classes for a given status string.
   * Works for policy status, claim status, card status, etc.
   */
  statusClasses(status: string): string {
    switch (status?.toLowerCase()) {
      case 'active':
      case 'approved':
      case 'paid':
      case 'issued':
        return 'bg-emerald-100 text-emerald-700';

      case 'pending':
      case 'submitted':
      case 'in_review':
      case 'processing':
        return 'bg-amber-100 text-amber-700';

      case 'rejected':
      case 'declined':
      case 'cancelled':
      case 'expired':
        return 'bg-red-100 text-red-700';

      case 'draft':
      case 'inactive':
        return 'bg-gray-100 text-gray-600';

      default:
        return 'bg-gray-100 text-gray-700';
    }
  }

  /**
   * Returns a Material Symbol icon name for a given status.
   */
  statusIcon(status: string): string {
    switch (status?.toLowerCase()) {
      case 'active':
      case 'approved':
      case 'paid':
      case 'issued':
        return 'check_circle';

      case 'pending':
      case 'submitted':
      case 'in_review':
      case 'processing':
        return 'schedule';

      case 'rejected':
      case 'declined':
      case 'cancelled':
        return 'cancel';

      case 'expired':
        return 'event_busy';

      default:
        return 'help';
    }
  }

  /**
   * Solid left-border color class for status indicator bars (thin vertical strips).
   * Used in claim list rows and dashboard recent-claims list.
   */
  statusBarColor(status: string): string {
    switch (status?.toLowerCase()) {
      case 'active':
      case 'approved':
      case 'paid':
      case 'issued':
        return 'bg-emerald-500';

      case 'pending':
      case 'submitted':
      case 'in_review':
      case 'processing':
        return 'bg-amber-500';

      case 'rejected':
      case 'declined':
      case 'cancelled':
        return 'bg-red-500';

      default:
        return 'bg-gray-300';
    }
  }

  // ── Alert / notification styling ─────────────────────────────────────────

  alertClasses(type: 'info' | 'warning' | 'error' | 'success'): string {
    switch (type) {
      case 'warning': return 'bg-amber-50 border-amber-200 text-amber-800';
      case 'error':   return 'bg-red-50 border-red-200 text-red-800';
      case 'success': return 'bg-emerald-50 border-emerald-200 text-emerald-800';
      default:        return 'bg-blue-50 border-blue-200 text-blue-800';
    }
  }

  alertIcon(type: 'info' | 'warning' | 'error' | 'success'): string {
    switch (type) {
      case 'warning': return 'warning';
      case 'error':   return 'error';
      case 'success': return 'check_circle';
      default:        return 'info';
    }
  }

  // ── Member helpers ────────────────────────────────────────────────────────

  /** Material Symbol icon for a member type / relationship. */
  memberIcon(type: string, isPrimary = false): string {
    if (isPrimary || type?.toLowerCase() === 'principal') return 'person';
    switch (type?.toLowerCase()) {
      case 'spouse':  return 'favorite';
      case 'child':   return 'child_care';
      case 'parent':  return 'elderly';
      default:        return 'person';
    }
  }

  /**
   * Returns a Tailwind color name used to build colour-coded badge gradients.
   * Use: `from-${memberColor(type)}-100 to-${memberColor(type)}-200`
   */
  memberColor(type: string): string {
    switch (type?.toLowerCase()) {
      case 'spouse':    return 'violet';
      case 'child':     return 'amber';
      case 'parent':    return 'emerald';
      default:          return 'teal';   // principal
    }
  }

  /** Returns full Tailwind gradient + text classes for a member type card. */
  memberBadgeClasses(type: string): { bg: string; text: string; iconBg: string } {
    switch (type?.toLowerCase()) {
      case 'spouse':
        return { bg: 'from-violet-50 to-purple-50',  text: 'text-violet-700',  iconBg: 'bg-violet-100' };
      case 'child':
        return { bg: 'from-amber-50 to-orange-50',   text: 'text-amber-700',   iconBg: 'bg-amber-100' };
      case 'parent':
        return { bg: 'from-emerald-50 to-green-50',  text: 'text-emerald-700', iconBg: 'bg-emerald-100' };
      default: // principal
        return { bg: 'from-teal-50 to-cyan-50',      text: 'text-teal-700',    iconBg: 'bg-teal-100' };
    }
  }

  // ── Benefit / usage bar ───────────────────────────────────────────────────

  usageBarClasses(percentage: number): string {
    if (percentage >= 90) return 'bg-linear-to-r from-red-500 to-rose-500';
    if (percentage >= 70) return 'bg-linear-to-r from-amber-500 to-orange-500';
    return 'bg-linear-to-r from-teal-500 to-cyan-500';
  }

  // ── Benefit category icons ────────────────────────────────────────────────

  benefitIcon(category: string): string {
    const icons: Record<string, string> = {
      inpatient:   'local_hospital',
      outpatient:  'medical_services',
      dental:      'dentistry',
      optical:     'visibility',
      maternity:   'pregnant_woman',
      wellness:    'spa',
      pharmacy:    'medication',
      emergency:   'emergency',
      specialist:  'stethoscope',
    };
    return icons[category?.toLowerCase()] ?? 'healing';
  }

  // ── Claim timeline icons ──────────────────────────────────────────────────

  timelineIcon(event: string): string {
    const icons: Record<string, string> = {
      submitted: 'upload',
      received:  'inbox',
      in_review: 'pending',
      approved:  'check_circle',
      rejected:  'cancel',
      paid:      'payments',
    };
    return icons[event?.toLowerCase()] ?? 'circle';
  }

  // ── Plan type helpers ─────────────────────────────────────────────────────

  planTypeIcon(type: string): string {
    const icons: Record<string, string> = {
      individual: 'person',
      family:     'family_restroom',
      corporate:  'business',
      sme:        'store',
    };
    return icons[type] ?? 'health_and_safety';
  }

  tierLabel(level: number): string {
    const labels: Record<number, string> = { 1: 'Basic', 2: 'Standard', 3: 'Premium', 4: 'Elite' };
    return labels[level] ?? `Tier ${level}`;
  }

  // ── Billing frequency ─────────────────────────────────────────────────────

  frequencyLabel(f: string): string {
    const labels: Record<string, string> = {
      monthly:     'Monthly',
      quarterly:   'Quarterly',
      semi_annual: 'Semi-Annual',
      annual:      'Annual',
    };
    return labels[f] ?? f;
  }

  // ── Member type label ─────────────────────────────────────────────────────

  memberTypeLabel(type: string): string {
    const labels: Record<string, string> = {
      principal: 'Primary Member',
      spouse:    'Spouse',
      child:     'Child',
      parent:    'Parent',
    };
    return labels[type] ?? type;
  }
}
