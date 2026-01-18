import { Component, OnInit, inject, signal, computed, effect } from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  FormsModule,
  ReactiveFormsModule,
  FormBuilder,
  FormGroup,
  FormArray,
  Validators,
  FormControl,
} from '@angular/forms';
import { toSignal } from '@angular/core/rxjs-interop';
import { startWith, map, debounceTime, tap } from 'rxjs';
import { MatDialogRef, MAT_DIALOG_DATA, MatDialogModule } from '@angular/material/dialog';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { MatDividerModule } from '@angular/material/divider';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatAutocompleteModule } from '@angular/material/autocomplete';
import { MatTooltipModule } from '@angular/material/tooltip';
import { provideNativeDateAdapter } from '@angular/material/core';
import {
  ClaimStore,
  PolicyStore,
  MemberStore,
  BenefitStore,
  CreateClaimPayload,
  PlanBenefit,
  BenefitEligibilityResult,
} from 'medical-data';
import { FeedbackService } from 'shared';

export interface ClaimDialogData {
  policy_id?: string;
  member_id?: string;
}

@Component({
  selector: 'lib-medical-claim-dialog',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    ReactiveFormsModule,
    MatDialogModule,
    MatButtonModule,
    MatIconModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatDatepickerModule,
    MatCheckboxModule,
    MatDividerModule,
    MatProgressSpinnerModule,
    MatAutocompleteModule,
    MatTooltipModule,
  ],
  providers: [provideNativeDateAdapter()],
  templateUrl: './medical-claim-dialog.html',
})
export class MedicalClaimDialog implements OnInit {
  // Injections
  private readonly fb = inject(FormBuilder);
  private readonly dialogRef = inject(MatDialogRef<MedicalClaimDialog>);
  private readonly data = inject<ClaimDialogData | null>(MAT_DIALOG_DATA, { optional: true });
  private readonly claimStore = inject(ClaimStore);
  private readonly policyStore = inject(PolicyStore);
  private readonly memberStore = inject(MemberStore);
  private readonly benefitStore = inject(BenefitStore);
  private readonly feedback = inject(FeedbackService);

  // Forms
  claimForm!: FormGroup;
  linesForm!: FormGroup;

  // Search Controls
  policyFilterControl = new FormControl('');
  memberFilterControl = new FormControl('');
  providerTypeFilterControl = new FormControl('');

  // Signals
  isSubmitting = signal(false);
  currentStep = signal(0);
  planBenefits = signal<PlanBenefit[]>([]);
  lineEligibility = signal<Record<number, BenefitEligibilityResult | null>>({});
  checkingEligibility = signal<Record<number, boolean>>({});
  currentFocusedLineIndex = signal<number | null>(null);

  // Constants
  readonly CLAIM_TYPES = [
    { value: 'out_patient', label: 'Out-Patient', icon: 'medical_services' },
    { value: 'in_patient', label: 'In-Patient', icon: 'local_hospital' },
    { value: 'dental', label: 'Dental', icon: 'dentistry' },
    { value: 'optical', label: 'Optical', icon: 'visibility' },
    { value: 'maternity', label: 'Maternity', icon: 'pregnant_woman' },
    { value: 'chronic', label: 'Chronic', icon: 'medication' },
    { value: 'wellness', label: 'Wellness', icon: 'spa' },
    { value: 'emergency', label: 'Emergency', icon: 'emergency' },
  ];

  readonly PROVIDER_TYPES = [
    { value: 'hospital', label: 'Hospital' },
    { value: 'clinic', label: 'Clinic' },
    { value: 'pharmacy', label: 'Pharmacy' },
    { value: 'lab', label: 'Laboratory' },
    { value: 'optical', label: 'Optical Center' },
    { value: 'dental', label: 'Dental Clinic' },
    { value: 'specialist', label: 'Specialist' },
  ];

  readonly maxDate = new Date();

  // --- Filtering Logic (Using Signals) ---

  private policyTerm = toSignal(this.policyFilterControl.valueChanges.pipe(startWith('')), {
    initialValue: '',
  });
  filteredPolicies = computed(() => {
    const rawTerm = this.policyTerm();
    // Handle case where term is an object (selected value) vs string (typing)
    const filter = (typeof rawTerm === 'string' ? rawTerm : '').toLowerCase();
    const policies = this.policyStore.policies();
    if (!filter || !policies) return policies || [];
    return policies.filter(
      (p) =>
        p.policy_number?.toLowerCase().includes(filter) ||
        p.policy_holder_name?.toLowerCase().includes(filter) ||
        p.plan?.name?.toLowerCase().includes(filter)
    );
  });

  private memberTerm = toSignal(this.memberFilterControl.valueChanges.pipe(startWith('')), {
    initialValue: '',
  });
  filteredMembers = computed(() => {
    const rawTerm = this.memberTerm();
    const filter = (typeof rawTerm === 'string' ? rawTerm : '').toLowerCase();
    const members = this.memberStore.members();
    if (!filter || !members) return members || [];
    return members.filter(
      (m) =>
        m.full_name?.toLowerCase().includes(filter) ||
        m.member_number?.toLowerCase().includes(filter)
    );
  });

