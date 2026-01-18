// libs/medical/feature/src/lib/dialogs/medical-endorsement-dialog/medical-endorsement-dialog.ts

import { Component, inject, OnInit, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogRef, MatDialogModule } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatNativeDateModule, provideNativeDateAdapter } from '@angular/material/core';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatStepperModule } from '@angular/material/stepper';

import {
  Policy,
  Member,
  MedicalPlan,
  PlanAddon,
  PolicyAddon,
  MEMBER_TYPES,
  EndorsementStore,
  CreateEndorsementPayload,
} from 'medical-data';
import { FeedbackService } from 'shared';

export type EndorsementDialogMode =
  | 'add_member'
  | 'remove_member'
  | 'upgrade_plan'
  | 'downgrade_plan'
  | 'add_addon'
  | 'remove_addon';

interface DialogData {
  policy: Policy;
  mode: EndorsementDialogMode;
  member?: Member; // For remove_member
  members?: Member[]; // List of members for selection
  availablePlans?: MedicalPlan[]; // Plans in the same scheme (for upgrade/downgrade)
  availableAddons?: PlanAddon[]; // Plan addons available for adding
  policyAddons?: PolicyAddon[]; // Current policy addons (for removal)
}

interface EndorsementTypeConfig {
  title: string;
  subtitle: string;
  icon: string;
  color: string;
}

interface EndorsementTypeConfigExtended extends EndorsementTypeConfig {
  buttonText: string;
  buttonIcon: string;
}

const ENDORSEMENT_CONFIG: Record<EndorsementDialogMode, EndorsementTypeConfigExtended> = {
  add_member: {
    title: 'Add New Member',
    subtitle: 'Add a new member to this policy',
    icon: 'person_add',
    color: 'bg-green-100 text-green-700',
    buttonText: 'Add Member',
    buttonIcon: 'person_add',
  },
  remove_member: {
    title: 'Remove Member',
    subtitle: 'Terminate coverage for a member',
    icon: 'person_remove',
    color: 'bg-red-100 text-red-700',
    buttonText: 'Remove Member',
    buttonIcon: 'person_remove',
  },
  upgrade_plan: {
    title: 'Upgrade Plan',
    subtitle: 'Upgrade to a higher tier plan',
    icon: 'upgrade',
    color: 'bg-blue-100 text-blue-700',
    buttonText: 'Submit Request',
    buttonIcon: 'upgrade',
  },
  downgrade_plan: {
    title: 'Downgrade Plan',
    subtitle: 'Downgrade to a lower tier plan',
    icon: 'download',
    color: 'bg-orange-100 text-orange-700',
    buttonText: 'Submit Request',
    buttonIcon: 'download',
  },
  add_addon: {
    title: 'Add Coverage',
    subtitle: 'Add an optional coverage to this policy',
    icon: 'add_circle',
    color: 'bg-purple-100 text-purple-700',
    buttonText: 'Add Coverage',
    buttonIcon: 'add_circle',
  },
  remove_addon: {
    title: 'Remove Coverage',
    subtitle: 'Remove an add-on coverage from this policy',
    icon: 'remove_circle',
    color: 'bg-amber-100 text-amber-700',
    buttonText: 'Remove Coverage',
    buttonIcon: 'remove_circle',
  },
};

@Component({
  selector: 'lib-medical-endorsement-dialog',
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
    MatCheckboxModule,
    MatProgressSpinnerModule,
    MatStepperModule,
  ],
  providers: [provideNativeDateAdapter()],
  templateUrl: './medical-endorsement-dialog.html',
})
export class MedicalEndorsementDialog implements OnInit {
  private readonly fb = inject(FormBuilder);
  private readonly endorsementStore = inject(EndorsementStore);
  private readonly feedback = inject(FeedbackService);
  readonly dialogRef = inject(MatDialogRef<MedicalEndorsementDialog>);
  readonly data = inject<DialogData>(MAT_DIALOG_DATA);

  // Constants
  readonly memberTypes = MEMBER_TYPES;
  readonly titleOptions = ['Mr', 'Mrs', 'Miss', 'Ms', 'Dr', 'Prof'];
  readonly relationshipOptions = [
    { value: 'wife', label: 'Wife' },
    { value: 'husband', label: 'Husband' },
    { value: 'son', label: 'Son' },
    { value: 'daughter', label: 'Daughter' },
    { value: 'father', label: 'Father' },
    { value: 'mother', label: 'Mother' },
    { value: 'partner', label: 'Partner' },
  ];

