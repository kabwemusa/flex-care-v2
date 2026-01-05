// libs/medical/ui/src/lib/dialogs/plan-addon-config-dialog/plan-addon-config-dialog.ts

import { Component, inject, OnInit } from '@angular/core';
import { CommonModule, DecimalPipe } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogRef, MatDialogModule } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatSlideToggleModule } from '@angular/material/slide-toggle';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';

import { PlanAddon, ADDON_AVAILABILITY, getLabelByValue } from 'medical-data';

interface DialogData {
  planAddon: PlanAddon;
  planId: string;
}

@Component({
  selector: 'lib-plan-addon-config-dialog',
  standalone: true,
  imports: [
    CommonModule,
    DecimalPipe,
    ReactiveFormsModule,
    MatDialogModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatSlideToggleModule,
    MatButtonModule,
    MatIconModule,
  ],
  templateUrl: `./medical-plan-addon-config-dialog.html`,
})
export class PlanAddonConfigDialog implements OnInit {
  private readonly fb = inject(FormBuilder);
  readonly dialogRef = inject(MatDialogRef<PlanAddonConfigDialog>);
  readonly data = inject<DialogData>(MAT_DIALOG_DATA);

  readonly availabilityOptions = ADDON_AVAILABILITY;

  form!: FormGroup;

  ngOnInit() {
    const pa = this.data.planAddon;
    this.form = this.fb.group({
      availability: [pa.availability || 'optional', Validators.required],
      sort_order: [pa.sort_order || 0],
      is_active: [pa.is_active ?? true],
    });
  }

  getAvailabilityLabel(value: string): string {
    return getLabelByValue(ADDON_AVAILABILITY, value);
  }

  getAvailabilityIcon(availability: string): string {
    const icons: Record<string, string> = {
      included: 'check_circle',
      mandatory: 'verified',
      optional: 'add_circle',
      conditional: 'rule',
    };
    return icons[availability] || 'extension';
  }

  getAvailabilityIconClass(availability: string): string {
    const classes: Record<string, string> = {
      included: '!text-green-500',
      mandatory: '!text-blue-500',
      optional: '!text-purple-500',
      conditional: '!text-amber-500',
    };
    return classes[availability] || '!text-slate-500';
  }

  save() {
    if (!this.form.valid) return;
    this.dialogRef.close(this.form.value);
  }
}
