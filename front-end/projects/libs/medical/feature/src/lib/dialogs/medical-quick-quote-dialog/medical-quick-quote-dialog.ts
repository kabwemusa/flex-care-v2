// libs/medical/feature/src/lib/dialogs/medical-quick-quote-dialog/medical-quick-quote-dialog.ts

import { Component, computed, inject, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormArray, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router } from '@angular/router';

import { MatDialogRef, MAT_DIALOG_DATA, MatDialogModule } from '@angular/material/dialog';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatDividerModule } from '@angular/material/divider';

import {
  PlanListStore,
  ApplicationStore,
  MedicalPlan,
  MEMBER_TYPES,
  BILLING_FREQUENCIES,
  getLabelByValue,
} from 'medical-data';
import { FeedbackService } from 'shared';

export interface QuickQuoteDialogData {
  planId?: string;
  plan?: MedicalPlan;
}

interface QuoteResult {
  plan: { id: string; code: string; name: string };
  rate_card: { id: string; code: string; version: string };
  members: any[];
  base_premium: number;
  addon_premium: number;
  addons: any[];
  discounts: any[];
  total_discount: number;
  promo_discount: number;
  loadings: any[];
  total_loading: number;
  final_premium: number;
  currency: string;
  frequency: string;
  quote_date: string;
  valid_until: string;
}

@Component({
  selector: 'lib-medical-quick-quote-dialog',
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
    MatDividerModule,
  ],
  templateUrl: './medical-quick-quote-dialog.html',
})
export class MedicalQuickQuoteDialog implements OnInit {
  private readonly fb = inject(FormBuilder);
  private readonly planStore = inject(PlanListStore);
  private readonly appStore = inject(ApplicationStore);
  private readonly feedback = inject(FeedbackService);
  private readonly router = inject(Router);
  readonly dialogRef = inject(MatDialogRef<MedicalQuickQuoteDialog>);
  readonly data = inject<QuickQuoteDialogData>(MAT_DIALOG_DATA, { optional: true });

  readonly memberTypes = MEMBER_TYPES;
  readonly billingFrequencies = BILLING_FREQUENCIES;

  readonly isCalculating = signal(false);
  readonly isConverting = signal(false);
  readonly quote = signal<QuoteResult | null>(null);
  readonly selectedPlan = signal<MedicalPlan | null>(null);

  readonly plans = computed(() => this.planStore.plans());

  readonly memberForm = this.fb.group({
    plan_id: [this.data?.planId || '', Validators.required],
    members: this.fb.array([]),
  });

  get membersArray(): FormArray {
    return this.memberForm.get('members') as FormArray;
  }

  ngOnInit() {
    this.planStore.loadAll();

    if (this.data?.plan) {
      this.selectedPlan.set(this.data.plan);
    } else if (this.data?.planId) {
      this.planStore.loadOne(this.data.planId).subscribe({
        next: () => this.selectedPlan.set(this.planStore.selectedPlan()),
      });
    }

    // Add initial member
    this.addMember();
  }

  addMember() {
    const memberGroup = this.fb.group({
      member_type: ['principal', Validators.required],
      age: [null, [Validators.required, Validators.min(0), Validators.max(100)]],
      gender: ['', Validators.required],
    });

    this.membersArray.push(memberGroup);
  }

  removeMember(index: number) {
    if (this.membersArray.length > 1) {
      this.membersArray.removeAt(index);
    }
  }

  calculateQuote() {
    if (this.memberForm.invalid) {
      this.memberForm.markAllAsTouched();
      return;
    }

    this.isCalculating.set(true);

    const formValue = this.memberForm.getRawValue();
    const payload = {
      plan_id: formValue.plan_id!,
      members: formValue.members || [],
    };

    this.planStore.generateQuickQuote(payload).subscribe({
      next: (response) => {
        this.isCalculating.set(false);
        this.quote.set(response.data as QuoteResult);
        this.feedback.success('Quote calculated successfully');
      },
      error: (err) => {
        this.isCalculating.set(false);
        this.feedback.error(err?.error?.message || 'Failed to calculate quote');
      },
    });
  }

  convertToApplication() {
    const quoteData = this.quote();
    if (!quoteData) return;

    this.isConverting.set(true);

    // Create application data with minimal member information
    const applicationData: any = {
      plan_id: quoteData.plan.id,
      rate_card_id: quoteData.rate_card.id,
      application_type: 'individual',
      policy_type: 'individual',
      billing_frequency: quoteData.frequency,
      members: quoteData.members.map((m: any) => ({
        member_type: m.member_type,
        first_name: '',
        last_name: '',
        age_at_inception: m.age,
        gender: m.gender,
        base_premium: m.premium,
        loading_amount: 0,
        total_premium: m.premium,
      })),
    };

    this.appStore.create(applicationData).subscribe({
      next: (response) => {
        this.isConverting.set(false);
        this.feedback.success('Application created successfully');
        this.dialogRef.close(true);
        this.router.navigate(['/medical/applications', response.data.id]);
      },
      error: (err) => {
        this.isConverting.set(false);
        this.feedback.error(err?.error?.message || 'Failed to create application');
      },
    });
  }

  formatCurrency(amount: number, currency: string = 'ZMW'): string {
    return `${currency} ${(amount || 0).toLocaleString('en-US', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })}`;
  }

  formatDate(date: string): string {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-GB', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    });
  }

  getMemberTypeLabel(value: string): string {
    return getLabelByValue(MEMBER_TYPES, value);
  }

  getBillingLabel(value: string): string {
    return getLabelByValue(BILLING_FREQUENCIES, value);
  }

  cancel() {
    this.dialogRef.close();
  }
}
