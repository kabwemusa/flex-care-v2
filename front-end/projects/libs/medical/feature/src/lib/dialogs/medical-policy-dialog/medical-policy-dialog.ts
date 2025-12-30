// libs/medical/feature/src/lib/dialogs/medical-policy-dialog/medical-policy-dialog.ts
// Medical Policy Dialog - Create/Edit with Stepper - Aligned with Plan/Scheme patterns

import { Component, Inject, inject, signal, OnInit, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';

// Material Imports
import { MatDialogRef, MAT_DIALOG_DATA, MatDialogModule } from '@angular/material/dialog';
import { MatStepper, MatStepperModule } from '@angular/material/stepper';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatDividerModule } from '@angular/material/divider';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { MatSlideToggleModule } from '@angular/material/slide-toggle';
import { STEPPER_GLOBAL_OPTIONS } from '@angular/cdk/stepper';

// Domain Imports
import {
  PolicyStore,
  SchemeListStore,
  PlanListStore,
  GroupStore,
  Policy,
  MedicalScheme,
  MedicalPlan,
  CorporateGroup,
  CreatePolicyPayload,
  POLICY_TYPES,
  POLICY_STATUSES,
  POLICY_TERMS,
  BILLING_FREQUENCIES,
} from 'medical-data';
import { provideNativeDateAdapter } from '@angular/material/core';

interface DialogData {
  policy?: Policy;
  groupId?: string;
}

@Component({
  selector: 'lib-medical-policy-dialog',
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
    MatDatepickerModule,
    MatDividerModule,
    MatProgressSpinnerModule,
    MatCheckboxModule,
    MatSlideToggleModule,
  ],
  providers: [
    {
      provide: STEPPER_GLOBAL_OPTIONS,
      useValue: { showError: true },
    },
    provideNativeDateAdapter(),
  ],
  templateUrl: './medical-policy-dialog.html',
})
export class MedicalPolicyDialog implements OnInit {
  readonly store = inject(PolicyStore);
  readonly schemeStore = inject(SchemeListStore);
  readonly planStore = inject(PlanListStore);
  readonly groupStore = inject(GroupStore);
  private readonly fb = inject(FormBuilder);
  readonly dialogRef = inject(MatDialogRef<MedicalPolicyDialog>);

  @ViewChild('stepper') stepper!: MatStepper;

  // Constants
  readonly POLICY_TYPES = POLICY_TYPES;
  readonly POLICY_STATUSES = POLICY_STATUSES;
  readonly POLICY_TERMS = POLICY_TERMS;
  readonly BILLING_FREQUENCIES = BILLING_FREQUENCIES;

  // State
  isEdit = false;
  schemes = signal<MedicalScheme[]>([]);
  plans = signal<MedicalPlan[]>([]);
  filteredPlans = signal<MedicalPlan[]>([]);
  groups = signal<CorporateGroup[]>([]);
  currentStep = signal(0);

  // Forms
  policyInfoForm!: FormGroup;
  coverageForm!: FormGroup;
  billingForm!: FormGroup;

  constructor(@Inject(MAT_DIALOG_DATA) public data: DialogData) {
    this.isEdit = !!data?.policy;
  }

  ngOnInit(): void {
    this.initForms();
    this.loadLookups();

    if (this.isEdit && this.data.policy) {
      this.patchForms(this.data.policy);
    }

    if (this.data.groupId) {
      this.policyInfoForm.patchValue({ group_id: this.data.groupId, policy_type: 'corporate' });
    }
  }

  private initForms(): void {
    this.policyInfoForm = this.fb.group({
      policy_type: ['individual', Validators.required],
      group_id: [''],
      policy_holder_name: ['', Validators.required],
      policy_holder_email: ['', Validators.email],
      policy_holder_phone: [''],
    });

    this.coverageForm = this.fb.group({
      scheme_id: ['', Validators.required],
      plan_id: ['', Validators.required],
      policy_term_months: [12, Validators.required],
      inception_date: [new Date(), Validators.required],
      is_auto_renew: [true],
    });

    this.billingForm = this.fb.group({
      billing_frequency: ['annual', Validators.required],
      promo_code: [''],
      notes: [''],
    });

    // Listen for scheme changes to filter plans
    this.coverageForm.get('scheme_id')?.valueChanges.subscribe((schemeId) => {
      this.filterPlansByScheme(schemeId);
    });
  }