  private providerTypeTerm = toSignal(
    this.providerTypeFilterControl.valueChanges.pipe(startWith('')),
    { initialValue: '' }
  );
  filteredProviderTypes = computed(() => {
    const rawTerm = this.providerTypeTerm();
    const filter = (typeof rawTerm === 'string' ? rawTerm : '').toLowerCase();
    if (!filter) return this.PROVIDER_TYPES;
    return this.PROVIDER_TYPES.filter((t) => t.label.toLowerCase().includes(filter));
  });

  ngOnInit() {
    this.initForms();
    this.loadInitialData();
  }

  private initForms() {
    this.claimForm = this.fb.group({
      policy_id: [this.data?.policy_id || '', Validators.required],
      member_id: [this.data?.member_id || '', Validators.required],
      claim_type: ['out_patient', Validators.required],
      service_date: [new Date(), Validators.required],
      service_end_date: [null],
      admission_date: [null],
      discharge_date: [null],
      provider_name: [''],
      provider_type: [''],
      provider_invoice_number: [''],
      primary_diagnosis: [''],
      primary_icd_code: [''],
      diagnosis_notes: [''],
      requires_preauth: [false],
      preauth_number: [''],
    });

    this.linesForm = this.fb.group({
      lines: this.fb.array([this.createLineGroup()]),
    });

    // Sync Form Values to Filter Controls (Initial Population)
    if (this.data?.policy_id) this.onPolicySelected(this.data.policy_id);

    // In-Patient Logic
    this.claimForm.get('claim_type')?.valueChanges.subscribe((type) => {
      const admissionCtrl = this.claimForm.get('admission_date');
      if (type === 'in_patient') {
        admissionCtrl?.setValidators(Validators.required);
      } else {
        admissionCtrl?.clearValidators();
        this.claimForm.patchValue({ admission_date: null, discharge_date: null });
      }
      admissionCtrl?.updateValueAndValidity();
    });
  }

  private createLineGroup(): FormGroup {
    const group = this.fb.group({
      service_description: ['', Validators.required],
      service_code: [''],
      benefit_id: [''],
      quantity: [1, [Validators.required, Validators.min(0.01)]],
      unit: [''],
      unit_price: [0, [Validators.required, Validators.min(0)]],
      claimed_amount: [0, [Validators.required, Validators.min(0.01)]],
      // INTERNAL UI CONTROL: Not sent to backend
      _benefitSearch: [''],
    });

    // When benefit search changes, we don't need global listeners.
    // The Template reads `_benefitSearch` value directly via the FormArray reference.
    return group;
  }

  get lines(): FormArray {
    return this.linesForm.get('lines') as FormArray;
  }

  addLine() {
    this.lines.push(this.createLineGroup());
  }

  removeLine(index: number) {
    if (this.lines.length > 1) {
      this.lines.removeAt(index);
      // Cleanup eligibility state
      const current = { ...this.lineEligibility() };
      delete current[index];
      this.lineEligibility.set(current);
    }
  }

  // --- Search & Display Logic ---

  // NOTE: This function is called in the template @for loop.
  // We use the `_benefitSearch` control value directly from the form group.
  filteredBenefits(index: number) {
    const line = this.lines.at(index);
    const searchTerm = line.get('_benefitSearch')?.value;

    // If the search term is the actual ID (happens after selection), show all or matching
    // But since displayWith shows the name, the value in input is the name (string)
    const filter = (typeof searchTerm === 'string' ? searchTerm : '').toLowerCase();

    const benefits = this.planBenefits();
    if (!filter) return benefits;

    return benefits.filter(
      (b) =>
        b.benefit?.name?.toLowerCase().includes(filter) ||
        b.display_value?.toLowerCase().includes(filter)
    );
  }

  displayPolicyFn = (policyId: string | null): string => {
    const p = this.policyStore.policies()?.find((x) => x.id === policyId);
    return p ? `${p.policy_number} - ${p.policy_holder_name}` : '';
  };

  displayMemberFn = (memberId: string | null): string => {
    const m = this.memberStore.members()?.find((x) => x.id === memberId);
    return m?.full_name ?? '';
  };

  displayProviderTypeFn = (value: string | null): string => {
    return this.PROVIDER_TYPES.find((t) => t.value === value)?.label || '';
  };

  displayBenefitFn = (benefitId: string | null): string => {
    if (!benefitId) return '';
    const b = this.planBenefits().find((x) => x.benefit_id === benefitId);
    return b ? b.benefit?.name || '' : '';
  };

  // --- Selection Handlers ---

