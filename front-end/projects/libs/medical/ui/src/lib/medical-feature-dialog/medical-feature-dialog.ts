// @medical/ui/medical-feature-dialog.component.ts
import { Component, inject } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogRef, MatDialogModule } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';

@Component({
  selector: 'lib-medical-feature-dialog',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    MatDialogModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatButtonModule,
    MatIconModule,
  ],
  templateUrl: './medical-feature-dialog.html',
})
export class MedicalFeatureDialog {
  private fb = inject(FormBuilder);
  public ref = inject(MatDialogRef<MedicalFeatureDialog>);
  public data = inject(MAT_DIALOG_DATA);

  featureForm = this.fb.group({
    name: [this.data?.name || '', [Validators.required, Validators.minLength(2)]],
    category: [this.data?.category || 'Clinical', Validators.required],
  });

  save() {
    if (this.featureForm.valid) this.ref.close(this.featureForm.value);
  }
}
