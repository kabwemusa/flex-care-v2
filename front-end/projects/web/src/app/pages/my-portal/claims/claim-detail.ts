import { Component, signal, OnInit } from '@angular/core';
import { ActivatedRoute, RouterModule } from '@angular/router';
import { DecimalPipe, DatePipe, TitleCasePipe } from '@angular/common';
import { MemberPortalService, ClaimDetail } from '../../../services/member-portal.service';

@Component({
  selector: 'app-portal-claim-detail',
  standalone: true,
  imports: [RouterModule, DecimalPipe, DatePipe, TitleCasePipe],
  templateUrl: './claim-detail.html',
})
export class PortalClaimDetail implements OnInit {
  claim = signal<ClaimDetail | null>(null);
  loading = signal(true);
  error = signal('');

  constructor(
    private route: ActivatedRoute,
    private portalService: MemberPortalService
  ) {}

  ngOnInit() {
    const id = this.route.snapshot.paramMap.get('id');
    if (id) {
      this.loadClaim(id);
    }
  }

  loadClaim(id: string) {
    this.loading.set(true);
    this.error.set('');

    this.portalService.getClaimDetail(id).subscribe({
      next: (res) => {
        this.claim.set(res.data);
        this.loading.set(false);
      },
      error: (err) => {
        this.error.set(err.error?.message ?? 'Failed to load claim');
        this.loading.set(false);
      },
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

  getStatusIcon(status: string): string {
    switch (status?.toLowerCase()) {
      case 'approved':
      case 'paid':
        return 'check_circle';
      case 'pending':
      case 'submitted':
      case 'in_review':
        return 'schedule';
      case 'rejected':
      case 'declined':
        return 'cancel';
      default:
        return 'help';
    }
  }

  getTimelineIcon(event: string): string {
    switch (event?.toLowerCase()) {
      case 'submitted':
        return 'upload';
      case 'received':
        return 'inbox';
      case 'in_review':
        return 'pending';
      case 'approved':
        return 'check_circle';
      case 'rejected':
        return 'cancel';
      case 'paid':
        return 'payments';
      default:
        return 'circle';
    }
  }
}
