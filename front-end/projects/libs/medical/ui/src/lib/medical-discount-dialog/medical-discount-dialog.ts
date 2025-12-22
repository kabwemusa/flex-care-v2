import { Component, inject, OnInit } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatNativeDateModule, MatOptionModule } from '@angular/material/core';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MAT_DIALOG_DATA, MatDialogRef, MatDialogModule } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { PlanStore } from 'medical-data';
// ... other material imports (MatInput, MatSelect, MatDatepicker)

@Component({
  selector: 'lib-medical-discount-dialog',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    MatDialogModule,
    MatFormFieldModule,
    MatButtonModule,
    MatIconModule,
    // --- ADD THESE ---
    MatInputModule, // Provides accessor for <input matInput>
    MatSelectModule, // Provides accessor for <mat-select>
    MatDatepickerModule, // Provides accessor for <input [matDatepicker]>
    MatNativeDateModule,
  ],
  templateUrl: './medical-discount-dialog.html',
})
export class MedicalDiscountDialog {
  private fb = inject(FormBuilder);
  public planStore = inject(PlanStore);
  public ref = inject(MatDialogRef<MedicalDiscountDialog>);
  public data = inject(MAT_DIALOG_DATA);

  discountForm = this.fb.group({
    name: [this.data?.name || '', Validators.required],
    code: [this.data?.code || '', Validators.required],
    plan_id: [this.data?.plan_id || null],
    type: [this.data?.type || 'percentage', Validators.required],
    value: [this.data?.value || 0, [Validators.required, Validators.min(0)]],
    valid_from: [this.data?.valid_from || new Date(), Validators.required],
    valid_until: [this.data?.valid_until || null],
    // Nested Trigger Rule Engine
    trigger_rule: this.fb.group({
      field: [this.data?.trigger_rule?.field || 'frequency', Validators.required],
      operator: [this.data?.trigger_rule?.operator || '=', Validators.required],
      value: [this.data?.trigger_rule?.value || '', Validators.required],
    }),
  });

  save() {
    if (this.discountForm.valid) this.ref.close(this.discountForm.value);
  }
}
