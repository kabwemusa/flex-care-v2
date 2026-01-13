// libs/medical/feature/src/lib/dialogs/medical-add-member-to-policy-dialog/medical-add-member-to-policy-dialog.ts

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

import { Policy, MEMBER_TYPES } from 'medical-data';

interface DialogData {
  policy: Policy;
}

@Component({
  selector: 'lib-medical-add-member-to-policy-dialog',
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
  ],
  providers: [provideNativeDateAdapter()],
  templateUrl: './medical-add-member-to-policy-dialog.html',
  styleUrl: './medical-add-member-to-policy-dialog.css',
})
export class MedicalAddMemberToPolicyDialog implements OnInit {
  private readonly fb = inject(FormBuilder);
  readonly dialogRef = inject(MatDialogRef<MedicalAddMemberToPolicyDialog>);
  readonly data = inject<DialogData>(MAT_DIALOG_DATA);

  // Constants
  readonly memberTypes = MEMBER_TYPES;
  readonly titleOptions = ['Mr', 'Mrs', 'Miss', 'Ms', 'Dr', 'Prof'];

  // Form
  memberForm!: FormGroup;

  // Computed values
  effectiveDate = signal<Date | null>(null);
  policyExpiryDate = computed(() => {
    return this.data.policy.expiry_date ? new Date(this.data.policy.expiry_date) : null;
  });

  minEffectiveDate = computed(() => {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return today;
  });

  maxEffectiveDate = computed(() => {
    const expiry = this.policyExpiryDate();
    if (!expiry) return null;

    // Max date is expiry - 30 days (minimum coverage period)
    const maxDate = new Date(expiry);
    maxDate.setDate(maxDate.getDate() - 30);
    return maxDate;
  });

  // Pro-rata calculation results
  daysRemaining = signal<number>(0);
  annualPremium = signal<number>(0);
  proratedPremium = signal<number>(0);

  ngOnInit() {
    this.initForm();
    this.setupDateWatcher();
  }

  private initForm() {
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);

    this.memberForm = this.fb.group({
      effective_date: [tomorrow, Validators.required],
      member_type: ['principal', Validators.required],
      principal_member_id: [null],

      // Personal Information
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

      // Employment (for corporate)
      employee_number: [''],
      job_title: [''],
      department: [''],

      // Medical Declaration
      has_pre_existing_conditions: [false],
      medical_history_notes: [''],
    });

    // Set initial effective date
    this.effectiveDate.set(tomorrow);
  }

  private setupDateWatcher() {
    this.memberForm.get('effective_date')?.valueChanges.subscribe((date) => {
      this.effectiveDate.set(date);
      this.calculateProRata();
    });

    this.memberForm.get('date_of_birth')?.valueChanges.subscribe(() => {
      this.calculateProRata();
    });
  }

  private calculateProRata() {
    const effectiveDate = this.effectiveDate();
    const expiryDate = this.policyExpiryDate();
    const dob = this.memberForm.get('date_of_birth')?.value;

    if (!effectiveDate || !expiryDate || !dob) {
      this.daysRemaining.set(0);
      this.annualPremium.set(0);
      this.proratedPremium.set(0);
      return;
    }

    // Calculate days remaining (including both start and end dates)
    const oneDay = 24 * 60 * 60 * 1000;
    const days = Math.round((expiryDate.getTime() - effectiveDate.getTime()) / oneDay) + 1;
    this.daysRemaining.set(days);

    // Calculate age at effective date
    const age = this.calculateAge(dob, effectiveDate);

    // Estimate annual premium based on age (this is just for display - actual calculation happens on backend)
    // Using rough estimates: base rate increases with age
    const baseRate = 1000; // Base annual premium
    const ageMultiplier = 1 + (age / 100); // Simple age factor
    const estimated = baseRate * ageMultiplier;

    this.annualPremium.set(estimated);

    // Pro-rata calculation: (Annual / 365) × Days Remaining
    const prorated = (estimated / 365) * days;
    this.proratedPremium.set(Math.round(prorated * 100) / 100);
  }

  private calculateAge(birthDate: Date, referenceDate: Date): number {
    const birth = new Date(birthDate);
    const reference = new Date(referenceDate);

    let age = reference.getFullYear() - birth.getFullYear();
    const monthDiff = reference.getMonth() - birth.getMonth();

    if (monthDiff < 0 || (monthDiff === 0 && reference.getDate() < birth.getDate())) {
      age--;
    }

    return age;
  }

  get isFormValid(): boolean {
    return this.memberForm.valid;
  }

  get isCorporatePolicy(): boolean {
    return this.data.policy.policy_type === 'corporate' || this.data.policy.policy_type === 'sme';
  }

  save() {
    if (!this.isFormValid) return;

    // Format dates to ISO string
    const formValue = this.memberForm.value;
    const effectiveDate = formValue.effective_date instanceof Date
      ? formValue.effective_date.toISOString().split('T')[0]
      : formValue.effective_date;

    const dateOfBirth = formValue.date_of_birth instanceof Date
      ? formValue.date_of_birth.toISOString().split('T')[0]
      : formValue.date_of_birth;

    const result = {
      ...formValue,
      effective_date: effectiveDate,
      date_of_birth: dateOfBirth,
    };

    this.dialogRef.close(result);
  }

  cancel() {
    this.dialogRef.close();
  }
}
