import { Component, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { MAT_DIALOG_DATA, MatDialogModule, MatDialogRef } from '@angular/material/dialog';
import { AddonStore } from 'medical-data';

@Component({
  selector: 'lib-plan-addons-dialog',
  standalone: true,
  imports: [MatDialogModule, MatCheckboxModule, MatButtonModule, FormsModule],
  template: `
    <div class="p-6">
      <h2 class="text-xl font-bold mb-4">Link Add-ons to {{ data.plan.name }}</h2>
      <div class="space-y-3 max-h-96 overflow-y-auto">
        @for (addon of addonStore.addons(); track addon.id) {
        <div class="flex items-center p-3 border rounded-xl hover:bg-slate-50">
          <mat-checkbox [(ngModel)]="selection[addon.id!]">
            <div class="ml-2">
              <div class="font-bold text-slate-800">{{ addon.name }}</div>
              <div class="text-xs text-slate-500">Premium: K{{ addon.price }}</div>
            </div>
          </mat-checkbox>
        </div>
        }
      </div>
      <div class="flex justify-end gap-3 mt-6">
        <button mat-button (click)="ref.close()">Cancel</button>
        <button mat-flat-button color="primary" (click)="save()">Save Links</button>
      </div>
    </div>
  `,
})
export class MedicalPlanAddonsDialog {
  public addonStore = inject(AddonStore);
  public ref = inject(MatDialogRef<MedicalPlanAddonsDialog>);
  public data = inject(MAT_DIALOG_DATA);
  selection: Record<number, boolean> = {};

  constructor() {
    this.addonStore.loadAll();
    // Pre-check currently linked addons
    this.data.plan.addons?.forEach((a: any) => (this.selection[a.id] = true));
  }

  save() {
    const selectedIds = Object.keys(this.selection)
      .filter((id) => this.selection[+id])
      .map((id) => +id);
    this.ref.close(selectedIds);
  }
}
