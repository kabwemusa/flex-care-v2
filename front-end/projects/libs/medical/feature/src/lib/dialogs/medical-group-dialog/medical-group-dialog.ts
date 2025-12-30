// libs/medical/feature/src/lib/dialogs/medical-group-dialog/medical-group-dialog.ts
// Corporate Group Dialog - Create/Edit with Stepper - Aligned with Plan/Scheme patterns

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
import { MatCheckboxModule } from '@angular/material/checkbox';
import { MatDividerModule } from '@angular/material/divider';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { STEPPER_GLOBAL_OPTIONS } from '@angular/cdk/stepper';

// Domain Imports
import {
  GroupStore,
  CorporateGroup,
  CreateGroupPayload,
  INDUSTRIES,
  COMPANY_SIZES,
  PAYMENT_TERMS,
  PAYMENT_METHODS,
  CONTACT_TYPES,
  PROVINCES,
} from 'medical-data';

interface DialogData {
  group?: CorporateGroup;
}

@Component({
  selector: 'lib-medical-group-dialog',
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
    MatCheckboxModule,
    MatDividerModule,
    MatProgressSpinnerModule,
  ],
  providers: [
    {
      provide: STEPPER_GLOBAL_OPTIONS,
      useValue: { showError: true },
    },
  ],
  templateUrl: './medical-group-dialog.html',
})
export class MedicalGroupDialog implements OnInit {
  readonly store = inject(GroupStore);
  private readonly fb = inject(FormBuilder);
  readonly dialogRef = inject(MatDialogRef<MedicalGroupDialog>);

  @ViewChild('stepper') stepper!: MatStepper;

  // Constants
  readonly INDUSTRIES = INDUSTRIES;
  readonly COMPANY_SIZES = COMPANY_SIZES;
  readonly PAYMENT_TERMS = PAYMENT_TERMS;
  readonly PAYMENT_METHODS = PAYMENT_METHODS;
  readonly CONTACT_TYPES = CONTACT_TYPES;
  readonly PROVINCES = PROVINCES;

  // State
  isEdit = false;
  showPrimaryContact = signal(false);
  currentStep = signal(0);

  // Forms
  companyForm!: FormGroup;
  contactForm!: FormGroup;
  billingForm!: FormGroup;

  constructor(@Inject(MAT_DIALOG_DATA) public data: DialogData) {
    this.isEdit = !!data?.group;
    this.showPrimaryContact.set(!!data?.group?.primary_contact);
  }

  ngOnInit(): void {
    this.initForms();

    if (this.isEdit && this.data.group) {
      this.patchForms(this.data.group);
    }
  }

  private initForms(): void {
    this.companyForm = this.fb.group({
      name: ['', Validators.required],
      trading_name: [''],
      registration_number: [''],
      tax_number: [''],
      industry: [''],
      company_size: [''],
      employee_count: [null],
    });

    this.contactForm = this.fb.group({
      email: ['', [Validators.email]],
      phone: [''],
      website: [''],
      physical_address: [''],
      city: [''],
      province: [''],
    });

    this.billingForm = this.fb.group({
      payment_terms: ['30_days'],
      preferred_payment_method: [''],
      billing_email: ['', [Validators.email]],
      notes: [''],
      primary_contact: this.fb.group({
        first_name: [''],
        last_name: [''],
        job_title: [''],
        contact_type: ['primary'],
        email: ['', [Validators.email]],
        phone: [''],
      }),
    });
  }

  private patchForms(group: CorporateGroup): void {
    this.companyForm.patchValue({
      name: group.name,
      trading_name: group.trading_name,
      registration_number: group.registration_number,
      tax_number: group.tax_number,
      industry: group.industry,
      company_size: group.company_size,
      employee_count: group.employee_count,
    });

    this.contactForm.patchValue({
      email: group.email,
      phone: group.phone,
      website: group.website,
      physical_address: group.physical_address,
      city: group.city,
      province: group.province,
    });

    this.billingForm.patchValue({
      payment_terms: group.payment_terms,
      preferred_payment_method: group.preferred_payment_method,
      billing_email: group.billing_email,
      notes: group.notes,
    });

    if (group.primary_contact) {
      this.billingForm.get('primary_contact')?.patchValue({
        first_name: group.primary_contact.first_name,
        last_name: group.primary_contact.last_name,
        job_title: group.primary_contact.job_title,
        contact_type: group.primary_contact.contact_type,
        email: group.primary_contact.email,
        phone: group.primary_contact.phone,
      });
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
    if (step === 0) return this.companyForm.valid;
    if (step === 1) return this.contactForm.valid;
    return true;
  }

  isFormValid(): boolean {
    return this.companyForm.valid;
  }

  // =========================================================================
  // SAVE
  // =========================================================================

  save(): void {
    if (!this.isFormValid()) return;

    const payload: CreateGroupPayload = {
      ...this.companyForm.value,
      ...this.contactForm.value,
      ...this.billingForm.value,
    };

    // Remove primary_contact if not adding one
    if (!this.showPrimaryContact()) {
      delete (payload as any).primary_contact;
    }

    // Clean up empty values
    Object.keys(payload).forEach((key) => {
      const value = (payload as any)[key];
      if (value === '' || value === null) {
        delete (payload as any)[key];
      }
    });

    const operation = this.isEdit
      ? this.store.update(this.data.group!.id, payload)
      : this.store.create(payload);

    operation.subscribe((res) => {
      if (res) {
        this.dialogRef.close(true);
      }
    });
  }
}
