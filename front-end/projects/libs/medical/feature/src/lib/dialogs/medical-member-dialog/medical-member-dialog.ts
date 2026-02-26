import { Component, Inject, inject, signal, computed, OnInit, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';

import { MatDialogRef, MAT_DIALOG_DATA, MatDialogModule } from '@angular/material/dialog';
import { MatStepper, MatStepperModule } from '@angular/material/stepper';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatTooltipModule } from '@angular/material/tooltip';
import { provideNativeDateAdapter } from '@angular/material/core';
import { STEPPER_GLOBAL_OPTIONS } from '@angular/cdk/stepper';

import {
  MemberStore,
  PolicyStore,
  Member,
  CreateMemberPayload,
  MEMBER_TYPES,
  GENDERS,
  RELATIONSHIPS,
} from 'medical-data';
import { FeedbackService } from 'shared';

interface DialogData {
  member?: Member;
  policyId?: string;
  principalId?: string;
}

const TITLES = ['Mr', 'Mrs', 'Miss', 'Ms', 'Dr', 'Prof'] as const;

@Component({
  selector: 'lib-member-dialog',
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
    MatCheckboxModule,
    MatProgressSpinnerModule,
    MatTooltipModule,
  ],
  providers: [
    { provide: STEPPER_GLOBAL_OPTIONS, useValue: { showError: true } },
    provideNativeDateAdapter(),
  ],
  templateUrl: './medical-member-dialog.html',
})
export class MedicalMemberDialog implements OnInit {
  private readonly fb = inject(FormBuilder);
  readonly dialogRef = inject(MatDialogRef<MedicalMemberDialog>);
  private readonly store = inject(MemberStore);
  private readonly policyStore = inject(PolicyStore);
  private readonly feedback = inject(FeedbackService);

  @ViewChild('stepper') stepper!: MatStepper;

  readonly TITLES = TITLES;
  readonly MEMBER_TYPES = MEMBER_TYPES;
  readonly GENDERS = GENDERS;
  readonly RELATIONSHIPS = RELATIONSHIPS;

  readonly isEdit: boolean;
  readonly isSaving = computed(() => this.store.isSaving());
  readonly activePolicies = computed(() => this.policyStore.activePolicies());
  readonly calculatedAge = signal<number | null>(null);
  readonly currentStep = signal(0);

  // Step form groups
  membershipForm!: FormGroup;
  personalForm!: FormGroup;
  contactForm!: FormGroup;

  constructor(@Inject(MAT_DIALOG_DATA) public data: DialogData) {
    this.isEdit = !!data?.member;
  }

  ngOnInit(): void {
    this.initForms();
    this.policyStore.loadAll();

    if (this.isEdit && this.data.member) {
      this.patchForms(this.data.member);
    } else {
      if (this.data.policyId) {
        this.membershipForm.patchValue({ policy_id: this.data.policyId });
      }
      if (this.data.principalId) {
        this.membershipForm.patchValue({ member_type: 'spouse', principal_id: this.data.principalId });
      }
    }
  }

  private initForms(): void {
    // Step 1 — Membership context
    this.membershipForm = this.fb.group({
      policy_id:    ['', Validators.required],
      member_type:  ['principal', Validators.required],
      principal_id: [''],
      relationship: ['self', Validators.required],
    });

    this.membershipForm.get('member_type')?.valueChanges.subscribe((type) => {
      if (type === 'principal') {
        this.membershipForm.patchValue({ relationship: 'self' });
        this.membershipForm.get('principal_id')?.clearValidators();
        this.membershipForm.get('relationship')?.setValidators(Validators.required);
      } else {
        if (this.membershipForm.get('relationship')?.value === 'self') {
          this.membershipForm.patchValue({ relationship: '' });
        }
        this.membershipForm.get('principal_id')?.setValidators(Validators.required);
        this.membershipForm.get('relationship')?.setValidators(Validators.required);
      }
      this.membershipForm.get('principal_id')?.updateValueAndValidity();
      this.membershipForm.get('relationship')?.updateValueAndValidity();
    });

    // Step 2 — Personal details
    this.personalForm = this.fb.group({
      title:           [''],
      first_name:      ['', [Validators.required, Validators.minLength(2)]],
      middle_name:     [''],
      last_name:       ['', [Validators.required, Validators.minLength(2)]],
      gender:          ['', Validators.required],
      date_of_birth:   [null, Validators.required],
      national_id:     [''],
      passport_number: [''],
    });

    this.personalForm.get('date_of_birth')?.valueChanges.subscribe((val) => {
      if (val) {
        const today = new Date();
        const birth = new Date(val);
        let age = today.getFullYear() - birth.getFullYear();
        const m = today.getMonth() - birth.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
        this.calculatedAge.set(age);
      } else {
        this.calculatedAge.set(null);
      }
    });

    // Step 3 — Contact, Employment & Medical
    this.contactForm = this.fb.group({
      email:                      ['', Validators.email],
      phone:                      [''],
      mobile:                     [''],
      address:                    [''],
      city:                       [''],
      employee_number:            [''],
      job_title:                  [''],
      department:                 [''],
      has_pre_existing_conditions: [false],
      notes:                      [''],
    });
  }

