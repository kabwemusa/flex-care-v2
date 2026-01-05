// libs/medical/feature/src/lib/dialogs/medical-application-referral-dialog/medical-application-referral-dialog.ts

import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';

import { MatDialogModule, MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatIconModule } from '@angular/material/icon';

export interface ReferralDialogData {
  applicationNumber?: string;
}

@Component({
  selector: 'lib-medical-application-referral-dialog',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    MatDialogModule,
    MatButtonModule,
    MatFormFieldModule,
    MatInputModule,
    MatIconModule,
  ],
  templateUrl: './medical-application-referral-dialog.html',
})
export class MedicalApplicationReferralDialog {
  private readonly fb = inject(FormBuilder);
  readonly dialogRef = inject(MatDialogRef<MedicalApplicationReferralDialog>);
  readonly data = inject<ReferralDialogData>(MAT_DIALOG_DATA, { optional: true });

  readonly form = this.fb.group({
    reason: ['', [Validators.required, Validators.minLength(10), Validators.maxLength(1000)]],
    underwriter_id: [''],
  });

  cancel() {
    this.dialogRef.close();
  }

  submit() {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.dialogRef.close(this.form.value);
  }
}
