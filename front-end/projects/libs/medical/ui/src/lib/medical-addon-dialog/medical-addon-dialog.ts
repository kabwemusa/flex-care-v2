import { Component, inject } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatDialogRef, MAT_DIALOG_DATA, MatDialogModule } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { MatSlideToggleModule } from '@angular/material/slide-toggle';

@Component({
  selector: 'lib-medical-addon-dialog',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    MatDialogModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    MatSlideToggleModule,
  ],
  templateUrl: './medical-addon-dialog.html',
})
export class MedicalAddonDialog {
  private fb = inject(FormBuilder);
  public ref = inject(MatDialogRef<MedicalAddonDialog>);
  public data = inject(MAT_DIALOG_DATA);

  addonForm = this.fb.group({
    name: [this.data?.name || '', [Validators.required, Validators.minLength(3)]],
    price: [this.data?.price || 0, [Validators.required, Validators.min(0)]],
    description: [this.data?.description || ''],
    is_mandatory: [this.data ? this.data.is_mandatory : true],
  });

  save() {
    if (this.addonForm.valid) this.ref.close(this.addonForm.value);
  }
}
