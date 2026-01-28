// libs/medical/feature/src/lib/dialogs/medical-record-payment-dialog/medical-record-payment-dialog.ts

import { Component, inject, signal, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatDialogRef, MAT_DIALOG_DATA, MatDialogModule } from '@angular/material/dialog';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatAutocompleteModule } from '@angular/material/autocomplete';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { debounceTime, distinctUntilChanged, switchMap, of } from 'rxjs';

import { PolicyStore, formatCurrency, RecordPaymentPayload } from 'medical-data';
import { BILLING_PAYMENT_METHODS } from 'medical-data';
import { provideNativeDateAdapter } from '@angular/material/core';

interface DialogData {
  policyId?: string;
  invoiceId?: string;
  suggestedAmount?: number;
}

@Component({
  selector: 'lib-medical-record-payment-dialog',
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
    MatAutocompleteModule,
    MatProgressSpinnerModule,
  ],
  providers: [provideNativeDateAdapter()],
  templateUrl: './medical-record-payment-dialog.html',
})
export class MedicalRecordPaymentDialog implements OnInit {
  private readonly fb = inject(FormBuilder);
  private readonly dialogRef = inject(MatDialogRef<MedicalRecordPaymentDialog>);
  private readonly data = inject<DialogData>(MAT_DIALOG_DATA, { optional: true });
  private readonly policyStore = inject(PolicyStore);

  readonly PAYMENT_METHODS = BILLING_PAYMENT_METHODS;
  readonly formatCurrency = formatCurrency;

  form!: FormGroup;
  isSubmitting = signal(false);
  searchingPolicies = signal(false);
  policySearchResults = signal<Array<{ id: string; policy_number: string; holder_name: string }>>(
    []
  );
  selectedPolicy = signal<{ id: string; policy_number: string; holder_name: string } | null>(null);

  readonly today = new Date();

  ngOnInit(): void {
    this.initForm();

    // Pre-fill if data provided
    if (this.data?.policyId) {
      this.form.patchValue({ policy_id: this.data.policyId });
      // Load the policy details to display
      this.loadPolicyDetails(this.data.policyId);
    }
    if (this.data?.suggestedAmount) {
      this.form.patchValue({ amount: this.data.suggestedAmount });
    }
  }

  private loadPolicyDetails(policyId: string): void {
    this.policyStore.loadOne(policyId).subscribe({
      next: (res) => {
        const policy = res.data;
        if (policy) {
          this.selectedPolicy.set({
            id: policy.id,
            policy_number: policy.policy_number,
            holder_name: policy.holder_name ?? '',
          });
          this.form.patchValue({
            policy_search: `${policy.policy_number} - ${policy.holder_name ?? ''}`,
          });
        }
      },
    });
  }

  private initForm(): void {
    this.form = this.fb.group({
      policy_id: ['', Validators.required],
      policy_search: [''],
      amount: ['', [Validators.required, Validators.min(0.01)]],
      payment_date: [new Date(), Validators.required],
      payment_method: ['bank_transfer', Validators.required],
      reference: [''],
      notes: [''],
    });

    // Setup policy search
    this.form
      .get('policy_search')
      ?.valueChanges.pipe(
        debounceTime(300),
        distinctUntilChanged(),
        switchMap((term: string) => {
          if (!term || term.length < 2) {
            this.policySearchResults.set([]);
            return of([]);
          }
          this.searchingPolicies.set(true);
          // Search policies
          this.policyStore.loadAll({ search: term, per_page: 10, page: 1 });
          return of(this.policyStore.policies());
        })
      )
      .subscribe((results) => {
        this.searchingPolicies.set(false);
        if (Array.isArray(results)) {
          this.policySearchResults.set(
            results.map((p) => ({
              id: p.id,
              policy_number: p.policy_number,
              holder_name: p.holder_name ?? '',
            }))
          );
        }
      });
  }

  selectPolicy(policy: { id: string; policy_number: string; holder_name: string }): void {
    this.selectedPolicy.set(policy);
    this.form.patchValue({
      policy_id: policy.id,
      policy_search: `${policy.policy_number} - ${policy.holder_name}`,
    });
    this.policySearchResults.set([]);
  }

  clearPolicy(): void {
    this.selectedPolicy.set(null);
    this.form.patchValue({
      policy_id: '',
      policy_search: '',
    });
  }

  getMethodLabel(value: string): string {
    return BILLING_PAYMENT_METHODS.find((m) => m.value === value)?.label ?? value;
  }

  submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.isSubmitting.set(true);

    const formValue = this.form.value;
    const payload: RecordPaymentPayload = {
      policy_id: formValue.policy_id,
      amount: parseFloat(formValue.amount),
      payment_date: this.formatDateForApi(formValue.payment_date),
      payment_method: formValue.payment_method,
      payment_reference: formValue.reference || undefined,
      notes: formValue.notes || undefined,
    };

    // Return the payload to the parent component
    this.dialogRef.close(payload);
  }

  private formatDateForApi(date: Date): string {
    return date.toISOString().split('T')[0];
  }

  cancel(): void {
    this.dialogRef.close(null);
  }
}
