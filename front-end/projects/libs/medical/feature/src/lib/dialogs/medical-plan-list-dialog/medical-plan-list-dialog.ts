// libs/medical/ui/src/lib/dialogs/medical-plan-dialog/medical-plan-dialog.ts

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
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatDividerModule } from '@angular/material/divider';
import { STEPPER_GLOBAL_OPTIONS } from '@angular/cdk/stepper';

import {
  MedicalPlan,
  SchemeListStore,
  PLAN_TYPES,
  NETWORK_TYPES,
  MEMBER_TYPES,
  WAITING_PERIOD_TYPES,
} from 'medical-data';

@Component({
  selector: 'lib-medical-plan-dialog',
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
    MatTooltipModule,
    MatDividerModule,
  ],
  providers: [
    {
      provide: STEPPER_GLOBAL_OPTIONS,
      useValue: { showError: true },
    },
  ],
  templateUrl: './medical-plan-list-dialog.html',
})
export class MedicalPlanListDialog implements OnInit {
  private readonly fb = inject(FormBuilder);
  readonly dialogRef = inject(MatDialogRef<MedicalPlanListDialog>);
  readonly data = inject<MedicalPlan | null>(MAT_DIALOG_DATA);
  readonly schemeStore = inject(SchemeListStore);

  @ViewChild('stepper') stepper!: MatStepper;

  // Constants
  readonly planTypes = PLAN_TYPES;
  readonly networkTypes = NETWORK_TYPES;
  readonly memberTypes = MEMBER_TYPES;
  readonly waitingPeriodTypes = WAITING_PERIOD_TYPES;
  readonly tierLevels = [
    { value: 1, label: 'Platinum (Tier 1)', description: 'Highest coverage' },
    { value: 2, label: 'Gold (Tier 2)', description: 'Premium coverage' },
    { value: 3, label: 'Silver (Tier 3)', description: 'Standard coverage' },
    { value: 4, label: 'Bronze (Tier 4)', description: 'Basic coverage' },
    { value: 5, label: 'Basic (Tier 5)', description: 'Essential coverage' },
  ];

  isEditMode = false;
  currentStep = signal(0);

  // Form Groups for each step
  basicInfoForm!: FormGroup;
  memberConfigForm!: FormGroup;
  waitingPeriodsForm!: FormGroup;
  costSharingForm!: FormGroup;

  ngOnInit() {
    this.isEditMode = !!this.data;
    this.schemeStore.loadAll();
    this.initForms();
  }

  private initForms() {
    // Step 1: Basic Information
    this.basicInfoForm = this.fb.group({
      scheme_id: [this.data?.scheme_id || '', Validators.required],
      name: [this.data?.name || '', [Validators.required, Validators.minLength(3)]],
      plan_type: [this.data?.plan_type || '', Validators.required],
      network_type: [this.data?.network_type || 'open'],
      tier_level: [this.data?.tier_level || 3],
      description: [this.data?.description || ''],
      is_active: [this.data?.is_active ?? false],
      is_visible: [this.data?.is_visible ?? true],
      effective_from: [
        this.data?.effective_from ? new Date(this.data.effective_from) : new Date(),
        Validators.required,
      ],
      effective_to: [this.data?.effective_to ? new Date(this.data.effective_to) : null],
    });

    // Step 2: Member Configuration
    const memberConfig = this.data?.member_config || {};
    this.memberConfigForm = this.fb.group({
      max_dependents: [memberConfig.max_dependents ?? 5, [Validators.min(0), Validators.max(20)]],
      allowed_member_types: [memberConfig.allowed_member_types || ['principal', 'spouse', 'child']],
      child_age_limit: [
        memberConfig.child_age_limit ?? 21,
        [Validators.min(0), Validators.max(30)],
      ],
      child_student_age_limit: [
        memberConfig.child_student_age_limit ?? 25,
        [Validators.min(0), Validators.max(30)],
      ],
      parent_age_limit: [
        memberConfig.parent_age_limit ?? 75,
        [Validators.min(0), Validators.max(100)],
      ],
    });

    // Step 3: Waiting Periods
    const waitingPeriods = this.data?.default_waiting_periods || {};
    this.waitingPeriodsForm = this.fb.group({
      general: [waitingPeriods.general ?? 30, [Validators.min(0)]],
      maternity: [waitingPeriods.maternity ?? 270, [Validators.min(0)]],
      pre_existing: [waitingPeriods.pre_existing ?? 365, [Validators.min(0)]],
      chronic: [waitingPeriods.chronic ?? 180, [Validators.min(0)]],
      dental: [waitingPeriods.dental ?? 90, [Validators.min(0)]],
      optical: [waitingPeriods.optical ?? 90, [Validators.min(0)]],
    });

    // Step 4: Cost Sharing (Optional)
    const costSharing = this.data?.default_cost_sharing || {};
    this.costSharingForm = this.fb.group({
      copay_type: [costSharing.copay_type || 'percentage'],
      copay_amount: [costSharing.copay_amount ?? 0, [Validators.min(0)]],
      copay_percentage: [
        costSharing.copay_percentage ?? 0,
        [Validators.min(0), Validators.max(100)],
      ],
      deductible: [costSharing.deductible ?? 0, [Validators.min(0)]],
      out_of_pocket_max: [costSharing.out_of_pocket_max ?? 0, [Validators.min(0)]],
    });
  }

  onStepChange(index: number) {
    this.currentStep.set(index);
  }

  // Step Navigation
  nextStep() {
    if (this.canProceedToNextStep()) {
      this.stepper.next();
    }
  }

  previousStep() {
    this.stepper.previous();
  }

  canProceedToNextStep(): boolean {
    switch (this.currentStep()) {
      case 0:
        return this.basicInfoForm.valid;
      case 1:
        return this.memberConfigForm.valid;
      case 2:
        return this.waitingPeriodsForm.valid;
      case 3:
        return this.costSharingForm.valid;
      default:
        return false;
    }
  }

  get isFormValid(): boolean {
    return (
      this.basicInfoForm.valid &&
      this.memberConfigForm.valid &&
      this.waitingPeriodsForm.valid &&
      this.costSharingForm.valid
    );
  }

  save() {
    if (!this.isFormValid) return;

    const result: Partial<MedicalPlan> = {
      ...this.basicInfoForm.value,
      member_config: this.memberConfigForm.value,
      default_waiting_periods: this.waitingPeriodsForm.value,
      default_cost_sharing: this.costSharingForm.value,
    };

    this.dialogRef.close(result);
  }
}
