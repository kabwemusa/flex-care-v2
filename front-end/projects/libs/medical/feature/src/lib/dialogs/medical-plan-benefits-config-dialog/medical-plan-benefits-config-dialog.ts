// libs/medical/ui/src/lib/dialogs/plan-benefit-dialog/plan-benefit-dialog.component.ts

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
import { MatTabsModule } from '@angular/material/tabs';
import { STEPPER_GLOBAL_OPTIONS } from '@angular/cdk/stepper';

import {
  PlanBenefit,
  LIMIT_TYPES,
  LIMIT_FREQUENCIES,
  LIMIT_BASES,
  MEMBER_TYPES,
  getLabelByValue,
} from 'medical-data';

@Component({
  selector: 'lib-plan-benefit-dialog',
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
    MatTabsModule,
  ],
  providers: [
    {
      provide: STEPPER_GLOBAL_OPTIONS,
      useValue: { showError: true },
    },
  ],
  templateUrl: './medical-plan-benefits-config-dialog.html',
})
export class MedicalPlanBenefitsConfigDialog implements OnInit {
  private readonly fb = inject(FormBuilder);
  readonly dialogRef = inject(MatDialogRef<MedicalPlanBenefitsConfigDialog>);
  readonly data = inject<{ planBenefit: PlanBenefit; planId: string }>(MAT_DIALOG_DATA);

  @ViewChild('stepper') stepper!: MatStepper;

  // Constants
  readonly limitTypes = LIMIT_TYPES;
  readonly limitFrequencies = LIMIT_FREQUENCIES;
  readonly limitBases = LIMIT_BASES;
  readonly memberTypes = MEMBER_TYPES;

  currentStep = signal(0);

  // Forms
  limitsForm!: FormGroup;
  costSharingForm!: FormGroup;
  settingsForm!: FormGroup;

  get pb(): PlanBenefit {
    return this.data.planBenefit;
  }

  ngOnInit() {
    this.initForms();
  }

  private initForms() {
    // Step 1: Limits
    this.limitsForm = this.fb.group({
      benefit_id: [this.pb.benefit_id, [Validators.required]],
      limit_type: [this.pb.limit_type || this.pb.benefit?.default_limit_type || 'unlimited'],
      limit_frequency: [
        this.pb.limit_frequency || this.pb.benefit?.default_limit_frequency || 'per_annum',
      ],
      limit_basis: [this.pb.limit_basis || this.pb.benefit?.default_limit_basis || 'per_member'],
      limit_amount: [this.pb.limit_amount, [Validators.min(0)]],
      limit_count: [this.pb.limit_count, [Validators.min(0)]],
      limit_days: [this.pb.limit_days, [Validators.min(0)]],
      per_claim_limit: [this.pb.per_claim_limit, [Validators.min(0)]],
      per_day_limit: [this.pb.per_day_limit, [Validators.min(0)]],
      max_claims_per_year: [this.pb.max_claims_per_year, [Validators.min(0)]],
    });

    // Step 2: Cost Sharing
    const costSharing = this.pb.cost_sharing || {};
    this.costSharingForm = this.fb.group({
      copay_type: [costSharing.copay_type || 'percentage'],
      copay_amount: [costSharing.copay_amount, [Validators.min(0)]],
      copay_percentage: [costSharing.copay_percentage, [Validators.min(0), Validators.max(100)]],
      deductible: [costSharing.deductible, [Validators.min(0)]],
      out_of_pocket_max: [costSharing.out_of_pocket_max, [Validators.min(0)]],
    });

    // Step 3: Settings
    this.settingsForm = this.fb.group({
      waiting_period_days: [this.pb.waiting_period_days, [Validators.min(0)]],
      is_covered: [this.pb.is_covered ?? true],
      is_visible: [this.pb.is_visible ?? true],
      notes: [this.pb.notes || ''],
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
    if (step === 0) return this.limitsForm.valid;
    if (step === 1) return this.costSharingForm.valid;
    return true;
  }

  get isLastStep(): boolean {
    return this.currentStep() === 2;
  }

  get isFirstStep(): boolean {
    return this.currentStep() === 0;
  }

  // Display helpers for mat-select-trigger
  getLimitTypeLabel(value: string): string {
    return getLabelByValue(LIMIT_TYPES, value);
  }

  getLimitFrequencyLabel(value: string): string {
    return getLabelByValue(LIMIT_FREQUENCIES, value);
  }

  getLimitBasisLabel(value: string): string {
    return getLabelByValue(LIMIT_BASES, value);
  }

  get showLimitAmount(): boolean {
    const type = this.limitsForm.get('limit_type')?.value;
    return type === 'monetary' || type === 'combined';
  }

  get showLimitCount(): boolean {
    const type = this.limitsForm.get('limit_type')?.value;
    return type === 'count' || type === 'combined';
  }

  get showLimitDays(): boolean {
    const type = this.limitsForm.get('limit_type')?.value;
    return type === 'days' || type === 'combined';
  }

  get isFormValid(): boolean {
    return this.limitsForm.valid && this.costSharingForm.valid && this.settingsForm.valid;
  }

  save() {
    if (!this.isFormValid) return;

    const costSharingValues = this.costSharingForm.value;
    const costSharing = {
      copay_type: costSharingValues.copay_type,
      copay_amount:
        costSharingValues.copay_type === 'fixed' ? costSharingValues.copay_amount : null,
      copay_percentage:
        costSharingValues.copay_type === 'percentage' ? costSharingValues.copay_percentage : null,
      deductible: costSharingValues.deductible || null,
      out_of_pocket_max: costSharingValues.out_of_pocket_max || null,
    };

    const result: Partial<PlanBenefit> = {
      ...this.limitsForm.value,
      cost_sharing: Object.values(costSharing).some((v) => v !== null) ? costSharing : null,
      ...this.settingsForm.value,
    };

    // Clean up null values based on limit type
    // const limitType = result.limit_type;
    // if (limitType === 'unlimited') {
    //   result.limit_amount = null;
    //   result.limit_count = null;
    //   result.limit_days = null;
    // }

    this.dialogRef.close(result);
  }
}
