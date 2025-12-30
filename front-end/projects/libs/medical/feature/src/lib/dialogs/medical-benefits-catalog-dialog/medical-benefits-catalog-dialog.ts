// libs/medical/ui/src/lib/dialogs/benefit-dialog/benefit-dialog.component.ts

import { Component, inject, OnInit, signal, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogRef, MatDialogModule } from '@angular/material/dialog';
import { MatStepper, MatStepperModule } from '@angular/material/stepper';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatSlideToggleModule } from '@angular/material/slide-toggle';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { MatChipsModule } from '@angular/material/chips';
import { STEPPER_GLOBAL_OPTIONS } from '@angular/cdk/stepper';

import {
  Benefit,
  BenefitStore,
  BENEFIT_TYPES,
  LIMIT_TYPES,
  LIMIT_FREQUENCIES,
  LIMIT_BASES,
  MEMBER_TYPES,
  getLabelByValue,
} from 'medical-data';

@Component({
  selector: 'lib-benefit-dialog',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    MatDialogModule,
    MatStepperModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatSlideToggleModule,
    MatButtonModule,
    MatIconModule,
    MatCheckboxModule,
    MatChipsModule,
  ],
  providers: [
    {
      provide: STEPPER_GLOBAL_OPTIONS,
      useValue: { showError: true },
    },
  ],
  templateUrl: './medical-benefits-catalog-dialog.html',
})
export class MedicalBenefitsCatalogDialog implements OnInit {
  private readonly fb = inject(FormBuilder);
  readonly dialogRef = inject(MatDialogRef<MedicalBenefitsCatalogDialog>);
  readonly data = inject<Partial<Benefit> | null>(MAT_DIALOG_DATA);
  readonly store = inject(BenefitStore);

  @ViewChild('stepper') stepper!: MatStepper;

  // Constants
  readonly benefitTypes = BENEFIT_TYPES;
  readonly limitTypes = LIMIT_TYPES;
  readonly limitFrequencies = LIMIT_FREQUENCIES;
  readonly limitBases = LIMIT_BASES;
  readonly memberTypes = MEMBER_TYPES;

  isEditMode = false;
  currentStep = signal(0);

  // Form Groups
  basicInfoForm!: FormGroup;
  limitsForm!: FormGroup;
  requirementsForm!: FormGroup;

  ngOnInit() {
    this.isEditMode = !!this.data?.id;
    this.store.loadCategories();
    this.initForms();
  }

  private initForms() {
    // Step 1: Basic Information
    this.basicInfoForm = this.fb.group({
      category_id: [this.data?.category_id || '', Validators.required],
      parent_id: [this.data?.parent_id || null],
      name: [this.data?.name || '', [Validators.required, Validators.minLength(2)]],
      display_name: [this.data?.display_name || ''],
      benefit_type: [this.data?.benefit_type || '', Validators.required],
      description: [this.data?.description || ''],
      is_active: [this.data?.is_active ?? true],
    });

    // Step 2: Default Limits
    this.limitsForm = this.fb.group({
      default_limit_type: [this.data?.default_limit_type || 'unlimited'],
      default_limit_frequency: [this.data?.default_limit_frequency || 'per_annum'],
      default_limit_basis: [this.data?.default_limit_basis || 'per_member'],
    });

    // Step 3: Requirements
    this.requirementsForm = this.fb.group({
      requires_preauth: [this.data?.requires_preauth ?? false],
      requires_referral: [this.data?.requires_referral ?? false],
      applicable_member_types: [
        this.data?.applicable_member_types || ['principal', 'spouse', 'child', 'parent'],
      ],
    });
  }

  onStepChange(index: number) {
    this.currentStep.set(index);
  }

  // Navigation
  nextStep() {
    if (this.stepper) {
      this.stepper.next();
    }
  }

  prevStep() {
    if (this.stepper) {
      this.stepper.previous();
    }
  }

  get canGoNext(): boolean {
    const step = this.currentStep();
    if (step === 0) return this.basicInfoForm.valid;
    if (step === 1) return this.limitsForm.valid;
    return true;
  }

  get isLastStep(): boolean {
    return this.currentStep() === 2;
  }

  get isFirstStep(): boolean {
    return this.currentStep() === 0;
  }

  // Helper methods for display
  getBenefitTypeIcon(value: string): string {
    return BENEFIT_TYPES.find((t) => t.value === value)?.icon || 'medical_services';
  }

  getBenefitTypeLabel(value: string): string {
    return getLabelByValue(BENEFIT_TYPES, value);
  }

  getLimitTypeLabel(value: string): string {
    return getLabelByValue(LIMIT_TYPES, value);
  }

  getLimitFrequencyLabel(value: string): string {
    return getLabelByValue(LIMIT_FREQUENCIES, value);
  }

  getLimitBasisLabel(value: string): string {
    return getLabelByValue(LIMIT_BASES, value);
  }

  getCategoryName(categoryId: string): string {
    return this.store.categories().find((c) => c.id === categoryId)?.name || '';
  }

  getCategoryIcon(categoryId: string): string {
    return this.store.categories().find((c) => c.id === categoryId)?.icon || 'folder';
  }

  getCategoryColor(categoryId: string): string {
    return this.store.categories().find((c) => c.id === categoryId)?.color || '#6b7280';
  }

  get parentBenefits(): Benefit[] {
    const categoryId = this.basicInfoForm.get('category_id')?.value;
    if (!categoryId) return [];
    return this.store
      .benefits()
      .filter((b) => b.category_id === categoryId && !b.parent_id && b.id !== this.data?.id);
  }

  getParentBenefitName(parentId: string): string {
    return this.store.benefits().find((b) => b.id === parentId)?.name || '';
  }

  getMemberTypeLabel(value: string): string {
    return getLabelByValue(MEMBER_TYPES, value);
  }

  get isFormValid(): boolean {
    return this.basicInfoForm.valid && this.limitsForm.valid && this.requirementsForm.valid;
  }

  save() {
    if (!this.isFormValid) return;

    const result: Partial<Benefit> = {
      ...this.basicInfoForm.value,
      ...this.limitsForm.value,
      ...this.requirementsForm.value,
    };

    // Clean up null parent_id
    if (!result.parent_id) {
      delete result.parent_id;
    }

    this.dialogRef.close(result);
  }
}
