// @medical/ui/medical-benefit-matrix-dialog.component.ts
import { Component, inject, signal, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { MAT_DIALOG_DATA, MatDialogRef, MatDialogModule } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input'; // REQUIRED
import { MatButtonModule } from '@angular/material/button'; // REQUIRED
import { MatIconModule } from '@angular/material/icon';
import { FeatureStore } from 'medical-data';

@Component({
  selector: 'lib-medical-benefit-matrix-dialog',
  standalone: true,
  // Add MatInputModule and MatButtonModule here
  imports: [
    FormsModule,
    MatDialogModule,
    MatIconModule,
    MatFormFieldModule,
    MatInputModule,
    MatCheckboxModule,
    MatButtonModule,
  ],
  templateUrl: './medical-benefit-matrix-dialog.html',
  styleUrl: './medical-benefit-matrix-dialog.css',
})
export class MedicalBenefitMatrixDialog implements OnInit {
  private featureStore = inject(FeatureStore);
  public ref = inject(MatDialogRef<MedicalBenefitMatrixDialog>);
  public data = inject(MAT_DIALOG_DATA);

  allFeatures = this.featureStore.features;
  selectedIds = signal<number[]>([]);
  // Use a Record type to ensure we don't have undefined issues during binding
  limits: Record<number, { limit_amount: number; limit_description: string }> = {};

  ngOnInit() {
    this.featureStore.loadAll();
    this.initializeExistingData();
  }

  private initializeExistingData() {
    if (this.data.plan?.features) {
      this.data.plan.features.forEach((f: any) => {
        const id = f.id;
        this.selectedIds.update((ids) => [...ids, id]);
        this.limits[id] = {
          limit_amount: f.pivot?.limit_amount || 0,
          limit_description: f.pivot?.limit_description || '',
        };
      });
    }
  }

  toggleFeature(id: number) {
    this.selectedIds.update((ids) => {
      if (ids.includes(id)) {
        return ids.filter((i) => i !== id);
      } else {
        // Ensure the object exists before the template tries to ngModel it
        if (!this.limits[id]) {
          this.limits[id] = { limit_amount: 0, limit_description: '' };
        }
        return [...ids, id];
      }
    });
  }

  isSelected(id: number) {
    return this.selectedIds().includes(id);
  }

  save() {
    // Only send limits for selected IDs
    const payload = this.selectedIds().reduce((acc, id) => {
      acc[id] = this.limits[id];
      return acc;
    }, {} as any);
    this.ref.close(payload);
  }
}
