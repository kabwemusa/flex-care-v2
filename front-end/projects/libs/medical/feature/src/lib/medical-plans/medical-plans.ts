// @medical/feature/medical-plans.component.ts
import { Component, OnInit, inject } from '@angular/core';
import { MatDialog } from '@angular/material/dialog';
import { MedicalPlan, PlanStore, SchemeStore } from 'medical-data';
import { MedicalBenefitMatrixDialog, MedicalPlanDialog, MedicalPlanAddonsDialog } from 'medical-ui';
import { FeedbackService, LibSkeleton } from 'shared';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatMenuModule } from '@angular/material/menu';
import { firstValueFrom } from 'rxjs';

@Component({
  selector: 'lib-medical-plans',
  standalone: true,
  imports: [MatIconModule, MatButtonModule, MatMenuModule, LibSkeleton],
  templateUrl: './medical-plans.html',
})
export class MedicalPlans implements OnInit {
  // Injecting stores and services using the new Angular 21 pattern
  public store = inject(PlanStore);
  public schemeStore = inject(SchemeStore);
  private dialog = inject(MatDialog);
  private feedback = inject(FeedbackService);

  ngOnInit() {
    this.store.loadAll();
    // Also load schemes if they aren't loaded, so the dialog dropdown has data
    if (this.schemeStore.schemes().length === 0) {
      this.schemeStore.loadAll();
    }
  }

  async openDialog(plan?: any) {
    const dialogRef = this.dialog.open(MedicalPlanDialog, {
      width: '100%',
      maxWidth: '500px',
      data: plan,
      panelClass: 'responsive-dialog',
    });

    const result = await dialogRef.afterClosed().toPromise();

    if (result) {
      this.store.upsertPlan({ ...result, id: plan?.id }).subscribe({
        next: () => this.feedback.success(`Plan ${plan ? 'updated' : 'created'} successfully`),
        error: (err) => this.feedback.error('Operation failed'),
      });
    }
  }

  async openMatMatrixDialog(plan: any) {
    // 1. Ensure the feature library is loaded so the dialog isn't empty
    if (this.schemeStore.schemes().length === 0) {
      this.schemeStore.loadAll(); // Load umbrella schemes if needed
    }

    const dialogRef = this.dialog.open(MedicalBenefitMatrixDialog, {
      width: '100vw',
      maxWidth: '900px', // Increased width for the limits table
      height: '90vh', // High-density view
      data: { plan }, // Pass as object for clarity
      panelClass: 'matrix-dialog-panel',
    });

    const result = await firstValueFrom(dialogRef.afterClosed());

    if (result) {
      // 2. Call the store with the plan ID and the mapped feature limits
      this.store.syncFeatures(plan.id, result).subscribe({
        next: () => this.feedback.success(`Benefit matrix for ${plan.name} updated`),
        error: (err) => this.feedback.error(err.message || 'Failed to update benefits'),
      });
    }
  }

  async openAddonsDialog(plan: MedicalPlan) {
    const ref = this.dialog.open(MedicalPlanAddonsDialog, {
      width: '400px',
      data: { plan },
    });

    const result = await firstValueFrom(ref.afterClosed());
    if (result) {
      this.store.syncAddons(plan.id, result).subscribe();
    }
  }
  async deletePlan(id: number) {
    const confirmed = await this.feedback.confirm(
      'Delete Plan?',
      'Are you sure you want to remove this tier? This action cannot be undone.'
    );

    if (confirmed) {
      this.store.deletePlan(id).subscribe({
        next: () => this.feedback.success('Scheme deleted successfully'),
        error: () => this.feedback.error('Failed to delete scheme'),
      });
      // Note: You would add a deletePlan method to your PlanStore similar to SchemeStore
    }
  }
}