  // State
  saving = signal(false);
  currentStep = signal(0);

  // Forms
  endorsementForm!: FormGroup;
  memberForm!: FormGroup;

  // Config
  config = computed(() => ENDORSEMENT_CONFIG[this.data.mode]);

  // Date constraints - must be at least today AND at least policy inception date
  minEffectiveDate = computed(() => {
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    // Policy inception date
    const inception = this.data.policy.inception_date
      ? new Date(this.data.policy.inception_date)
      : null;
    if (inception) {
      inception.setHours(0, 0, 0, 0);
    }

    // Return the later of today or inception date
    if (inception && inception > today) {
      return inception;
    }
    return today;
  });

  maxEffectiveDate = computed(() => {
    const expiry = this.data.policy.expiry_date ? new Date(this.data.policy.expiry_date) : null;
    if (!expiry) return null;

    // For add_member, restrict to 30 days before expiry (minimal coverage period)
    // For remove_member/other, allow up to expiry date
    if (this.data.mode === 'add_member') {
      const maxDate = new Date(expiry);
      maxDate.setDate(maxDate.getDate() - 30);
      return maxDate;
    }

    return expiry;
  });

  policyExpiryDate = computed(() => {
    return this.data.policy.expiry_date ? new Date(this.data.policy.expiry_date) : null;
  });

  policyInceptionDate = computed(() => {
    return this.data.policy.inception_date ? new Date(this.data.policy.inception_date) : null;
  });

  // Format date for display
  formatDateDisplay(date: Date | null): string {
    if (!date) return 'N/A';
    return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
  }

  // Pro-rata calculation
  effectiveDate = signal<Date | null>(null);
  daysRemaining = signal<number>(0);

  // Principal members for dependent selection
  principalMembers = computed(() => {
    return this.data.members?.filter((m) => m.member_type === 'principal') ?? [];
  });

  // Show principal selection only for dependents
  showPrincipalSelection = signal(false);

  // Plans for upgrade/downgrade (exclude current plan)
  alternatePlans = computed(() => {
    const plans = this.data.availablePlans ?? [];
    const currentPlanId = this.data.policy.plan_id;
    return plans.filter((p) => p.id !== currentPlanId && p.is_active);
  });

  // Check if tier levels are properly configured
  private hasTierLevels = computed(() => {
    const plans = this.data.availablePlans ?? [];
    // Check if at least 2 plans have different, non-null tier_level values
    const tierLevels = plans.map((p) => p.tier_level).filter((t) => t !== null && t !== undefined);
    const uniqueTiers = new Set(tierLevels);
    return uniqueTiers.size > 1;
  });

  // For upgrade: plans with LOWER tier level than current (Tier 1=Platinum is best)
  // Lower tier_level = better plan, so upgrade means tier_level < currentTier
  upgradePlans = computed(() => {
    const alternates = this.alternatePlans();

    // If tier levels aren't properly configured, show all alternate plans
    if (!this.hasTierLevels()) {
      return alternates;
    }

    const currentPlan = this.data.availablePlans?.find((p) => p.id === this.data.policy.plan_id);
    if (!currentPlan) return alternates;
    const currentTier = currentPlan.tier_level ?? 999;
    // Upgrade = move to LOWER tier number (better plan)
    return alternates.filter((p) => (p.tier_level ?? 999) < currentTier);
  });

  // For downgrade: plans with HIGHER tier level than current
  // Higher tier_level = worse plan, so downgrade means tier_level > currentTier
  downgradePlans = computed(() => {
    const alternates = this.alternatePlans();

    // If tier levels aren't properly configured, show all alternate plans
    if (!this.hasTierLevels()) {
      return alternates;
    }

    const currentPlan = this.data.availablePlans?.find((p) => p.id === this.data.policy.plan_id);
    if (!currentPlan) return alternates;
    const currentTier = currentPlan.tier_level ?? 0;
    // Downgrade = move to HIGHER tier number (worse plan)
    return alternates.filter((p) => (p.tier_level ?? 0) > currentTier);
  });

  // Addons available for adding (not already on policy)
  availableAddonsForPolicy = computed(() => {
    const planAddons = this.data.availableAddons ?? [];
    const policyAddonIds = (this.data.policyAddons ?? []).map((pa) => pa.addon_id);
    return planAddons.filter((pa) => !policyAddonIds.includes(pa.addon_id) && pa.is_active);
  });

