import { Component, signal, OnInit, inject, DestroyRef } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ActivatedRoute, RouterModule } from '@angular/router';
import { DecimalPipe, DatePipe, TitleCasePipe } from '@angular/common';
import { MemberPortalService, ClaimDetail } from '../../../services/member-portal.service';
import { UiUtilsService } from '../../../services/ui-utils.service';
import { ToastService } from '../../../services/toast.service';

@Component({
  selector: 'app-portal-claim-detail',
  standalone: true,
  imports: [RouterModule, DecimalPipe, DatePipe, TitleCasePipe],
  templateUrl: './claim-detail.html',
})
export class PortalClaimDetail implements OnInit {
  private readonly route         = inject(ActivatedRoute);
  private readonly portalService = inject(MemberPortalService);
  private readonly toast         = inject(ToastService);
  private readonly destroyRef    = inject(DestroyRef);
  readonly ui                    = inject(UiUtilsService);

  claim   = signal<ClaimDetail | null>(null);
  loading = signal(true);

  ngOnInit(): void {
    const id = this.route.snapshot.paramMap.get('id');
    if (id) {
      this.loadClaim(id);
    }
  }

  loadClaim(id: string): void {
    this.loading.set(true);

    this.portalService.getClaimDetail(id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (res) => {
          this.claim.set(res.data);
          this.loading.set(false);
        },
        error: (err) => {
          this.loading.set(false);
          this.toast.error(err.error?.message ?? 'Failed to load claim details.');
        },
      });
  }
}
