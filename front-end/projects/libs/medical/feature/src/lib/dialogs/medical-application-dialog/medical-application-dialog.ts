// libs/medical/ui/src/lib/dialogs/application-dialog/application-dialog.component.ts

import { Component, computed, inject, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators, FormArray } from '@angular/forms';

import { MatDialogRef, MAT_DIALOG_DATA, MatDialogModule } from '@angular/material/dialog';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatStepperModule } from '@angular/material/stepper';
import { MatDividerModule } from '@angular/material/divider';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { provideNativeDateAdapter } from '@angular/material/core';

import {
  ApplicationStore,
  SchemeListStore,
  PlanListStore,
  RateCardListStore,
  GroupStore,
  Application,
  APPLICATION_TYPES,
  POLICY_TYPES,
  BILLING_FREQUENCIES,
  APPLICATION_SOURCES,
  MEMBER_TYPES,
  RELATIONSHIPS,
  GENDERS,
  getLabelByValue,
} from 'medical-data';

interface DialogData {
  application?: Application;
}

@Component({
  selector: 'lib-application-dialog',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    MatDialogModule,
    MatButtonModule,
    MatIconModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatDatepickerModule,
    MatStepperModule,
    MatDividerModule,
    MatCheckboxModule,
  ],
  providers: [provideNativeDateAdapter()],
  templateUrl: './medical-application-dialog.html',
})
export class MedicalApplicationDialog implements OnInit {
  private readonly dialogRef = inject(MatDialogRef<MedicalApplicationDialog>);
  private readonly data = inject<DialogData>(MAT_DIALOG_DATA, { optional: true });
  private readonly fb = inject(FormBuilder);

  // Stores
  readonly store = inject(ApplicationStore);
  readonly schemeStore = inject(SchemeListStore);
  readonly planStore = inject(PlanListStore);
  readonly rateCardStore = inject(RateCardListStore);
  readonly groupStore = inject(GroupStore);

  // Constants
  readonly applicationTypes = APPLICATION_TYPES;
  readonly policyTypes = POLICY_TYPES;
  readonly billingFrequencies = BILLING_FREQUENCIES;
  readonly applicationSources = APPLICATION_SOURCES;
  readonly memberTypes = MEMBER_TYPES;
  readonly relationships = RELATIONSHIPS;
  readonly genders = GENDERS;

  // State
  readonly isEditMode = signal(false);
  readonly isSaving = computed(() => this.store.isSaving());

  // Data Selectors
  readonly schemes = computed(() => this.schemeStore.schemes());
  readonly groups = computed(() => this.groupStore.groups());

  // Dependent Dropdowns (Signal based)
  readonly plans = signal<{ id: string; name: string; code: string }[]>([]);
  readonly rateCards = signal<{ id: string; name: string; code: string }[]>([]);

  form!: FormGroup;

  ngOnInit() {
    this.isEditMode.set(!!this.data?.application);
    this.buildForm();
    this.loadDropdowns();

    if (this.data?.application) {
      // FIX NG0100: Wrap patch in setTimeout to break sync update cycle
      setTimeout(() => {
        if (this.data?.application) {
          this.patchForm(this.data.application);
        }
      }, 0);
    }
  }

  private buildForm() {
    this.form = this.fb.group({
      // Step 1: Basic Info
      application_type: ['new_business', Validators.required],
      policy_type: ['individual', Validators.required],
      scheme_id: ['', Validators.required],
      plan_id: ['', Validators.required],
      rate_card_id: ['', Validators.required],
      group_id: [''],

      // Step 2: Contact
      contact_name: ['', Validators.required],
      contact_email: ['', [Validators.required, Validators.email]],
      contact_phone: [''],

      // Step 3: Policy Details
      proposed_start_date: [new Date(), Validators.required],
      policy_term_months: [12, [Validators.required, Validators.min(1), Validators.max(36)]],
      billing_frequency: ['monthly', Validators.required],
      currency: ['ZMW', Validators.required],
      source: ['online'],

      // Step 4: Members
      members: this.fb.array([]),

      // Notes
      notes: [''],
    });

    // Watch policy_type for corporate group requirement
    this.form.get('policy_type')?.valueChanges.subscribe((type) => {
      const groupControl = this.form.get('group_id');
      if (type === 'corporate') {
        groupControl?.setValidators(Validators.required);
      } else {
        groupControl?.clearValidators();
        groupControl?.setValue('');
      }
      groupControl?.updateValueAndValidity();
    });

    // Watch scheme changes
    this.form.get('scheme_id')?.valueChanges.subscribe((schemeId) => {
      if (schemeId) {
        this.loadPlansForScheme(schemeId);
      } else {
        this.plans.set([]);
        this.form.patchValue({ plan_id: '', rate_card_id: '' });
      }
    });

    // Watch plan changes
    this.form.get('plan_id')?.valueChanges.subscribe((planId) => {
      if (planId) {
        this.loadRateCardsForPlan(planId);
      } else {
        this.rateCards.set([]);
        this.form.patchValue({ rate_card_id: '' });
      }
    });

    // Add initial member if new application
    if (!this.data?.application) {
      this.addMember(true);
    }
  }

  private loadDropdowns() {
    this.schemeStore.loadAll();
    this.groupStore.loadAll();
  }