  onPolicySelected(policyId: string) {
    if (!policyId) return;
    this.claimForm.patchValue({ policy_id: policyId });
    this.policyFilterControl.setValue(policyId as any); // Set value to ID so displayWith triggers
    this.memberStore.loadAll({ policy_id: policyId, status: 'active' });

    // Reset member
    this.claimForm.patchValue({ member_id: '' });
    this.memberFilterControl.setValue('');

    // Load benefits
    const policy = this.policyStore.policies().find((p) => p.id === policyId);
    if (policy?.plan_id) this.loadPlanBenefits(policy.plan_id);
  }

  onMemberSelected(memberId: string) {
    this.claimForm.patchValue({ member_id: memberId });
    this.memberFilterControl.setValue(memberId as any);
  }

  onProviderTypeSelected(value: string) {
    this.claimForm.patchValue({ provider_type: value });
    this.providerTypeFilterControl.setValue(value as any);
  }

  onBenefitInputFocus(index: number) {
    this.currentFocusedLineIndex.set(index);
  }

  onBenefitSelected(index: number, benefitId: string) {
    const line = this.lines.at(index);
    line.patchValue({ benefit_id: benefitId });
    line.get('_benefitSearch')?.setValue(benefitId); // Triggers displayWith
    this.checkEligibility(index);
  }

  onLineAmountChange(index: number) {
    const line = this.lines.at(index);
    const qty = line.get('quantity')?.value || 0;
    const price = line.get('unit_price')?.value || 0;
    line.patchValue({ claimed_amount: qty * price });

    if (line.get('benefit_id')?.value) {
      this.checkEligibility(index);
    }
  }

  private checkEligibility(index: number) {
    const line = this.lines.at(index);
    const benefitId = line.get('benefit_id')?.value;
    const amount = line.get('claimed_amount')?.value || 0;
    const memberId = this.claimForm.get('member_id')?.value;

    if (!benefitId || !memberId) return;

    this.checkingEligibility.update((s) => ({ ...s, [index]: true }));

    this.claimStore
      .checkBenefitEligibility({
        member_id: memberId,
        benefit_id: benefitId,
        amount: amount,
        service_date: this.formatDate(this.claimForm.get('service_date')?.value),
      })
      .subscribe({
        next: (res) => {
          this.lineEligibility.update((s) => ({ ...s, [index]: res.data }));
          this.checkingEligibility.update((s) => ({ ...s, [index]: false }));
        },
        error: () => this.checkingEligibility.update((s) => ({ ...s, [index]: false })),
      });
  }

  // --- Data Loading & Utils ---

  private loadInitialData() {
    this.policyStore.loadAll({ status: 'active', per_page: 100 });
  }

  private loadPlanBenefits(planId: string) {
    this.benefitStore.loadBenefitSchedule(planId).subscribe((res) => {
      const benefits: PlanBenefit[] = [];
      Object.values(res.data).forEach((cat: any) => benefits.push(...cat));
      this.planBenefits.set(benefits.filter((b) => b.is_covered));
    });
  }

  getLineEligibility(index: number) {
    return this.lineEligibility()[index];
  }

  isCheckingEligibility(index: number) {
    return this.checkingEligibility()[index];
  }

  get totalClaimedAmount(): number {
    return this.lines.controls.reduce(
      (sum, line) => sum + (line.get('claimed_amount')?.value || 0),
      0
    );
  }

  formatCurrency(amount: number): string {
    return new Intl.NumberFormat('en-ZM', { style: 'currency', currency: 'ZMW' }).format(amount);
  }

  private formatDate(date: Date): string {
    return date ? date.toISOString().split('T')[0] : '';
  }

  nextStep() {
    this.currentStep.update((s) => Math.min(s + 1, 2));
  }
  prevStep() {
    this.currentStep.update((s) => Math.max(s - 1, 0));
  }
  cancel() {
    this.dialogRef.close();
  }

  submit() {
    if (this.claimForm.invalid || this.linesForm.invalid) return;
    this.isSubmitting.set(true);
    // ... submission logic largely same as before ...
    const payload = this.constructPayload(); // Extracted for brevity
    this.claimStore.create(payload).subscribe({
      next: (res) => {
        this.isSubmitting.set(false);
        this.dialogRef.close(res.data);
      },
      error: (err) => {
        this.isSubmitting.set(false);
        this.feedback.error(err.error?.message || 'Failed');
      },
    });
  }

  private constructPayload(): CreateClaimPayload {
    // Helper to map form values to payload
    const form = this.claimForm.value;
    const lines = this.linesForm.value.lines;
    return {
      ...form,
      service_date: this.formatDate(form.service_date),
      // Map other dates similarly...
      claimed_amount: this.totalClaimedAmount,
      lines: lines.map((l: any) => ({
        service_description: l.service_description,
        benefit_id: l.benefit_id,
        quantity: l.quantity,
        unit: l.unit,
        unit_price: l.unit_price,
        claimed_amount: l.claimed_amount,
      })),
    };
  }
}
