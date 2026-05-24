import { Component, signal, computed, OnInit, inject, DestroyRef } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { RouterModule } from '@angular/router';
import { DecimalPipe, DatePipe, TitleCasePipe } from '@angular/common';
import { MemberPortalService, Claim } from '../../../services/member-portal.service';
import { UiUtilsService } from '../../../services/ui-utils.service';
import { ToastService } from '../../../services/toast.service';

type ClaimFilter = 'all' | 'pending' | 'approved' | 'rejected';

@Component({
  selector: 'app-portal-claims-list',
  standalone: true,
  imports: [RouterModule, DecimalPipe, DatePipe, TitleCasePipe],
  templateUrl: './claims-list.html',
})
export class PortalClaimsList implements OnInit {
  private readonly portalService = inject(MemberPortalService);
  private readonly toast         = inject(ToastService);
  private readonly destroyRef    = inject(DestroyRef);
  readonly ui                    = inject(UiUtilsService);

  claims  = signal<Claim[]>([]);
  loading = signal(true);
  filter  = signal<ClaimFilter>('all');

  /** Derived filtered list — recalculated only when claims or filter changes. */
  filteredClaims = computed(() => {
    const f   = this.filter();
    const all = this.claims();

    switch (f) {
      case 'pending':
        return all.filter((c) =>
          ['pending', 'submitted', 'in_review'].includes(c.status?.toLowerCase())
        );
      case 'approved':
        return all.filter((c) =>
          ['approved', 'paid'].includes(c.status?.toLowerCase())
        );
      case 'rejected':
        return all.filter((c) =>
          ['rejected', 'declined'].includes(c.status?.toLowerCase())
        );
      default:
        return all;
    }
  });

  /** Derived counts for filter tabs. */
  filterCounts = computed(() => {
    const all = this.claims();
    return {
      all:      all.length,
      pending:  all.filter((c) => ['pending', 'submitted', 'in_review'].includes(c.status?.toLowerCase())).length,
      approved: all.filter((c) => ['approved', 'paid'].includes(c.status?.toLowerCase())).length,
      rejected: all.filter((c) => ['rejected', 'declined'].includes(c.status?.toLowerCase())).length,
    };
  });

  ngOnInit(): void {
    this.loadClaims();
  }

  loadClaims(): void {
    this.loading.set(true);

    this.portalService.getClaims()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (res) => {
          this.claims.set(res.data);
          this.loading.set(false);
        },
        error: (err) => {
          this.loading.set(false);
          this.toast.error(err.error?.message ?? 'Failed to load claims. Please refresh.');
        },
      });
  }

  setFilter(f: ClaimFilter): void {
    this.filter.set(f);
  }

}
