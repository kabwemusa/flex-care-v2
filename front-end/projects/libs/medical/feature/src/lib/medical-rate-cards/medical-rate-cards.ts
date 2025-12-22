import { Component, OnInit, inject } from '@angular/core';
import { MatDialog } from '@angular/material/dialog';
import { RateCardStore, RateCard } from 'medical-data';
import { MedicalPricingMatrixDialog, MedicalRateCardDialog } from 'medical-ui';
import { FeedbackService } from 'shared';
import { MatTableModule } from '@angular/material/table';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatMenuModule } from '@angular/material/menu';
import { DatePipe } from '@angular/common';
import { firstValueFrom } from 'rxjs';

@Component({
  selector: 'lib-medical-rate-cards',
  standalone: true,
  imports: [MatTableModule, MatButtonModule, MatIconModule, MatMenuModule, DatePipe],
  templateUrl: './medical-rate-cards.html',
})
export class MedicalRateCards implements OnInit {
  public store = inject(RateCardStore);
  private dialog = inject(MatDialog);
  private feedback = inject(FeedbackService);

  displayedColumns = ['status', 'name', 'validity', 'entries', 'actions'];

  ngOnInit() {
    this.store.loadAll();
  }

  async openDialog(card?: RateCard) {
    const ref = this.dialog.open(MedicalRateCardDialog, { width: '500px', data: card });
    const result = await ref.afterClosed().toPromise();
    if (result) {
      this.store.upsert({ ...result, id: card?.id }).subscribe({
        next: () =>
          this.feedback.success(`Rate Card ${result ? 'updated' : 'Created'}  successfully`),
        error: () => this.feedback.error('Operation failed'),
      });
    }
  }

  async openPricingMatrix(card: RateCard) {
    if (!card.id) return;

    // Ask the store for the full details instead of using HttpClient directly
    this.store.getRateCardDetails(card.id).subscribe(async (res) => {
      const ref = this.dialog.open(MedicalPricingMatrixDialog, {
        width: '100vw',
        maxWidth: '900px',
        data: { rateCard: res.data },
      });

      const result = await firstValueFrom(ref.afterClosed());

      if (result) {
        this.store.syncPricing(card.id!, result).subscribe({
          next: () => this.feedback.success('Pricing matrix updated'),
          error: () => this.feedback.error('Operation failed'),
        });
      }
    });
  }

  async deleteCard(id: number) {
    if (
      await this.feedback.confirm(
        'Delete Rate Card?',
        'This will permanently remove all associated price bands.'
      )
    ) {
      this.store.delete(id).subscribe({
        next: () => this.feedback.success('Rate card deleted successfully'),
        error: () => this.feedback.error('Failed to delete rate card'),
      });
    }
  }
}
