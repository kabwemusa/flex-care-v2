import { Component, signal, OnInit } from '@angular/core';
import { RouterModule } from '@angular/router';
import { DecimalPipe, DatePipe, TitleCasePipe } from '@angular/common';
import { MemberPortalService, DashboardData, Alert } from '../../../services/member-portal.service';

@Component({
  selector: 'app-portal-dashboard',
  standalone: true,
  imports: [RouterModule, DecimalPipe, DatePipe, TitleCasePipe],
  templateUrl: './dashboard.html',
})
export class PortalDashboard implements OnInit {
  data = signal<DashboardData | null>(null);
  loading = signal(true);
  error = signal('');

  constructor(private portalService: MemberPortalService) {}

  ngOnInit() {
    this.loadDashboard();
  }

  loadDashboard() {
    this.loading.set(true);
    this.error.set('');

    this.portalService.getDashboard().subscribe({
      next: (res) => {
        this.data.set(res.data);
        this.loading.set(false);
      },
      error: (err) => {
        this.error.set(err.error?.message ?? 'Failed to load dashboard');
        this.loading.set(false);
      },
    });
  }

  getAlertIcon(type: Alert['type']): string {
    switch (type) {
      case 'warning':
        return 'warning';
      case 'error':
        return 'error';
      case 'success':
        return 'check_circle';
      default:
        return 'info';
    }
  }

  getAlertClasses(type: Alert['type']): string {
    switch (type) {
      case 'warning':
        return 'bg-amber-50 border-amber-200 text-amber-800';
      case 'error':
        return 'bg-red-50 border-red-200 text-red-800';
      case 'success':
        return 'bg-emerald-50 border-emerald-200 text-emerald-800';
      default:
        return 'bg-blue-50 border-blue-200 text-blue-800';
    }
  }

  getStatusClasses(status: string): string {
    switch (status?.toLowerCase()) {
      case 'active':
      case 'approved':
      case 'paid':
        return 'bg-emerald-100 text-emerald-700';
      case 'pending':
      case 'submitted':
      case 'in_review':
        return 'bg-amber-100 text-amber-700';
      case 'rejected':
      case 'declined':
      case 'cancelled':
        return 'bg-red-100 text-red-700';
      default:
        return 'bg-gray-100 text-gray-700';
    }
  }

  getUsageColor(percentage: number): string {
    if (percentage >= 90) return 'bg-linear-to-r from-red-500 to-rose-500';
    if (percentage >= 70) return 'bg-linear-to-r from-amber-500 to-orange-500';
    return 'bg-linear-to-r from-teal-500 to-cyan-500';
  }

  getMemberIcon(type: string): string {
    switch (type?.toLowerCase()) {
      case 'spouse':
        return 'favorite';
      case 'child':
        return 'child_care';
      case 'parent':
        return 'elderly';
      default:
        return 'person';
    }
  }

  getMemberColor(type: string): string {
    switch (type?.toLowerCase()) {
      case 'spouse':
        return 'violet';
      case 'child':
        return 'amber';
      case 'parent':
        return 'emerald';
      default:
        return 'teal';
    }
  }
}
