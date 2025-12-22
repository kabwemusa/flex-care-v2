// @medical/ui/medical-plan-dialog.component.ts
import { Component, inject } from '@angular/core';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogRef, MatDialogModule } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatButtonModule } from '@angular/material/button';
import { PlanStore, SchemeStore } from 'medical-data'; // To list available schemes

@Component({
  selector: 'lib-medical-plan-dialog',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    MatDialogModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatButtonModule,
  ],
  templateUrl: './medical-plan-dialog.html',
})
// @medical/ui/medical-plan-dialog.component.ts
export class MedicalPlanDialog {
  private fb = inject(FormBuilder);
  public ref = inject(MatDialogRef<MedicalPlanDialog>);
  public data = inject(MAT_DIALOG_DATA);
  public planStore = inject(PlanStore);
  // IMPORTANT: Inject SchemeStore to get the list of umbrella schemes
  public schemeStore = inject(SchemeStore);

  planForm: FormGroup;

  constructor() {
    this.planForm = this.fb.group({
      // If creating from a specific scheme, data.scheme_id will be populated
      scheme_id: [this.data?.scheme_id || '', Validators.required],
      name: [this.data?.name || '', [Validators.required, Validators.minLength(3)]],
      type: [this.data?.type || 'Individual', Validators.required],
    });
  }

  save() {
    // rawValue is needed to include the 'code' field if you want to send it,
    // but the backend will ignore it anyway.
    if (this.planForm.valid) {
      this.ref.close(this.planForm.getRawValue());
    }
  }
}
