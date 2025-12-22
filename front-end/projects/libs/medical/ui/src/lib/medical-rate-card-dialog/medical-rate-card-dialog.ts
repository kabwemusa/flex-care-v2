import { Component, inject } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogRef, MatDialogModule } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatSlideToggleModule } from '@angular/material/slide-toggle';
import { MatButtonModule } from '@angular/material/button';
import { provideNativeDateAdapter } from '@angular/material/core';
import { PlanStore } from 'medical-data';

@Component({
  selector: 'lib-medical-rate-card-dialog',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    MatDialogModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatDatepickerModule,
    MatSlideToggleModule,
    MatButtonModule,
  ],
  providers: [provideNativeDateAdapter()],
  templateUrl: './medical-rate-card-dialog.html',
})
export class MedicalRateCardDialog {
  private fb = inject(FormBuilder);
  public ref = inject(MatDialogRef<MedicalRateCardDialog>);
  public data = inject(MAT_DIALOG_DATA);
  public planStore = inject(PlanStore);

  cardForm = this.fb.group({
    plan_id: [this.data?.plan_id || '', Validators.required],
    name: [this.data?.name || '', [Validators.required, Validators.minLength(3)]],
    currency: [this.data?.currency || 'ZMW', [Validators.required, Validators.maxLength(3)]],
    valid_from: [this.data?.valid_from || new Date(), Validators.required],
    valid_until: [this.data?.valid_until || null],
    is_active: [this.data?.is_active || false],
  });

  save() {
    if (this.cardForm.valid) {
      this.ref.close(this.cardForm.value);
    }
  }
}
