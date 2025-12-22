// @medical/feature/medical-features.component.ts
import { Component, OnInit, inject } from '@angular/core';
import { MatDialog } from '@angular/material/dialog';
import { FeatureStore, MedicalFeature } from 'medical-data';
import { MedicalFeatureDialog } from 'medical-ui';
import { FeedbackService, LibSkeleton } from 'shared';
import { MatTableModule } from '@angular/material/table';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatMenuModule } from '@angular/material/menu';

@Component({
  selector: 'lib-medical-features',
  standalone: true,
  imports: [MatTableModule, MatButtonModule, MatIconModule, MatMenuModule, LibSkeleton],
  templateUrl: './medical-features.html',
})
export class MedicalFeatures implements OnInit {
  public store = inject(FeatureStore);
  private dialog = inject(MatDialog);
  private feedback = inject(FeedbackService);

  displayedColumns = ['code', 'name', 'category', 'actions'];

  ngOnInit() {
    this.store.loadAll();
  }

  async openDialog(feature?: MedicalFeature) {
    const ref = this.dialog.open(MedicalFeatureDialog, { width: '450px', data: feature });
    const result = await ref.afterClosed().toPromise();
    if (result) {
      this.store.upsert({ ...result, id: feature?.id }).subscribe({
        next: () =>
          this.feedback.success(`Feature ${feature?.name ? 'updated' : 'created'} successfully`),
        error: (err) => this.feedback.error('Operation failed'),
      });
    }
  }

  async deleteFeature(id: number) {
    if (
      await this.feedback.confirm(
        'Delete Benefit?',
        'This will remove the benefit from the global library.'
      )
    ) {
      this.store.delete(id).subscribe({
        next: () => this.feedback.success('Feature deleted successfully'),
        error: () => this.feedback.error('Failed to delete Feature'),
      });
    }
  }
}