  // Current policy addons for removal
  currentPolicyAddons = computed(() => {
    return (this.data.policyAddons ?? []).filter((pa) => pa.is_active);
  });

  ngOnInit() {
    this.initForms();
    this.setupWatchers();
  }

  private initForms() {
    // Calculate initial effective date - must be at least today AND at least policy inception
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const inception = this.data.policy.inception_date
      ? new Date(this.data.policy.inception_date)
      : null;
    if (inception) {
      inception.setHours(0, 0, 0, 0);
    }

    // Use the later of today or inception date as the initial value
    const initialDate = inception && inception > today ? inception : today;

    // Common endorsement fields
    this.endorsementForm = this.fb.group({
      effective_date: [initialDate, Validators.required],
      notes: [''],
    });

    // Member-specific form (for add_member)
    this.memberForm = this.fb.group({
      member_type: ['principal', Validators.required],
      principal_id: [null],
      relationship: [null],

      // Personal
      title: [''],
      first_name: ['', [Validators.required, Validators.minLength(2)]],
      middle_name: [''],
      last_name: ['', [Validators.required, Validators.minLength(2)]],
      date_of_birth: [null, Validators.required],
      gender: ['', Validators.required],

      // Identification
      national_id: [''],
      passport_number: [''],

      // Contact
      email: ['', Validators.email],
      phone: [''],
      mobile: [''],

      // Employment
      employee_number: [''],
      job_title: [''],
      department: [''],

      // Medical
      has_pre_existing_conditions: [false],
      medical_history_notes: [''],
    });

    // For remove_member, pre-select the member if provided
    if (this.data.mode === 'remove_member' && this.data.member) {
      this.endorsementForm.addControl(
        'member_id',
        this.fb.control(this.data.member.id, Validators.required)
      );
      this.endorsementForm.addControl('reason', this.fb.control('', Validators.required));
    }

    // For plan change (upgrade/downgrade)
    if (this.data.mode === 'upgrade_plan' || this.data.mode === 'downgrade_plan') {
      this.endorsementForm.addControl('new_plan_id', this.fb.control('', Validators.required));
    }

    // For addon management
    if (this.data.mode === 'add_addon') {
      this.endorsementForm.addControl('addon_id', this.fb.control('', Validators.required));
    }

    if (this.data.mode === 'remove_addon') {
      this.endorsementForm.addControl('addon_id', this.fb.control('', Validators.required));
      this.endorsementForm.addControl('reason', this.fb.control('', Validators.required));
    }

    this.effectiveDate.set(initialDate);
  }

  private setupWatchers() {
    // Watch effective date changes
    this.endorsementForm.get('effective_date')?.valueChanges.subscribe((date) => {
      this.effectiveDate.set(date);
      this.calculateDaysRemaining();
    });

    // Watch member type changes
    this.memberForm.get('member_type')?.valueChanges.subscribe((type) => {
      const isDependent = type !== 'principal';
      this.showPrincipalSelection.set(isDependent);

      if (isDependent) {
        this.memberForm.get('principal_id')?.setValidators(Validators.required);
        this.memberForm.get('relationship')?.setValidators(Validators.required);
      } else {
        this.memberForm.get('principal_id')?.clearValidators();
        this.memberForm.get('relationship')?.clearValidators();
        this.memberForm.patchValue({ principal_id: null, relationship: null });
      }
      this.memberForm.get('principal_id')?.updateValueAndValidity();
      this.memberForm.get('relationship')?.updateValueAndValidity();
    });

    // Initial calculation
    this.calculateDaysRemaining();
  }

  private calculateDaysRemaining() {
    const effectiveDate = this.effectiveDate();
    const expiryDate = this.policyExpiryDate();

    if (!effectiveDate || !expiryDate) {
      this.daysRemaining.set(0);
      return;
    }

    const oneDay = 24 * 60 * 60 * 1000;
    const days = Math.round((expiryDate.getTime() - effectiveDate.getTime()) / oneDay) + 1;
    this.daysRemaining.set(Math.max(0, days));
  }

  get isFormValid(): boolean {
    if (!this.endorsementForm.valid) return false;

    if (this.data.mode === 'add_member') {
      return this.memberForm.valid;
    }

    return true;
  }

