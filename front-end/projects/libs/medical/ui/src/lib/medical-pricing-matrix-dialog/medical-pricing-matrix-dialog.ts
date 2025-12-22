import { Component, inject, signal, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { MatDialogRef, MAT_DIALOG_DATA, MatDialogModule } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { RateCardEntry } from 'medical-data';

@Component({
  selector: 'lib-medical-pricing-matrix-dialog',
  standalone: true,
  imports: [
    FormsModule,
    MatDialogModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatButtonModule,
    MatIconModule,
  ],
  templateUrl: './medical-pricing-matrix-dialog.html',
})
export class MedicalPricingMatrixDialog implements OnInit {
  public ref = inject(MatDialogRef<MedicalPricingMatrixDialog>);
  public data = inject(MAT_DIALOG_DATA); // Expects { rateCard: RateCard }

  entries = signal<RateCardEntry[]>([]);

  ngOnInit() {
    // If the rate card already has entries, load them; otherwise start with an empty row
    if (this.data.rateCard.entries?.length > 0) {
      this.entries.set([...this.data.rateCard.entries]);
    } else {
      this.addRow();
    }
  }

  addRow() {
    this.entries.update((items) => [
      ...items,
      { min_age: 0, max_age: 18, member_type: 'Principal', price: 0 },
    ]);
  }

  removeRow(index: number) {
    this.entries.update((items) => items.filter((_, i) => i !== index));
  }

  save() {
    // Return the array of entries to the parent for syncing
    this.ref.close(this.entries());
  }
}
