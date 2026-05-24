import { Component, signal, OnInit, inject, DestroyRef } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { RouterModule } from '@angular/router';
import { DatePipe } from '@angular/common';
import { MemberPortalService, IdCard } from '../../../services/member-portal.service';
import { UiUtilsService } from '../../../services/ui-utils.service';
import { ToastService } from '../../../services/toast.service';

@Component({
  selector: 'app-portal-id-cards',
  standalone: true,
  imports: [RouterModule, DatePipe],
  templateUrl: './id-cards.html',
})
export class PortalIdCards implements OnInit {
  private readonly portalService = inject(MemberPortalService);
  private readonly toast         = inject(ToastService);
  private readonly destroyRef    = inject(DestroyRef);
  readonly ui                    = inject(UiUtilsService);

  cards       = signal<IdCard[]>([]);
  loading     = signal(true);
  downloading = signal<string | null>(null);

  ngOnInit(): void {
    this.loadCards();
  }

  loadCards(): void {
    this.loading.set(true);

    this.portalService.getIdCards()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (res) => {
          this.cards.set(res.data);
          this.loading.set(false);
        },
        error: (err) => {
          this.loading.set(false);
          this.toast.error(err.error?.message ?? 'Failed to load ID cards. Please refresh.');
        },
      });
  }

  downloadCard(memberId: string): void {
    this.downloading.set(memberId);

    this.portalService.downloadIdCard(memberId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (blob) => {
          const url = window.URL.createObjectURL(blob);
          const a   = document.createElement('a');
          a.href     = url;
          a.download = `id-card-${memberId}.pdf`;
          a.click();
          window.URL.revokeObjectURL(url);
          this.downloading.set(null);
          this.toast.success('ID card downloaded successfully.');
        },
        error: () => {
          this.downloading.set(null);
          this.toast.error('Failed to download ID card. Please try again.');
        },
      });
  }
}
