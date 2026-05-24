import { Component, signal, OnInit, inject, DestroyRef } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { RouterModule } from '@angular/router';
import { DecimalPipe, DatePipe, TitleCasePipe } from '@angular/common';
import { MemberPortalService, PolicyData } from '../../../services/member-portal.service';
import { UiUtilsService } from '../../../services/ui-utils.service';
import { ToastService } from '../../../services/toast.service';

@Component({
  selector: 'app-portal-policy',
  standalone: true,
  imports: [RouterModule, DecimalPipe, DatePipe, TitleCasePipe],
  templateUrl: './policy.html',
})
export class PortalPolicy implements OnInit {
  private readonly portalService = inject(MemberPortalService);
  private readonly toast         = inject(ToastService);
  private readonly destroyRef    = inject(DestroyRef);
  readonly ui                    = inject(UiUtilsService);

  policy  = signal<PolicyData | null>(null);
  loading = signal(true);

  ngOnInit(): void {
    this.loadPolicy();
  }

  loadPolicy(): void {
    this.loading.set(true);

    this.portalService.getPolicy()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (res) => {
          this.policy.set(res.data);
          this.loading.set(false);
        },
        error: (err) => {
          this.loading.set(false);
          this.toast.error(err.error?.message ?? 'Failed to load policy. Please refresh.');
        },
      });
  }
}
