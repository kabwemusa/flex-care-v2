import { Component, signal, OnInit } from '@angular/core';
import { RouterModule } from '@angular/router';
import { DecimalPipe, DatePipe, TitleCasePipe } from '@angular/common';
import { MemberPortalService, PolicyData } from '../../../services/member-portal.service';

@Component({
  selector: 'app-portal-policy',
  standalone: true,
  imports: [RouterModule, DecimalPipe, DatePipe, TitleCasePipe],
  templateUrl: './policy.html',
})
export class PortalPolicy implements OnInit {
  policy = signal<PolicyData | null>(null);
  loading = signal(true);
  error = signal('');

  constructor(private portalService: MemberPortalService) {}

  ngOnInit() {
    this.loadPolicy();
  }

  loadPolicy() {
    this.loading.set(true);
    this.error.set('');

    this.portalService.getPolicy().subscribe({
      next: (res) => {
        this.policy.set(res.data);
        this.loading.set(false);
      },
      error: (err) => {
        this.error.set(err.error?.message ?? 'Failed to load policy');
        this.loading.set(false);
      },
    });
  }

  getBenefitIcon(category: string): string {
    const icons: Record<string, string> = {
      inpatient: 'local_hospital',
      outpatient: 'medical_services',
      dental: 'dentistry',
      optical: 'visibility',
      maternity: 'pregnant_woman',
      wellness: 'spa',
    };
    return icons[category?.toLowerCase()] || 'healing';
  }

  getUsageColor(percentage: number): string {
    if (percentage >= 90) return 'bg-linear-to-r from-red-500 to-rose-500';
    if (percentage >= 70) return 'bg-linear-to-r from-amber-500 to-orange-500';
    return 'bg-linear-to-r from-teal-500 to-cyan-500';
  }

  getMemberIcon(relationship: string, isPrimary: boolean): string {
    if (isPrimary) return 'person';
    switch (relationship?.toLowerCase()) {
      case 'spouse': return 'favorite';
      case 'child': return 'child_care';
      case 'parent': return 'elderly';
      default: return 'person';
    }
  }
}