  private loadPlansForScheme(schemeId: string) {
    this.planStore.loadByScheme(schemeId).subscribe({
      next: (res) => {
        if (res.data) {
          this.plans.set(res.data.map((p) => ({ id: p.id, name: p.name, code: p.code })));
        }
        // Don't reset if we are in the middle of patching (logic handled in patchForm)
        if (this.form.get('plan_id')?.pristine) {
          // this.form.patchValue({ plan_id: '', rate_card_id: '' }, { emitEvent: false });
        }
      },
    });
  }

  private loadRateCardsForPlan(planId: string) {
    this.rateCardStore.loadByPlan(planId).subscribe({
      next: (res) => {
        if (res.data) {
          this.rateCards.set(res.data.map((r) => ({ id: r.id, name: r.name, code: r.code })));

          // Auto-select if only one active rate card and user hasn't selected one yet
          const activeCards = res.data.filter((r) => r.is_active);
          if (activeCards.length === 1 && !this.form.get('rate_card_id')?.value) {
            this.form.patchValue({ rate_card_id: activeCards[0].id });
          }
        }
      },
    });
  }

  private patchForm(app: Application) {
    // 1. Patch basic fields first
    this.form.patchValue(
      {
        application_type: app.application_type,
        policy_type: app.policy_type,
        scheme_id: app.scheme_id,
        // Don't patch plan/rate_card yet, wait for dropdowns
        group_id: app.group_id || '',
        contact_name: app.contact_name,
        contact_email: app.contact_email,
        contact_phone: app.contact_phone,
        proposed_start_date: app.proposed_start_date ? new Date(app.proposed_start_date) : null,
        policy_term_months: app.policy_term_months,
        billing_frequency: app.billing_frequency,
        currency: app.currency,
        source: app.source,
        notes: app.notes,
      },
      { emitEvent: true }
    ); // Emit event to trigger scheme listener

    // 2. Handle Cascading Data manually to ensure values stick
    if (app.scheme_id) {
      this.planStore.loadByScheme(app.scheme_id).subscribe((res) => {
        this.plans.set(res.data.map((p) => ({ id: p.id, name: p.name, code: p.code })));
        this.form.patchValue({ plan_id: app.plan_id }, { emitEvent: true });

        if (app.plan_id) {
          this.rateCardStore.loadByPlan(app.plan_id).subscribe((rcRes) => {
            this.rateCards.set(rcRes.data.map((r) => ({ id: r.id, name: r.name, code: r.code })));
            this.form.patchValue({ rate_card_id: app.rate_card_id });
          });
        }
      });
    }

    // 3. Patch Members
    this.membersArray.clear(); // Clear initial empty member
    if (app.members?.length) {
      app.members.forEach((m) => this.addMember(m.is_principal, m));
    } else {
      // Should ensure at least one slot if empty (though rare for edit)
      this.addMember(true);
    }
  }

  // Members FormArray
  get membersArray(): FormArray {
    return this.form.get('members') as FormArray;
  }

  addMember(isPrincipal = false, data?: any) {
    const memberGroup = this.fb.group({
      member_type: [isPrincipal ? 'principal' : 'spouse', Validators.required],
      relationship: [isPrincipal ? 'self' : ''],
      principal_member_id: [''],
      title: [''],
      first_name: ['', Validators.required],
      middle_name: [''],
      last_name: ['', Validators.required],
      date_of_birth: [null, Validators.required],
      gender: ['', Validators.required],
      national_id: [''],
      email: ['', [Validators.email]], // Optional for deps usually
      phone: [''],
      has_pre_existing_conditions: [false],
    });

    if (data) {
      memberGroup.patchValue({
        ...data,
        date_of_birth: data.date_of_birth ? new Date(data.date_of_birth) : null,
      });
    }

    this.membersArray.push(memberGroup);
  }

  removeMember(index: number) {
    this.membersArray.removeAt(index);
  }

  getMemberTypeLabel(value: string): string {
    return getLabelByValue(MEMBER_TYPES, value);
  }

  isPrincipalMember(index: number): boolean {
    const control = this.membersArray.at(index);
    return control ? control.get('member_type')?.value === 'principal' : false;
  }

  isCorporate(): boolean {
    return this.form.get('policy_type')?.value === 'corporate';
  }

  cancel() {
    this.dialogRef.close();
  }

  save() {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const formValue = this.form.value;

    // Format dates and clean up member data
    const payload = {
      ...formValue,
      proposed_start_date: formValue.proposed_start_date
        ? this.formatDate(formValue.proposed_start_date)
        : null,
      members: formValue.members.map((m: any) => {
        const member = {
          ...m,
          date_of_birth: m.date_of_birth ? this.formatDate(m.date_of_birth) : null,
        };

        // Remove 'self' relationship for principal members (backend doesn't accept it)
        if (m.member_type === 'principal' || m.relationship === 'self') {
          delete member.relationship;
        }

        // Remove empty/null fields
        if (!member.relationship) delete member.relationship;
        if (!member.national_id) delete member.national_id;
        if (!member.email) delete member.email;
        if (!member.phone) delete member.phone;

        return member;
      }),
    };

    // Remove empty group_id
    if (!payload.group_id) {
      delete payload.group_id;
    }

    const operation =
      this.isEditMode() && this.data?.application
        ? this.store.update(this.data.application.id, payload)
        : this.store.create(payload);

    operation.subscribe({
      next: () => this.dialogRef.close(true),
      error: (err) => console.error('Error saving application:', err),
    });
  }

  private formatDate(date: Date): string {
    // Handle timezone offset to ensure date is YYYY-MM-DD correctly
    const offset = date.getTimezoneOffset();
    const d = new Date(date.getTime() - offset * 60 * 1000);
    return d.toISOString().split('T')[0];
  }
}