  get isCorporatePolicy(): boolean {
    return this.data.policy.policy_type === 'corporate' || this.data.policy.policy_type === 'sme';
  }

  private formatDate(date: Date | string | null): string {
    if (!date) return '';
    const d = date instanceof Date ? date : new Date(date);
    return d.toISOString().split('T')[0];
  }

  private buildDescription(): string {
    const mode = this.data.mode;

    switch (mode) {
      case 'add_member': {
        const firstName = this.memberForm.get('first_name')?.value || '';
        const lastName = this.memberForm.get('last_name')?.value || '';
        const memberType = this.memberForm.get('member_type')?.value || '';
        return `Add new ${memberType}: ${firstName} ${lastName}`;
      }
      case 'remove_member': {
        const member = this.data.member;
        const reason = this.endorsementForm.get('reason')?.value || '';
        return `Remove member: ${member?.first_name} ${member?.last_name} - ${reason}`;
      }
      case 'upgrade_plan':
      case 'downgrade_plan': {
        const planId = this.endorsementForm.get('new_plan_id')?.value;
        const plan = this.alternatePlans().find((p) => p.id === planId);
        const action = mode === 'upgrade_plan' ? 'Upgrade' : 'Downgrade';
        return `${action} to ${plan?.name || 'new plan'}`;
      }
      case 'add_addon': {
        const addonId = this.endorsementForm.get('addon_id')?.value;
        const addon = this.availableAddonsForPolicy().find((a) => a.addon_id === addonId);
        return `Add add-on: ${addon?.addon?.name || 'New add-on'}`;
      }
      case 'remove_addon': {
        const addonId = this.endorsementForm.get('addon_id')?.value;
        const addon = this.currentPolicyAddons().find((a) => a.addon_id === addonId);
        const reason = this.endorsementForm.get('reason')?.value || '';
        return `Remove add-on: ${addon?.addon_name || addon?.addon?.name || 'Add-on'} - ${reason}`;
      }
    }
  }

  submit() {
    if (!this.isFormValid) return;

    this.saving.set(true);

    const effectiveDate = this.formatDate(this.endorsementForm.get('effective_date')?.value);
    const notes = this.endorsementForm.get('notes')?.value || undefined;

    let payload: CreateEndorsementPayload = {
      policy_id: this.data.policy.id,
      endorsement_type: this.data.mode,
      description: this.buildDescription(),
      effective_date: effectiveDate,
      notes,
    };

    // Add type-specific fields
    switch (this.data.mode) {
      case 'add_member': {
        const memberData = this.memberForm.value;
        payload.member_data = {
          member_type: memberData.member_type,
          first_name: memberData.first_name,
          last_name: memberData.last_name,
          date_of_birth: this.formatDate(memberData.date_of_birth),
          gender: memberData.gender,
          title: memberData.title || undefined,
          middle_name: memberData.middle_name || undefined,
          national_id: memberData.national_id || undefined,
          passport_number: memberData.passport_number || undefined,
          email: memberData.email || undefined,
          phone: memberData.phone || undefined,
          mobile: memberData.mobile || undefined,
          principal_id: memberData.principal_id || undefined,
          relationship: memberData.relationship || undefined,
        };
        break;
      }
      case 'remove_member': {
        payload.member_id = this.endorsementForm.get('member_id')?.value;
        payload.reason = this.endorsementForm.get('reason')?.value;
        break;
      }
      case 'upgrade_plan':
      case 'downgrade_plan': {
        payload.new_plan_id = this.endorsementForm.get('new_plan_id')?.value;
        break;
      }
      case 'add_addon': {
        payload.addon_id = this.endorsementForm.get('addon_id')?.value;
        break;
      }
      case 'remove_addon': {
        payload.addon_id = this.endorsementForm.get('addon_id')?.value;
        payload.reason = this.endorsementForm.get('reason')?.value;
        break;
      }
    }

    this.endorsementStore.create(payload).subscribe({
      next: (res) => {
        this.saving.set(false);
        this.feedback.success('Endorsement created successfully. It is now pending approval.');
        this.dialogRef.close(res.data);
      },
      error: (err) => {
        this.saving.set(false);
        this.feedback.error(err?.error?.message ?? 'Failed to create endorsement');
      },
    });
  }

  cancel() {
    this.dialogRef.close();
  }
}
