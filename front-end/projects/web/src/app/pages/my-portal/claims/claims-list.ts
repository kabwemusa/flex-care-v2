import { Component, signal, OnInit } from '@angular/core';
import { RouterModule } from '@angular/router';
import { DecimalPipe, DatePipe, TitleCasePipe } from '@angular/common';
import { MemberPortalService, Claim } from '../../../services/member-portal.service';

@Component({
  selector: 'app-portal-claims-list',
  standalone: true,
  imports: [RouterModule, DecimalPipe, DatePipe, TitleCasePipe],
  templateUrl: './claims-list.html',
})
export class PortalClaimsList implements OnInit {
  claims = signal<Claim[]>([]);
  loading = signal(true);
  error = signal('');
  filter = signal<'all' | 'pending' | 'approved' | 'rejected'>('all');

  constructor(private portalService: MemberPortalService) {}

  ngOnInit() {
    this.loadClaims();
  }

  loadClaims() {
    this.loading.set(true);
    this.error.set('');

    this.portalService.getClaims().subscribe({
      next: (res) => {
        this.claims.set(res.data);
        this.loading.set(false);
      },
      error: (err) => {
        this.error.set(err.error?.message ?? 'Failed to load claims');
        this.loading.set(false);
      },
    });
  }

  setFilter(filter: 'all' | 'pending' | 'approved' | 'rejected') {
    this.filter.set(filter);
  }

  filteredClaims(): Claim[] {
    const f = this.filter();
    if (f === 'all') return this.claims();

    return this.claims().filter((c) => {
      if (f === 'pending') return ['pending', 'submitted', 'in_review'].includes(c.status?.toLowerCase());
      if (f === 'approved') return ['approved', 'paid'].includes(c.status?.toLowerCase());
      if (f === 'rejected') return ['rejected', 'declined'].includes(c.status?.toLowerCase());
      return true;
    });
  }

  getStatusClasses(status: string): string {
    switch (status?.toLowerCase()) {
      case 'approved':
      case 'paid':
        return 'bg-emerald-100 text-emerald-700';
      case 'pending':
      case 'submitted':
      case 'in_review':
        return 'bg-amber-100 text-amber-700';
      case 'rejected':
      case 'declined':
        return 'bg-red-100 text-red-700';
      default:
        return 'bg-gray-100 text-gray-700';
    }
  }

  getStatusBarColor(status: string): string {
    switch (status?.toLowerCase()) {
      case 'approved':
      case 'paid':
        return 'bg-emerald-500';
      case 'pending':
      case 'submitted':
      case 'in_review':
        return 'bg-amber-500';
      case 'rejected':
      case 'declined':
        return 'bg-red-500';
      default:
        return 'bg-gray-300';
    }
  }

  getFilterCount(filterType: string): number {
    const allClaims = this.claims();
    if (filterType === 'all') return allClaims.length;
    if (filterType === 'pending') return allClaims.filter(c => ['pending', 'submitted', 'in_review'].includes(c.status?.toLowerCase())).length;
    if (filterType === 'approved') return allClaims.filter(c => ['approved', 'paid'].includes(c.status?.toLowerCase())).length;
    if (filterType === 'rejected') return allClaims.filter(c => ['rejected', 'declined'].includes(c.status?.toLowerCase())).length;
    return 0;
  }
}
