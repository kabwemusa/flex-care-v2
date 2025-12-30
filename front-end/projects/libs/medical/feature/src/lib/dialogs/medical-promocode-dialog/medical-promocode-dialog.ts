// libs/medical/ui/src/lib/dialogs/promo-code-dialog/promo-code-dialog.ts

import { Component, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogRef, MatDialogModule } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatNativeDateModule } from '@angular/material/core';
import { MatSlideToggleModule } from '@angular/material/slide-toggle';

import { PromoCode, DiscountListStore } from 'medical-data';

@Component({
  selector: 'lib-promo-code-dialog',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    MatDialogModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatButtonModule,
    MatIconModule,
    MatDatepickerModule,
    MatNativeDateModule,
    MatSlideToggleModule,
  ],
  templateUrl: `./medical-promocode-dialog.html`,
})
export class PromoCodeDialog implements OnInit {
  private readonly fb = inject(FormBuilder);
  readonly dialogRef = inject(MatDialogRef<PromoCodeDialog>);
  readonly data = inject<(PromoCode & { discount_rule_id?: string }) | null>(MAT_DIALOG_DATA);
  private readonly discountStore = inject(DiscountListStore);

  form!: FormGroup;
  isEditMode = false;

  promoCodeRules = this.discountStore.discountRules;

  ngOnInit() {
    this.isEditMode = !!this.data?.id;

    // Load discount rules
    this.discountStore.loadRules({ adjustment_type: 'discount' });

    this.form = this.fb.group({
      discount_rule_id: [this.data?.discount_rule_id || '', Validators.required],
      code: [this.data?.code || '', [Validators.required, Validators.pattern(/^[A-Za-z0-9_]+$/)]],
      name: [this.data?.name || ''],
      valid_from: [
        this.data?.valid_from ? new Date(this.data.valid_from) : new Date(),
        Validators.required,
      ],
      valid_to: [
        this.data?.valid_to ? new Date(this.data.valid_to) : this.getDefaultEndDate(),
        Validators.required,
      ],
      max_uses: [this.data?.max_uses || null],
      // max_uses_per_policy: [this.data?.max_uses_per_policy ?? 1],
      is_active: [this.data?.is_active ?? true],
    });
  }

  private getDefaultEndDate(): Date {
    const date = new Date();
    date.setMonth(date.getMonth() + 3);
    return date;
  }

  save() {
    if (!this.form.valid) return;

    const value = this.form.value;

    const result: Partial<PromoCode> = {
      discount_rule_id: value.discount_rule_id,
      code: value.code.toUpperCase(),
      name: value.name || null,
      valid_from: value.valid_from?.toISOString()?.split('T')[0],
      valid_to: value.valid_to?.toISOString()?.split('T')[0],
      max_uses: value.max_uses || null,
      // max_uses_per_policy: value.max_uses_per_policy,
      is_active: value.is_active,
    };

    this.dialogRef.close(result);
  }
}
