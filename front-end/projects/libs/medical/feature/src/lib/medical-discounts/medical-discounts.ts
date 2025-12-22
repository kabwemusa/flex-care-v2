import { Component, OnInit, inject } from '@angular/core';
import { CommonModule, DatePipe } from '@angular/common';
import { MatDialog, MatDialogModule } from '@angular/material/dialog';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatMenuModule } from '@angular/material/menu';
import { firstValueFrom } from 'rxjs';

// Internal Medical Library Imports
import { DiscountCardStore, MedicalDiscount } from 'medical-data';
import { MedicalDiscountDialog } from 'medical-ui';
import { FeedbackService } from 'shared';

@Component({
  selector: 'lib-medical-discounts',
  standalone: true,
  imports: [CommonModule, MatDialogModule, MatButtonModule, MatIconModule, MatMenuModule, DatePipe],
  templateUrl: './medical-discounts.html',
  styleUrl: './medical-discounts.css',
})
export class MedicalDiscounts implements OnInit {
  // 1. Inject the Store we created earlier
  public store = inject(DiscountCardStore);
  private dialog = inject(MatDialog);
  private feedback = inject(FeedbackService);

  ngOnInit() {
    // 2. Load the data when the component initializes
    this.store.loadAll();
  }

  /**
   * Opens the dialog to Create or Edit a Discount Card
   */
  async openDialog(discount?: MedicalDiscount) {
    const ref = this.dialog.open(MedicalDiscountDialog, {
      width: '550px',
      data: discount || null,
      panelClass: 'medical-dialog-container',
    });

    const result = await firstValueFrom(ref.afterClosed());

    if (result) {
      this.store.upsert({ ...result, id: discount?.id }).subscribe({
        next: () =>
          this.feedback.success(`Discount ${discount ? 'updated' : 'created'} successfully`),
        error: (err) => this.feedback.error('Operation failed'),
      });
    }
  }

  /**
   * Handles the deletion of a discount card
   */
  async delete(id: number) {
    const confirmed = await this.feedback.confirm(
      'Delete Discount?',
      'This rule will no longer be applied to new quotes.'
    );

    if (confirmed) {
      this.store.delete(id).subscribe({
        next: () => this.feedback.success('Discount removed'),
        error: () => this.feedback.error('Delete failed'),
      });
    }
  }
}
