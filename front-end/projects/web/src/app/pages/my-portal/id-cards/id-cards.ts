import { Component, signal, OnInit } from '@angular/core';
import { RouterModule } from '@angular/router';
import { DatePipe, TitleCasePipe } from '@angular/common';
import { MemberPortalService, IdCard } from '../../../services/member-portal.service';

@Component({
  selector: 'app-portal-id-cards',
  standalone: true,
  imports: [RouterModule, DatePipe, TitleCasePipe],
  templateUrl: './id-cards.html',
})
export class PortalIdCards implements OnInit {
  cards = signal<IdCard[]>([]);
  loading = signal(true);
  error = signal('');
  downloading = signal<string | null>(null);

  constructor(private portalService: MemberPortalService) {}

  ngOnInit() {
    this.loadCards();
  }

  loadCards() {
    this.loading.set(true);
    this.error.set('');

    this.portalService.getIdCards().subscribe({
      next: (res) => {
        this.cards.set(res.data);
        this.loading.set(false);
      },
      error: (err) => {
        this.error.set(err.error?.message ?? 'Failed to load ID cards');
        this.loading.set(false);
      },
    });
  }

  downloadCard(memberId: string) {
    this.downloading.set(memberId);

    this.portalService.downloadIdCard(memberId).subscribe({
      next: (blob) => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `id-card-${memberId}.pdf`;
        a.click();
        window.URL.revokeObjectURL(url);
        this.downloading.set(null);
      },
      error: (err) => {
        this.error.set('Failed to download ID card');
        this.downloading.set(null);
      },
    });
  }
}
