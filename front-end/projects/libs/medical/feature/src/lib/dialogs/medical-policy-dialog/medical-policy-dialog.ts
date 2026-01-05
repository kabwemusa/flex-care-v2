// libs/medical/feature/src/lib/dialogs/medical-policy-dialog/medical-policy-dialog.ts
import {
  Component,
  Inject,
  inject,
  signal,
  computed,
  OnInit,
  ViewChild,
  effect,
} from '@angular/core';
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
import { provideNativeDateAdapter } from '@angular/material/core';

// Domain Imports
import {
  PolicyStore,
  SchemeListStore,
  PlanListStore,
  GroupStore,
  Policy,
  MedicalPlan,
  CreatePolicyPayload,
  POLICY_TYPES,
  POLICY_TERMS,
  BILLING_FREQUENCIES,
} from 'medical-data';

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
  private readonly fb = inject(FormBuilder);
  readonly dialogRef = inject(MatDialogRef<MedicalPolicyDialog>);

  // Stores
  readonly store = inject(PolicyStore);
  readonly schemeStore = inject(SchemeListStore);
  readonly planStore = inject(PlanListStore);
  readonly groupStore = inject(GroupStore);

  @ViewChild('stepper') stepper!: MatStepper;

  // Constants
  readonly POLICY_TYPES = POLICY_TYPES;
  readonly POLICY_TERMS = POLICY_TERMS;
  readonly BILLING_FREQUENCIES = BILLING_FREQUENCIES;

  // Computed Lookups (Active items only)
  readonly activeSchemes = computed(() =>
    this.schemeStore.schemes().filter((s) => s.is_active === true)
  );

  readonly activePlans = computed(() => this.planStore.plans().filter((p) => p.is_active === true));

  readonly activeGroups = computed(() =>
    this.groupStore.groups().filter((g) => g.status === 'active')
  );

  // State
  isEdit = false;
  currentStep = signal(0);
  filteredPlans = signal<MedicalPlan[]>([]);

  // Forms
  policyInfoForm!: FormGroup;
  coverageForm!: FormGroup;
  billingForm!: FormGroup;

  constructor(@Inject(MAT_DIALOG_DATA) public data: DialogData) {
    this.isEdit = !!data?.policy;

    // Effect to ensure filtered plans update if plans load after scheme selection
    effect(
      () => {
        const currentSchemeId = this.coverageForm?.get('scheme_id')?.value;
        if (currentSchemeId && this.activePlans().length > 0) {
          this.filterPlansByScheme(currentSchemeId);
        }
      },
      { allowSignalWrites: true }
    );
  }

  ngOnInit(): void {
    this.initForms();
    this.loadLookups();

    if (this.isEdit && this.data.policy) {
      this.patchForms(this.data.policy);
    } else if (this.data.groupId) {
      this.policyInfoForm.patchValue({
        group_id: this.data.groupId,
        policy_type: 'corporate',
      });
    }
  }

  private initForms(): void {
    this.policyInfoForm = this.fb.group({
      policy_type: ['individual', Validators.required],
      group_id: [''],
      holder_name: ['', Validators.required],
      holder_email: ['', [Validators.email]],
      holder_phone: [''],
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
      // Reset plan when scheme changes
      this.coverageForm.patchValue({ plan_id: '' }, { emitEvent: false });
    });
  }

  private patchForms(policy: Policy): void {
    this.policyInfoForm.patchValue({
      policy_type: policy.policy_type,
      group_id: policy.group_id,
      holder_name: policy.holder_name,
      holder_email: policy.holder_email,
      holder_phone: policy.holder_phone,
    });

    this.coverageForm.patchValue({
      scheme_id: policy.scheme_id,
      plan_id: policy.plan_id,
      policy_term_months: policy.policy_term_months,
      inception_date: policy.inception_date ? new Date(policy.inception_date) : null,
      // is_auto_renew: policy.is_auto_renew ?? true,
    });

    // Manually trigger filter after patch
    if (policy.scheme_id) {
      this.filterPlansByScheme(policy.scheme_id);
      // Set plan_id again after filter to ensure it sticks
      this.coverageForm.patchValue({ plan_id: policy.plan_id }, { emitEvent: false });
    }

    this.billingForm.patchValue({
      billing_frequency: policy.billing_frequency,
      promo_code: '',
      notes: '',
    });
  }

  private loadLookups(): void {
    this.schemeStore.loadAll();
    this.planStore.loadAll();
    this.groupStore.loadAll();
  }

  private filterPlansByScheme(schemeId: string): void {
    if (!schemeId) {
      this.filteredPlans.set([]);
      return;
    }
    const filtered = this.activePlans().filter((p) => p.scheme_id === schemeId);
    this.filteredPlans.set(filtered);
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

    // Format dates to YYYY-MM-DD
    let formattedDate = '';
    if (coverageValues.inception_date instanceof Date) {
      const d = coverageValues.inception_date;
      const offset = d.getTimezoneOffset() * 60000;
      formattedDate = new Date(d.getTime() - offset).toISOString().split('T')[0];
    } else {
      formattedDate = coverageValues.inception_date;
    }

    const payload: Partial<CreatePolicyPayload> = {
      ...policyInfoValues,
      ...coverageValues,
      ...billingValues,
      inception_date: formattedDate,
    };

    // Clean up empty values
    Object.keys(payload).forEach((key) => {
      const value = (payload as any)[key];
      if (value === '' || value === null || value === undefined) {
        delete (payload as any)[key];
      }
    });

    // const operation = this.isEdit
    //   ? this.store.update(this.data.policy!.id, payload)
    //   : this.store.create(payload);

    // operation.subscribe((res) => {
    //   if (res) {
    //     this.dialogRef.close(true);
    //   }
    // });
  }
}