  private patchForms(member: Member): void {
    this.membershipForm.patchValue({
      policy_id:    member.policy_id,
      member_type:  member.member_type,
      principal_id: member.principal_id,
      relationship: member.relationship,
    });
    this.membershipForm.get('policy_id')?.disable();

    this.personalForm.patchValue({
      title:           member.title,
      first_name:      member.first_name,
      middle_name:     member.middle_name,
      last_name:       member.last_name,
      gender:          member.gender,
      date_of_birth:   member.date_of_birth ? new Date(member.date_of_birth) : null,
      national_id:     member.national_id,
      passport_number: member.passport_number,
    });

    this.contactForm.patchValue({
      email:                      member.email,
      phone:                      member.phone,
      mobile:                     member.mobile,
      address:                    member.address,
      city:                       member.city,
      employee_number:            member.employee_number,
      job_title:                  member.job_title,
      department:                 member.department,
      has_pre_existing_conditions: member.has_pre_existing_conditions ?? false,
      notes:                      member.notes,
    });
  }

  // =========================================================================
  // STEP NAVIGATION
  // =========================================================================

  onStepChange(index: number): void {
    this.currentStep.set(index);
  }

  nextStep(): void {
    if (this.canProceed()) this.stepper.next();
  }

  previousStep(): void {
    this.stepper.previous();
  }

  canProceed(): boolean {
    switch (this.currentStep()) {
      case 0: return this.membershipForm.valid;
      case 1: return this.personalForm.valid;
      case 2: return this.contactForm.valid;
      default: return false;
    }
  }

  get isFormValid(): boolean {
    return this.membershipForm.valid && this.personalForm.valid && this.contactForm.valid;
  }

  isPrincipal(): boolean {
    return this.membershipForm.get('member_type')?.value === 'principal';
  }

  showPreExistingNotes(): boolean {
    return this.contactForm.get('has_pre_existing_conditions')?.value === true;
  }

  // =========================================================================
  // SAVE
  // =========================================================================

  save(): void {
    if (!this.isFormValid) return;

    const membership = this.membershipForm.getRawValue();
    const personal   = this.personalForm.getRawValue();
    const contact    = this.contactForm.getRawValue();

    let dob = '';
    if (personal.date_of_birth instanceof Date) {
      dob = personal.date_of_birth.toISOString().split('T')[0];
    } else {
      dob = personal.date_of_birth ?? '';
    }

    const payload: CreateMemberPayload = {
      ...membership,
      ...personal,
      ...contact,
      date_of_birth: dob,
    };

    // Remove empty optional fields
    Object.keys(payload).forEach((key) => {
      const val = (payload as any)[key];
      if (val === '' || val === null || val === undefined) {
        delete (payload as any)[key];
      }
    });

    const operation = this.isEdit
      ? this.store.update(this.data.member!.id, payload)
      : this.store.create(payload);

    operation.subscribe({
      next: () => {
        this.feedback.success(this.isEdit ? 'Member updated successfully' : 'Member created successfully');
        this.dialogRef.close(true);
      },
      error: (err) => {
        this.feedback.error(err?.error?.message ?? `Failed to ${this.isEdit ? 'update' : 'create'} member`);
      },
    });
  }
}
