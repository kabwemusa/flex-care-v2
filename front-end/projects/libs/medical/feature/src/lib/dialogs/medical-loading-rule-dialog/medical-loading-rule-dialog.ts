// libs/medical/ui/src/lib/dialogs/loading-rule-dialog/loading-rule-dialog.ts

import { Component, inject, OnInit, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, FormArray, ReactiveFormsModule, Validators } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogRef, MatDialogModule } from '@angular/material/dialog';
import { MatStepper, MatStepperModule } from '@angular/material/stepper';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { MatSlideToggleModule } from '@angular/material/slide-toggle';
import { MatChipsModule } from '@angular/material/chips';

import { LoadingRule, LOADING_TYPES, DURATION_TYPES, CONDITION_CATEGORIES } from 'medical-data';

@Component({
  selector: 'lib-loading-rule-dialog',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    MatDialogModule,
    MatStepperModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatButtonModule,
    MatIconModule,
    MatCheckboxModule,
    MatSlideToggleModule,
    MatChipsModule,
  ],
  templateUrl: `./medical-loading-rule-dialog.html`,
})
export class LoadingRuleDialog implements OnInit {
  private readonly fb = inject(FormBuilder);
  readonly dialogRef = inject(MatDialogRef<LoadingRuleDialog>);
  readonly data = inject<LoadingRule | null>(MAT_DIALOG_DATA);

  @ViewChild('stepper') stepper!: MatStepper;

  isEditMode = false;

  // Forms
  conditionForm!: FormGroup;
  loadingForm!: FormGroup;
  optionsForm!: FormGroup;

  // Constants
  readonly categories = CONDITION_CATEGORIES;
  readonly loadingTypes = LOADING_TYPES;
  readonly durationTypes = DURATION_TYPES;

  ngOnInit() {
    this.isEditMode = !!this.data?.id;
    this.initForms();
  }

  private initForms() {
    const d = this.data;

    this.conditionForm = this.fb.group({
      condition_name: [d?.condition_name || '', Validators.required],
      condition_category: [d?.condition_category || 'chronic', Validators.required],
      icd10_code: [d?.icd10_code || ''],
      related_icd_codes_text: [d?.related_icd_codes?.join(', ') || ''],
    });

    this.loadingForm = this.fb.group({
      loading_type: [d?.loading_type || 'percentage', Validators.required],
      loading_value: [d?.loading_value ?? 0],
      min_loading: [d?.min_loading || null],
      max_loading: [d?.max_loading || null],
      duration_type: [d?.duration_type || 'permanent', Validators.required],
      duration_months: [d?.duration_months || null],
    });

    this.optionsForm = this.fb.group({
      exclusion_available: [d?.exclusion_available ?? false],
      exclusion_terms: [d?.exclusion_terms || ''],
      underwriting_notes: [d?.underwriting_notes || ''],
      required_documents_text: [d?.required_documents?.join('\n') || ''],
      is_active: [d?.is_active ?? true],
    });
  }

  // Stepper navigation
  isFirstStep(): boolean {
    return this.stepper?.selectedIndex === 0;
  }

  isLastStep(): boolean {
    return this.stepper?.selectedIndex === 2;
  }

  canGoNext(): boolean {
    const idx = this.stepper?.selectedIndex ?? 0;
    if (idx === 0) return this.conditionForm.valid;
    if (idx === 1) return this.loadingForm.valid;
    return true;
  }

  canSave(): boolean {
    return this.conditionForm.valid && this.loadingForm.valid && this.optionsForm.valid;
  }

  nextStep() {
    if (this.canGoNext()) {
      this.stepper.next();
    }
  }

  prevStep() {
    this.stepper.previous();
  }

  save() {
    if (!this.canSave()) return;

    const condition = this.conditionForm.value;
    const loading = this.loadingForm.value;
    const options = this.optionsForm.value;

    // Parse related ICD codes
    const relatedIcdCodes = condition.related_icd_codes_text
      ? condition.related_icd_codes_text
          .split(',')
          .map((c: string) => c.trim().toUpperCase())
          .filter(Boolean)
      : null;

    // Parse required documents
    const requiredDocs = options.required_documents_text
      ? options.required_documents_text
          .split('\n')
          .map((d: string) => d.trim())
          .filter(Boolean)
      : null;

    const result: Partial<LoadingRule> = {
      condition_name: condition.condition_name,
      condition_category: condition.condition_category,
      icd10_code: condition.icd10_code?.toUpperCase() || null,
      related_icd_codes: relatedIcdCodes,
      loading_type: loading.loading_type,
      loading_value: loading.loading_type !== 'exclusion' ? loading.loading_value : null,
      min_loading: loading.min_loading || null,
      max_loading: loading.max_loading || null,
      duration_type: loading.duration_type,
      duration_months: loading.duration_type === 'time_limited' ? loading.duration_months : null,
      exclusion_available: options.exclusion_available,
      exclusion_terms: options.exclusion_available ? options.exclusion_terms : null,
      underwriting_notes: options.underwriting_notes || null,
      required_documents: requiredDocs,
      is_active: options.is_active,
    };

    this.dialogRef.close(result);
  }
}
