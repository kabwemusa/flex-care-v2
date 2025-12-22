// @medical/ui/medical-scheme-dialog.component.ts
import { Component, Inject, inject } from '@angular/core';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogRef, MatDialogModule } from '@angular/material/dialog';
import { MatSlideToggleModule } from '@angular/material/slide-toggle';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';

@Component({
  selector: 'lib-medical-scheme-dialog',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    MatSlideToggleModule,
    MatFormFieldModule,
    MatInputModule,
    MatDialogModule,
    MatButtonModule,
  ],
  templateUrl: './medical-scheme-dialog.html',
})
export class MedicalSchemeDialog {
  private fb = inject(FormBuilder);
  public dialogRef = inject(MatDialogRef<MedicalSchemeDialog>);
  public data = inject(MAT_DIALOG_DATA);

  schemeForm: FormGroup;
  isEditMode: boolean;

  constructor() {
    this.isEditMode = !!this.data;
    this.schemeForm = this.fb.group({
      name: [this.data?.name || '', [Validators.required, Validators.minLength(3)]],
      is_active: [this.data?.is_active ?? true],
      description: [this.data?.description || ''],
    });
  }

  save() {
    if (this.schemeForm.valid) {
      this.dialogRef.close(this.schemeForm.value);
    }
  }
}
