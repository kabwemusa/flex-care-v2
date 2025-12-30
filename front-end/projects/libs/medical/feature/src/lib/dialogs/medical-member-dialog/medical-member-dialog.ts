// libs/medical/feature/src/lib/dialogs/medical-member-dialog/medical-member-dialog.ts
// Medical Member Dialog - Create/Edit with Stepper - Aligned with Plan/Scheme patterns

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
import { MatAutocompleteModule } from '@angular/material/autocomplete';
import { STEPPER_GLOBAL_OPTIONS } from '@angular/cdk/stepper';

// Domain Imports
import {
  MemberStore,
  PolicyStore,
  Member,
  Policy,
  CreateMemberPayload,
  MEMBER_TYPES,
  GENDERS,
  MARITAL_STATUSES,
  RELATIONSHIPS,
  ID_TYPES,
  PROVINCES,
} from 'medical-data';
import { provideNativeDateAdapter } from '@angular/material/core';

interface DialogData {
  member?: Member;
  policyId?: string;
}

@Component({
  selector: 'lib-medical-member-dialog',
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
    MatAutocompleteModule,
  ],
  providers: [
    {
      provide: STEPPER_GLOBAL_OPTIONS,
      useValue: { showError: true },
    },
    provideNativeDateAdapter(),
  ],
  templateUrl: './medical-member-dialog.html',
})
export class MedicalMemberDialog implements OnInit {
  readonly store = inject(MemberStore);
  readonly policyStore = inject(PolicyStore);
  private readonly fb = inject(FormBuilder);
  readonly dialogRef = inject(MatDialogRef<MedicalMemberDialog>);

  @ViewChild('stepper') stepper!: MatStepper;

  // Constants
  readonly MEMBER_TYPES = MEMBER_TYPES;
  readonly GENDERS = GENDERS;
  readonly MARITAL_STATUSES = MARITAL_STATUSES;
  readonly RELATIONSHIPS = RELATIONSHIPS;
  readonly ID_TYPES = ID_TYPES;
  readonly PROVINCES = PROVINCES;

  // State
  isEdit = false;
  policies = signal<Policy[]>([]);
  currentStep = signal(0);

  // Forms
  personalForm!: FormGroup;
  contactForm!: FormGroup;
  policyForm!: FormGroup;

  constructor(@Inject(MAT_DIALOG_DATA) public data: DialogData) {
    this.isEdit = !!data?.member;
  }

  ngOnInit(): void {
    this.initForms();
    this.loadPolicies();

    if (this.isEdit && this.data.member) {
      this.patchForms(this.data.member);
    }

    if (this.data.policyId) {
      this.policyForm.patchValue({ policy_id: this.data.policyId });
    }
  }

  private initForms(): void {
    this.personalForm = this.fb.group({
      member_type: ['principal', Validators.required],
      relationship: [''],
      title: [''],
      first_name: ['', Validators.required],
      middle_name: [''],
      last_name: ['', Validators.required],
      gender: ['', Validators.required],
      date_of_birth: ['', Validators.required],
      marital_status: [''],
      id_type: ['nrc'],
      id_number: [''],
    });

    this.contactForm = this.fb.group({
      email: ['', Validators.email],
      phone: [''],
      mobile: [''],
      address: [''],
      city: [''],
      province: [''],
    });

    this.policyForm = this.fb.group({
      policy_id: [''],
      effective_date: [new Date()],
      employee_number: [''],
      department: [''],
      job_title: [''],
      employment_date: [''],
      notes: [''],
    });
  }

  private patchForms(member: Member): void {
    this.personalForm.patchValue({
      member_type: member.member_type,
      relationship: member.relationship,
      title: member.title,
      first_name: member.first_name,
      middle_name: member.middle_name,
      last_name: member.last_name,
      gender: member.gender,
      date_of_birth: new Date(member.date_of_birth),
      marital_status: member.marital_status,
      id_type: member.id_type,
      id_number: member.id_number,
    });

    this.contactForm.patchValue({
      email: member.email,
      phone: member.phone,
      mobile: member.mobile,
      address: member.address,
      city: member.city,
      province: member.province,
    });

    this.policyForm.patchValue({
      policy_id: member.policy_id,
      effective_date: member.effective_date ? new Date(member.effective_date) : null,
      employee_number: member.employee_number,
      department: member.department,
      job_title: member.job_title,
      employment_date: member.employment_date ? new Date(member.employment_date) : null,
      notes: member.notes,
    });
  }

  private loadPolicies(): void {
    this.policyStore.loadAll();
    setTimeout(() => {
      this.policies.set(this.policyStore.activePolicies());
    }, 500);
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
    if (step === 0) return this.personalForm.valid;
    if (step === 1) return true; // Contact is optional
    return true;
  }

  // =========================================================================
  // HELPERS
  // =========================================================================

  filteredRelationships() {
    const memberType = this.personalForm.get('member_type')?.value;
    return RELATIONSHIPS.filter((r) => {
      if (memberType === 'spouse') return r.value === 'spouse';
      if (memberType === 'child') return ['child', 'adopted_child', 'step_child'].includes(r.value);
      if (memberType === 'parent') return ['parent', 'parent_in_law'].includes(r.value);
      return true;
    });
  }

  isFormValid(): boolean {
    return this.personalForm.valid;
  }

  // =========================================================================
  // SAVE
  // =========================================================================

  save(): void {
    if (!this.isFormValid()) return;

    const personalValues = this.personalForm.value;
    const contactValues = this.contactForm.value;
    const policyValues = this.policyForm.value;

    // Format dates
    if (personalValues.date_of_birth instanceof Date) {
      personalValues.date_of_birth = personalValues.date_of_birth.toISOString().split('T')[0];
    }
    if (policyValues.effective_date instanceof Date) {
      policyValues.effective_date = policyValues.effective_date.toISOString().split('T')[0];
    }
    if (policyValues.employment_date instanceof Date) {
      policyValues.employment_date = policyValues.employment_date.toISOString().split('T')[0];
    }

    const payload: CreateMemberPayload = {
      ...personalValues,
      ...contactValues,
      ...policyValues,
    };

    // Clean up empty values
    Object.keys(payload).forEach((key) => {
      const value = (payload as any)[key];
      if (value === '' || value === null) {
        delete (payload as any)[key];
      }
    });

    const operation = this.isEdit
      ? this.store.update(this.data.member!.id, payload)
      : this.store.create(payload);

    operation.subscribe((res) => {
      if (res) {
        this.dialogRef.close(true);
      }
    });
  }
}