  private patchForms(policy: Policy): void {
    this.policyInfoForm.patchValue({
      policy_type: policy.policy_type,
      group_id: policy.group_id,
      policy_holder_name: policy.policy_holder_name,
      // policy_holder_email: policy.policy_holder_email,
      // policy_holder_phone: policy.policy_holder_phone,
    });

    this.coverageForm.patchValue({
      scheme_id: policy.scheme_id,
      plan_id: policy.plan_id,
      policy_term_months: policy.policy_term_months,
      inception_date: policy.inception_date ? new Date(policy.inception_date) : null,
      is_auto_renew: policy.is_auto_renew,
    });

    this.billingForm.patchValue({
      billing_frequency: policy.billing_frequency,
      promo_code: policy.promo_code,
      notes: policy.notes,
    });
  }

  private loadLookups(): void {
    this.schemeStore.loadAll();
    this.planStore.loadAll();
    this.groupStore.loadAll();

    setTimeout(() => {
      this.schemes.set(this.schemeStore.schemes().filter((s) => s.is_active));
      this.plans.set(this.planStore.plans().filter((p) => p.is_active));
      this.groups.set(this.groupStore.activeGroups());
    }, 500);
  }

  private filterPlansByScheme(schemeId: string): void {
    if (!schemeId) {
      this.filteredPlans.set([]);
      return;
    }

    const filtered = this.plans().filter((p) => p.scheme_id === schemeId);
    this.filteredPlans.set(filtered);

    // Clear plan selection if not in filtered list
    const currentPlanId = this.coverageForm.get('plan_id')?.value;
    if (currentPlanId && !filtered.find((p) => p.id === currentPlanId)) {
      this.coverageForm.patchValue({ plan_id: '' });
    }
  }

  // =========================================================================
  // STEPPER NAVIGATION
  // =========================================================================

  onStepChange(index: number): void {
    this.currentStep.set(index);
  }

  nextStep(): void {
    if (this.stepper) {
      this.stepper.next();
    }
  }

  previousStep(): void {
    if (this.stepper) {
      this.stepper.previous();
    }
  }

  canProceedToNextStep(): boolean {
    const step = this.currentStep();
    if (step === 0) return this.policyInfoForm.valid;
    if (step === 1) return this.coverageForm.valid;
    return true;
  }

  // =========================================================================
  // HELPERS
  // =========================================================================

  isPolicyTypeCorporate(): boolean {
    return this.policyInfoForm.get('policy_type')?.value === 'corporate';
  }

  isFormValid(): boolean {
    return this.policyInfoForm.valid && this.coverageForm.valid && this.billingForm.valid;
  }

  // =========================================================================
  // SAVE
  // =========================================================================

  save(): void {
    if (!this.isFormValid()) return;

    const policyInfoValues = this.policyInfoForm.value;
    const coverageValues = this.coverageForm.value;
    const billingValues = this.billingForm.value;

    // Format dates
    if (coverageValues.inception_date instanceof Date) {
      coverageValues.inception_date = coverageValues.inception_date.toISOString().split('T')[0];
    }

    const payload: CreatePolicyPayload = {
      ...policyInfoValues,
      ...coverageValues,
      ...billingValues,
    };

    // Clean up empty values
    Object.keys(payload).forEach((key) => {
      const value = (payload as any)[key];
      if (value === '' || value === null) {
        delete (payload as any)[key];
      }
    });

    const operation = this.isEdit
      ? this.store.update(this.data.policy!.id, payload)
      : this.store.create(payload);

    operation.subscribe((res) => {
      if (res) {
        this.dialogRef.close(true);
      }
    });
  }
}
