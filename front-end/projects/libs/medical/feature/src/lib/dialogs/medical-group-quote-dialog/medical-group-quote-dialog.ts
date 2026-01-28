// libs/medical/feature/src/lib/dialogs/medical-group-quote-dialog/medical-group-quote-dialog.ts

import { Component, inject, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogModule, MatDialogRef } from '@angular/material/dialog';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatSelectModule } from '@angular/material/select';
import { MatInputModule } from '@angular/material/input';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatChipsModule } from '@angular/material/chips';
import { MatCardModule } from '@angular/material/card';
import { MatDividerModule } from '@angular/material/divider';
import {
  SchemeListStore,
  PlanListStore,
  QuoteStore,
  GroupQuoteRequest,
  GroupQuoteResult,
} from 'medical-data';
import { FeedbackService } from 'shared';
import { CdkNoDataRow } from '@angular/cdk/table';

interface DialogData {
  group_id: string;
  group_name: string;
  members?: any[]; // Optional pre-loaded members from census
}

@Component({
  selector: 'lib-medical-group-quote-dialog',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    MatDialogModule,
    MatButtonModule,
    MatIconModule,
    MatFormFieldModule,
    MatSelectModule,
    MatInputModule,
    MatProgressSpinnerModule,
    MatChipsModule,
    MatCardModule,
    MatDividerModule,
    CdkNoDataRow,
  ],
  templateUrl: './medical-group-quote-dialog.html',
  styleUrls: ['./medical-group-quote-dialog.scss'],
})
export class MedicalGroupQuoteDialog {
  readonly fb = inject(FormBuilder);
  readonly dialogRef = inject(MatDialogRef<MedicalGroupQuoteDialog>);
  readonly data = inject<DialogData>(MAT_DIALOG_DATA);
  readonly quoteStore = inject(QuoteStore);
  readonly schemeStore = inject(SchemeListStore);
  readonly planStore = inject(PlanListStore);
  readonly feedback = inject(FeedbackService);

  // Form
  quoteForm!: FormGroup;

  // Signals
  quoteResult = signal<GroupQuoteResult | null>(null);
  currentStep = signal<'configure' | 'results'>('configure');

  // Data (loaded from permission-free lookup endpoints)
  readonly schemes = signal<{ id: string; name: string; code: string }[]>([]);
  readonly plans = signal<{ id: string; name: string; code: string }[]>([]);
  readonly loading = this.quoteStore.isLoading;
  readonly billingFrequencies = [
    { value: 'monthly', label: 'Monthly' },
    { value: 'quarterly', label: 'Quarterly' },
    { value: 'semi_annual', label: 'Semi-Annual' },
    { value: 'annual', label: 'Annual' },
  ];

  // Computed
  hasQuote = computed(() => this.quoteResult() !== null);
  hasTierInfo = computed(() => !!this.quoteResult()?.tier_info);
  hasVolumeDiscounts = computed(() => (this.quoteResult()?.volume_discounts?.length || 0) > 0);
  nextTierAvailable = computed(() => !!this.quoteResult()?.tier_info?.next_tier);

  constructor() {
    this.quoteForm = this.fb.group({
      scheme_id: ['', Validators.required],
      plan_id: [{ value: '', disabled: true }, Validators.required],
      billing_frequency: ['monthly', Validators.required],
    });

    // Load schemes using permission-free lookup endpoint
    this.schemeStore.loadDropdown().subscribe({
      next: (res) => this.schemes.set(res.data.map((s: any) => ({ id: s.id, name: s.name, code: s.code }))),
    });

    // Watch scheme changes to load plans
    this.quoteForm.get('scheme_id')?.valueChanges.subscribe((schemeId) => {
      const planControl = this.quoteForm.get('plan_id');
      if (schemeId) {
        this.loadPlansForScheme(schemeId);
        planControl?.enable();
      } else {
        this.plans.set([]);
        planControl?.disable();
        this.quoteForm.patchValue({ plan_id: '' });
      }
    });

    // If members are pre-loaded from census, show summary
    if (this.data.members && this.data.members.length > 0) {
      this.feedback.success(`Loaded ${this.data.members.length} members from census`);
    }
  }

  private loadPlansForScheme(schemeId: string) {
    // Use permission-free lookup endpoint
    this.planStore.loadDropdown(schemeId).subscribe({
      next: (res) => {
        if (res.data) {
          this.plans.set(res.data.map((p: any) => ({ id: p.id, name: p.name, code: p.code })));
        }
      },
    });
  }

  generateQuote() {
    if (this.quoteForm.invalid) {
      this.feedback.error('Please select a scheme and plan');
      return;
    }

    if (!this.data.members || this.data.members.length === 0) {
      this.feedback.error('No members available. Please upload a census first.');
      return;
    }

    const request: GroupQuoteRequest = {
      group_id: this.data.group_id,
      plan_id: this.quoteForm.value.plan_id,
      billing_frequency: this.quoteForm.value.billing_frequency,
      members: this.data.members.map((m: any) => ({
        date_of_birth: m.date_of_birth,
        member_type: m.member_type,
        gender: m.gender,
      })),
      show_tier_breakdown: true,
      show_volume_discounts: true,
      include_next_tier_info: true,
    };

    this.quoteStore.generateGroupQuote(request).subscribe({
      next: (response) => {
        this.quoteResult.set(response.data);
        this.currentStep.set('results');
        this.feedback.success('Group quote generated successfully!');
      },
      error: (err) => {
        this.feedback.error(err?.error?.message || 'Failed to generate group quote');
      },
    });
  }

  backToConfigure() {
    this.currentStep.set('configure');
  }

  acceptQuote() {
    const quote = this.quoteResult();
    if (quote) {
      this.dialogRef.close({
        accepted: true,
        quote: quote,
      });
    }
  }

  cancel() {
    this.dialogRef.close();
  }

  formatCurrency(amount: number): string {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
    }).format(amount);
  }

  formatDate(dateString: string): string {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    });
  }

  getMemberTypeChips() {
    const summary = this.quoteResult()?.member_summary;
    if (!summary) return [];

    const chips = [];
    if (summary.principals > 0)
      chips.push({ label: 'Principals', count: summary.principals, color: 'primary' });
    if (summary.spouses > 0)
      chips.push({ label: 'Spouses', count: summary.spouses, color: 'accent' });
    if (summary.children > 0)
      chips.push({ label: 'Children', count: summary.children, color: 'warn' });
    if (summary.parents > 0)
      chips.push({ label: 'Parents', count: summary.parents, color: 'default' });
    if (summary.other > 0) chips.push({ label: 'Other', count: summary.other, color: 'default' });

    return chips;
  }

  getTierProgressPercentage(): number {
    const tierInfo = this.quoteResult()?.tier_info;
    if (!tierInfo?.next_tier) return 100;

    const current = this.quoteResult()?.member_summary?.total || 0;
    const nextMin = tierInfo.next_tier.min_members;
    const currentMin = tierInfo.current_tier.min_members;

    return Math.min(100, ((current - currentMin) / (nextMin - currentMin)) * 100);
  }
}
