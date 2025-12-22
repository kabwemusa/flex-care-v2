// @medical/shared/components/confirmation-dialog.ts
import { Component, inject } from '@angular/core';
import { MAT_DIALOG_DATA, MatDialogRef, MatDialogModule } from '@angular/material/dialog';
import { MatButtonModule } from '@angular/material/button';

@Component({
  selector: 'lib-confirmation-dialog',
  standalone: true,
  imports: [MatDialogModule, MatButtonModule],
  template: `
    <div class="p-6">
      <h3 class="text-xl font-bold text-slate-900">{{ data.title }}</h3>
      <p class="mt-2 text-slate-600 leading-relaxed">{{ data.message }}</p>

      <div class="mt-8 flex justify-end gap-3">
        <button mat-button (click)="ref.close(false)">Cancel</button>
        <button mat-flat-button color="warn" (click)="ref.close(true)" class="rounded-lg">
          Confirm Action
        </button>
      </div>
    </div>
  `,
})
export class ConfirmationDialog {
  ref = inject(MatDialogRef<ConfirmationDialog>);
  data = inject(MAT_DIALOG_DATA);
}
