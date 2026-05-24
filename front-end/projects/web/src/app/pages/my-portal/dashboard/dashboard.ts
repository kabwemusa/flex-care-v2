import { Component, signal, OnInit, inject, DestroyRef } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { RouterModule } from '@angular/router';
import { DecimalPipe, DatePipe, TitleCasePipe } from '@angular/common';
import { MemberPortalService, DashboardData } from '../../../services/member-portal.service';
import { UiUtilsService } from '../../../services/ui-utils.service';
import { ToastService } from '../../../services/toast.service';

@Component({
  selector: 'app-portal-dashboard',
  standalone: true,
  imports: [RouterModule, DecimalPipe, DatePipe, TitleCasePipe],
  templateUrl: './dashboard.html',
})
export class PortalDashboard implements OnInit {
  private readonly portalService = inject(MemberPortalService);
  private readonly toast         = inject(ToastService);
  private readonly destroyRef    = inject(DestroyRef);
  readonly ui                    = inject(UiUtilsService);

  data    = signal<DashboardData | null>(null);
  loading = signal(true);

  ngOnInit(): void {
    this.loadDashboard();
  }

  loadDashboard(): void {
    this.loading.set(true);

    this.portalService.getDashboard()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (res) => {
          this.data.set(res.data);
          this.loading.set(false);
        },
        error: (err) => {
          this.loading.set(false);
          this.toast.error(err.error?.message ?? 'Failed to load dashboard. Please refresh.');
        },
      });
  }
}
