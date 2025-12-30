// libs/medical/ui/src/lib/dialogs/rate-card-dialog/rate-card-dialog.ts

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
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatNativeDateModule, provideNativeDateAdapter } from '@angular/material/core';
import { STEPPER_GLOBAL_OPTIONS } from '@angular/cdk/stepper';

import {
  RateCard,
  PlanListStore,
  PREMIUM_FREQUENCIES,
  PREMIUM_BASES,
  MEMBER_TYPES,
  getLabelByValue,
} from 'medical-data';

@Component({
  selector: 'lib-medical-rate-card-dialog',
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
    MatDatepickerModule,
    MatNativeDateModule,
  ],
  providers: [
    {
      provide: STEPPER_GLOBAL_OPTIONS,
      useValue: { showError: true },
    },
    provideNativeDateAdapter(),
  ],
  templateUrl: './medical-rate-cards-list-dialog.html',
})
export class MedicalRateCardListDialog implements OnInit {
  private readonly fb = inject(FormBuilder);
  readonly dialogRef = inject(MatDialogRef<MedicalRateCardListDialog>);
  readonly data = inject<RateCard | null>(MAT_DIALOG_DATA);
  readonly planStore = inject(PlanListStore);

  @ViewChild('stepper') stepper!: MatStepper;

  // Constants
  readonly premiumFrequencies = PREMIUM_FREQUENCIES;
  readonly premiumBases = PREMIUM_BASES;
  readonly memberTypes = MEMBER_TYPES;

  currentStep = signal(0);
  isEditMode = false;

  // Forms
  basicForm!: FormGroup;
  configForm!: FormGroup;
  factorsForm!: FormGroup;

  ngOnInit() {
    this.isEditMode = !!this.data?.id;
    this.planStore.loadAll();
    this.initForms();
  }

  private initForms() {
    // Step 1: Basic Info
    this.basicForm = this.fb.group({
      plan_id: [this.data?.plan_id || '', Validators.required],
      name: [this.data?.name || '', Validators.required],
      code: [this.data?.code || ''],
      version: [this.data?.version || '1.0', Validators.required],
      currency: [this.data?.currency || 'ZMW', Validators.required],
    });

    // Step 2: Configuration
    this.configForm = this.fb.group({
      premium_frequency: [this.data?.premium_frequency || 'monthly', Validators.required],
      premium_basis: [this.data?.premium_basis || 'per_member', Validators.required],
      effective_from: [
        this.data?.effective_from ? new Date(this.data.effective_from) : new Date(),
        Validators.required,
      ],
      effective_to: [this.data?.effective_to ? new Date(this.data.effective_to) : null],
      is_draft: [this.data?.is_draft ?? true],
      notes: [this.data?.notes || ''],
    });

    // Step 3: Member Type Factors
    const factors = this.data?.member_type_factors || {};
    this.factorsForm = this.fb.group({
      principal: [factors['principal'] || 1.0, [Validators.required, Validators.min(0)]],
      spouse: [factors['spouse'] || 1.0, [Validators.required, Validators.min(0)]],
      child: [factors['child'] || 0.5, [Validators.required, Validators.min(0)]],
      parent: [factors['parent'] || 1.5, [Validators.required, Validators.min(0)]],
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
    if (step === 0) return this.basicForm.valid;
    if (step === 1) return this.configForm.valid;
    return true;
  }

  get isLastStep(): boolean {
    return this.currentStep() === 2;
  }

  get isFirstStep(): boolean {
    return this.currentStep() === 0;
  }

  get isFormValid(): boolean {
    return this.basicForm.valid && this.configForm.valid && this.factorsForm.valid;
  }

  // Display helpers
  getFrequencyLabel(value: string): string {
    return getLabelByValue(PREMIUM_FREQUENCIES, value);
  }

  getBasisLabel(value: string): string {
    return getLabelByValue(PREMIUM_BASES, value);
  }

  getPlanName(planId: string): string {
    const plan = this.planStore.plans().find((p) => p.id === planId);
    return plan?.name || '';
  }

  save() {
    if (!this.isFormValid) return;

    const configValues = this.configForm.value;
    const factorValues = this.factorsForm.value;

    const result: Partial<RateCard> = {
      ...this.basicForm.value,
      premium_frequency: configValues.premium_frequency,
      premium_basis: configValues.premium_basis,
      effective_from: configValues.effective_from?.toISOString().split('T')[0],
      effective_to: configValues.effective_to?.toISOString().split('T')[0] || null,
      is_draft: configValues.is_draft,
      notes: configValues.notes || null,
      member_type_factors: {
        principal: factorValues.principal,
        spouse: factorValues.spouse,
        child: factorValues.child,
        parent: factorValues.parent,
      },
    };

    this.dialogRef.close(result);
  }
}
