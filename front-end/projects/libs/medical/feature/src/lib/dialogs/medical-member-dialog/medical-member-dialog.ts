// libs/medical/ui/src/lib/dialogs/member-dialog/member-dialog.component.ts

import { Component, Inject, inject, signal, computed, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';

import { MatDialogRef, MAT_DIALOG_DATA, MatDialogModule } from '@angular/material/dialog';
import { MatStepperModule } from '@angular/material/stepper';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatDividerModule } from '@angular/material/divider';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { provideNativeDateAdapter } from '@angular/material/core';

import {
  MemberStore,
  PolicyStore,
  Member,
  CreateMemberPayload,
  MEMBER_TYPES,
  GENDERS,
  RELATIONSHIPS,
} from 'medical-data';

interface DialogData {
  member?: Member;
  policyId?: string;
  principalId?: string;
}

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
    MatDividerModule,
    MatProgressSpinnerModule,
  ],
  providers: [provideNativeDateAdapter()],
  template: ``,
})
export class MedicalMemberDialog implements OnInit {
  private readonly fb = inject(FormBuilder);
  readonly dialogRef = inject(MatDialogRef<MedicalMemberDialog>);
  private readonly store = inject(MemberStore);
  private readonly policyStore = inject(PolicyStore);

  readonly MEMBER_TYPES = MEMBER_TYPES;
  readonly GENDERS = GENDERS;
  readonly RELATIONSHIPS = RELATIONSHIPS;

  readonly isEdit: boolean;
  readonly isSaving = computed(() => this.store.isSaving());
  readonly activePolicies = computed(() => this.policyStore.activePolicies());
  readonly calculatedAge = signal<number | null>(null);

  form!: FormGroup;

  constructor(@Inject(MAT_DIALOG_DATA) public data: DialogData) {
    this.isEdit = !!data?.member;
  }

  ngOnInit(): void {
    this.initForm();
    this.policyStore.loadAll();

    if (this.isEdit && this.data.member) {
      this.patchForm(this.data.member);
    } else {
      if (this.data.policyId) {
        this.form.patchValue({ policy_id: this.data.policyId });
      }
      if (this.data.principalId) {
        this.form.patchValue({ member_type: 'spouse', principal_id: this.data.principalId });
      }
    }
  }

  private initForm(): void {
    this.form = this.fb.group({
      policy_id: ['', Validators.required],
      member_type: ['principal', Validators.required],
      principal_id: [''],
      relationship: ['self', Validators.required],
      first_name: ['', Validators.required],
      last_name: ['', Validators.required],
      gender: ['', Validators.required],
      date_of_birth: [null, Validators.required],
      national_id: [''],
      passport_number: [''],
      email: ['', Validators.email],
      phone: [''],
      address: [''],
      city: [''],
    });

    // Auto-set relationship for principal
    this.form.get('member_type')?.valueChanges.subscribe((type) => {
      if (type === 'principal') {
        this.form.patchValue({ relationship: 'self' });
      } else if (this.form.get('relationship')?.value === 'self') {
        this.form.patchValue({ relationship: '' });
      }
    });

    // Calculate age on DOB change
    this.form.get('date_of_birth')?.valueChanges.subscribe((val) => {
      if (val) {
        const today = new Date();
        const birthDate = new Date(val);
        let age = today.getFullYear() - birthDate.getFullYear();
        const m = today.getMonth() - birthDate.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
          age--;
        }
        this.calculatedAge.set(age);
      } else {
        this.calculatedAge.set(null);
      }
    });
  }

  private patchForm(member: Member): void {
    this.form.patchValue({
      policy_id: member.policy_id,
      member_type: member.member_type,
      principal_id: member.principal_id,
      relationship: member.relationship,
      first_name: member.first_name,
      last_name: member.last_name,
      gender: member.gender,
      date_of_birth: member.date_of_birth ? new Date(member.date_of_birth) : null,
      national_id: member.national_id,
      passport_number: member.passport_number,
      email: member.email,
      phone: member.phone,
      address: member.address,
      city: member.city,
    });

    // Disable policy change in edit mode
    this.form.get('policy_id')?.disable();
  }

  isPrincipal(): boolean {
    return this.form.get('member_type')?.value === 'principal';
  }

  save(): void {
    if (this.form.invalid) return;

    const formValue = this.form.getRawValue();

    // Format date
    let dob = '';
    if (formValue.date_of_birth instanceof Date) {
      const d = formValue.date_of_birth;
      dob = d.toISOString().split('T')[0];
    } else {
      dob = formValue.date_of_birth;
    }

    const payload: CreateMemberPayload = {
      ...formValue,
      date_of_birth: dob,
    };

    // Clean empty values
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
      next: () => this.dialogRef.close(true),
    });
  }
}
