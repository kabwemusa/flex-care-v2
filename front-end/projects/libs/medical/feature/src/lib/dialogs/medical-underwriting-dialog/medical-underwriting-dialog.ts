// libs/medical/ui/src/lib/dialogs/underwriting-dialog/underwriting-dialog.component.ts

import { Component, computed, inject, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  FormBuilder,
  FormGroup,
  FormArray,
  ReactiveFormsModule,
  Validators,
  FormControl,
  AbstractControl,
} from '@angular/forms';

import { MatDialogRef, MAT_DIALOG_DATA, MatDialogModule } from '@angular/material/dialog';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { MatRadioModule } from '@angular/material/radio';
import { MatExpansionModule } from '@angular/material/expansion';
import { MatChipsModule } from '@angular/material/chips';
import { MatDividerModule } from '@angular/material/divider';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatAutocompleteModule } from '@angular/material/autocomplete';
import { MatCardModule } from '@angular/material/card';
import { MatBadgeModule } from '@angular/material/badge';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { provideNativeDateAdapter } from '@angular/material/core';

import {
  ApplicationStore,
  LoadingRuleStore,
  DiscountListStore,
  PlanExclusionStore,
  ApplicationMember,
  LOADING_TYPES,
  DURATION_TYPES,
  CONDITION_CATEGORIES,
  LoadingRule,
  DiscountRule,
  PromoCode,
  PlanExclusion,
} from 'medical-data';
import { FeedbackService } from 'shared';

interface DialogData {
  applicationId: string;
  member: ApplicationMember;
}

@Component({
  selector: 'lib-underwriting-dialog',
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
    MatCheckboxModule,
    MatRadioModule,
    MatExpansionModule,
    MatChipsModule,
    MatDividerModule,
    MatDatepickerModule,
    MatAutocompleteModule,
    MatCardModule,
    MatBadgeModule,
    MatProgressSpinnerModule,
  ],
  providers: [provideNativeDateAdapter()],
  templateUrl: `./medical-underwriting-dialog.html`,
})
export class MedicalUnderwritingDialog implements OnInit {
  private readonly dialogRef = inject(MatDialogRef<MedicalUnderwritingDialog>);
  private readonly data = inject<DialogData>(MAT_DIALOG_DATA);
  private readonly fb = inject(FormBuilder);
  private readonly store = inject(ApplicationStore);
  private readonly loadingRuleStore = inject(LoadingRuleStore);
  private readonly discountStore = inject(DiscountListStore);
  private readonly planExclusionStore = inject(PlanExclusionStore);
  private readonly feedback = inject(FeedbackService);

  readonly loadingTypes = LOADING_TYPES;
  readonly durationTypes = DURATION_TYPES;
  readonly conditionCategories = CONDITION_CATEGORIES;
  readonly isSaving = signal(false);

  // Search, Filter and Promo
  readonly searchQuery = signal<string>('');
  readonly selectedCategory = signal<string>('');
  readonly promoCodeControl = new FormControl('');
  readonly isValidatingPromo = signal(false);

  // Expansion panel states
  readonly loadingsExpanded = signal(true);
  readonly discountsExpanded = signal(false);
  readonly exclusionsExpanded = signal(false);

  // --- Rules & Computed Logic ---

  // Get active rules from store
  readonly availableRules = computed(() =>
    this.loadingRuleStore.loadingRules().filter((r) => r.is_active)
  );

  // Filtered and grouped rules
  readonly filteredRules = computed(() => {
    let rules = this.availableRules();
    const query = this.searchQuery().toLowerCase();
    const category = this.selectedCategory();

    if (query) {
      rules = rules.filter(
        (r) =>
          r.condition_name.toLowerCase().includes(query) ||
          r.icd10_code?.toLowerCase().includes(query) ||
          r.code.toLowerCase().includes(query)
      );
    }

    if (category) {
      rules = rules.filter((r) => r.condition_category === category);
    }

    return rules;
  });

  // Group rules by category
  readonly groupedRules = computed(() => {
    const rules = this.filteredRules();
    const grouped: Record<string, LoadingRule[]> = {};

    rules.forEach((rule) => {
      const cat = rule.condition_category || 'other';
      if (!grouped[cat]) {
        grouped[cat] = [];
      }
      grouped[cat].push(rule);
    });

    return grouped;
  });

  readonly groupedRuleCategories = computed(() => Object.keys(this.groupedRules()));

  form!: FormGroup;

  // --- Getters ---

  get member(): ApplicationMember {
    return this.data.member;
  }

  get memberName(): string {
    return this.member.full_name || `${this.member.first_name} ${this.member.last_name}`;
  }

  get loadingsArray(): FormArray {
    return this.form.get('loadings') as FormArray;
  }

  get discountsArray(): FormArray {
    return this.form.get('discounts') as FormArray;
  }

  get exclusionsArray(): FormArray {
    return this.form.get('exclusions') as FormArray;
  }

  // --- Initialization ---

  ngOnInit() {
    this.buildForm();
    // Load all required data from configured sources
    this.loadingRuleStore.loadAll({ active_only: true });
    this.discountStore.loadRules({ adjustment_type: 'discount' });
    this.discountStore.loadPromoCodes({ active_only: true });

    // Load plan exclusions for the member's plan
    // Try to get application from store first, otherwise load it
    let application = this.store.selectedApplication();
    if (!application || application.id !== this.data.applicationId) {
      // Load the application if not already loaded
      this.store.loadOne(this.data.applicationId).subscribe({
        next: () => {
          application = this.store.selectedApplication();
          if (application?.plan_id) {
            this.planExclusionStore
              .loadForPlan(application.plan_id, { active_only: true })
              .subscribe();
          }
        },
      });
    } else if (application?.plan_id) {
      this.planExclusionStore.loadForPlan(application.plan_id, { active_only: true }).subscribe();
    }
  }

  // Get available discount rules for dropdown
  readonly availableDiscountRules = computed(() =>
    this.discountStore
      .discountRules()
      .filter((r) => r.is_active && r.adjustment_type === 'discount')
  );

  // Get available promo codes
  readonly availablePromoCodes = computed(() => this.discountStore.activePromoCodes());

  // Get available plan exclusions
  readonly availablePlanExclusions = computed(() => this.planExclusionStore.activeExclusions());

  // Get applied promo codes with their index for removal
  readonly appliedPromoCodes = computed(() => {
    const promos: { code: string; index: number }[] = [];
    this.discountsArray.controls.forEach((ctrl, idx) => {
      if (ctrl.get('is_promo')?.value && ctrl.get('promo_code')?.value) {
        promos.push({ code: ctrl.get('promo_code')?.value, index: idx });
      }
    });
    return promos;
  });

  private buildForm() {
    this.form = this.fb.group({
      decision: ['approve', Validators.required],
      loadings: this.fb.array([]),
      discounts: this.fb.array([]),
      exclusions: this.fb.array([]),
      notes: [''],
    });
  }

  // --- Financial Calculations ---

  formatCurrency(amount: number): string {
    return new Intl.NumberFormat('en-ZM', {
      style: 'currency',
      currency: 'ZMW',
      minimumFractionDigits: 2,
    }).format(amount || 0);
  }

  // Calculate generic total premium (Base + Loadings - Discounts)
  calculateNetPremium(): number {
    const base = this.member.base_premium || 0;
    const loadings = this.getTotalLoadingImpact();
    const discounts = this.getTotalDiscountImpact();
    return Math.max(0, base + loadings - discounts);
  }

  // --- Loadings Logic ---
  // Only allow selection from configured LoadingRules

  // Add loading from rule (selection only)
  addLoadingFromRule(rule: LoadingRule) {
    const loadingGroup = this.fb.group({
      loading_rule_id: [rule.id, Validators.required],
      condition_name: [{ value: rule.condition_name, disabled: true }],
      icd10_code: [{ value: rule.icd10_code || '', disabled: true }],
      loading_type: [rule.loading_type, Validators.required],
      value: [rule.loading_value || 0, [Validators.required, Validators.min(0)]],
      duration_type: [rule.duration_type || 'permanent'],
      duration_months: [rule.duration_months || null],
      notes: [''],
    });

    this.loadingsArray.push(loadingGroup);
  }

  removeLoading(index: number) {
    this.loadingsArray.removeAt(index);
  }

  removeLoadingByRuleId(ruleId: string) {
    const index = this.loadingsArray.controls.findIndex(
      (ctrl) => ctrl.get('loading_rule_id')?.value === ruleId
    );
    if (index !== -1) {
      this.loadingsArray.removeAt(index);
    }
  }

  // Helper to calc single loading line item
  calculateSingleLoadingImpact(control: AbstractControl): number {
    const type = control.get('loading_type')?.value;
    const val = control.get('value')?.value || 0;
    const base = this.member.base_premium || 0;
    return type === 'percentage' ? (base * val) / 100 : val;
  }

  // Calculate single rule impact (for display in grid)
  calculateLoadingImpact(rule: LoadingRule): number {
    const basePremium = this.member.base_premium || 0;
    if (rule.loading_type === 'percentage' && rule.loading_value) {
      return (basePremium * rule.loading_value) / 100;
    }
    return rule.loading_value || 0;
  }

  getTotalLoadingImpact(): number {
    let total = 0;
    this.loadingsArray.controls.forEach((ctrl) => {
      total += this.calculateSingleLoadingImpact(ctrl);
    });
    return total;
  }

  isRuleAdded(ruleId: string): boolean {
    return this.loadingsArray.controls.some(
      (ctrl) => ctrl.get('loading_rule_id')?.value === ruleId
    );
  }

  getCategoryLabel(category: string): string {
    const cat = this.conditionCategories.find((c) => c.value === category);
    return cat?.label || category;
  }

  // --- Discount & Promo Logic ---

  addDiscount() {
    const group = this.fb.group({
      discount_rule_id: [''], // Link to DiscountRule if selected
      reason: ['custom', Validators.required], // Display name
      type: ['percentage', Validators.required],
      value: [0, [Validators.required, Validators.min(0)]],
      is_promo: [false], // internal flag
      promo_code: [''],
    });
    this.discountsArray.push(group);
  }

  removeDiscount(index: number) {
    this.discountsArray.removeAt(index);
  }

  // Handle dropdown changes to auto-fill values from discount rule
  onDiscountReasonChange(index: number, ruleId: string) {
    const group = this.discountsArray.at(index);

    if (!ruleId || ruleId === '') {
      // Custom discount - reset to defaults
      group.patchValue({
        discount_rule_id: '',
        reason: 'custom',
        type: 'percentage',
        value: 0,
        is_promo: false,
        promo_code: '',
      });
      return;
    }

    // Find the discount rule
    const rule = this.availableDiscountRules().find((r) => r.id === ruleId);
    if (rule) {
      group.patchValue({
        discount_rule_id: rule.id,
        reason: rule.name,
        type: rule.value_type, // 'percentage' or 'fixed'
        value: rule.value,
        is_promo: false,
        promo_code: '',
      });
    }
  }

  calculateSingleDiscountImpact(control: AbstractControl): number {
    const type = control.get('type')?.value;
    const val = control.get('value')?.value || 0;
    const base = this.member.base_premium || 0;
    return type === 'percentage' ? (base * val) / 100 : val;
  }

  getTotalDiscountImpact(): number {
    let total = 0;
    this.discountsArray.controls.forEach((c) => (total += this.calculateSingleDiscountImpact(c)));
    return total;
  }

  applyPromoCode() {
    const code = this.promoCodeControl.value?.trim().toUpperCase();
    if (!code) return;

    this.isValidatingPromo.set(true);

    // Find promo code in store
    const promoCode = this.availablePromoCodes().find((p) => p.code.toUpperCase() === code);

    if (!promoCode) {
      this.isValidatingPromo.set(false);
      this.feedback.error('Invalid or expired promo code');
      return;
    }

    // Check if already applied
    const alreadyApplied = this.discountsArray.controls.some(
      (ctrl) => ctrl.get('promo_code')?.value?.toUpperCase() === code
    );

    if (alreadyApplied) {
      this.isValidatingPromo.set(false);
      this.feedback.error('Promo code already applied');
      return;
    }

    // Get the discount rule for this promo code
    const discountRule = this.discountStore
      .discountRules()
      .find((r) => r.id === promoCode.discount_rule_id);

    if (!discountRule) {
      this.isValidatingPromo.set(false);
      this.feedback.error('Promo code discount rule not found');
      return;
    }

    // Create discount entry from promo code
    const group = this.fb.group({
      discount_rule_id: [discountRule.id],
      reason: [{ value: `Promo: ${promoCode.code}`, disabled: true }],
      type: [discountRule.value_type],
      value: [discountRule.value],
      is_promo: [true],
      promo_code: [promoCode.code],
    });

    this.discountsArray.push(group);
    this.promoCodeControl.reset();
    this.isValidatingPromo.set(false);
    this.feedback.success(`Promo code "${promoCode.code}" applied successfully!`);
  }

  // --- Exclusions Logic ---
  // Only allow selection from configured PlanExclusions

  // Add exclusion from plan exclusion (selection only)
  addExclusionFromPlan(planExclusion: PlanExclusion) {
    const exclusionGroup = this.fb.group({
      plan_exclusion_id: [planExclusion.id, Validators.required],
      exclusion_name: [{ value: planExclusion.name, disabled: true }],
      exclusion_type: [planExclusion.exclusion_type],
      benefit_id: [planExclusion.benefit_id || null],
      description: [{ value: planExclusion.description || '', disabled: true }],
      is_permanent: [planExclusion.exclusion_type === 'absolute'],
      notes: [''],
    });

    this.exclusionsArray.push(exclusionGroup);
  }

  removeExclusion(index: number) {
    this.exclusionsArray.removeAt(index);
  }

  // Check if plan exclusion is already added
  isPlanExclusionAdded(exclusionId: string): boolean {
    return this.exclusionsArray.controls.some(
      (ctrl) => ctrl.get('plan_exclusion_id')?.value === exclusionId
    );
  }

  isDiscountRuleAdded(id: string): boolean {
    return this.discountsArray.controls.some((ctrl) => ctrl.get('discount_rule_id')?.value === id);
  }

  addDiscountFromRule(rule: DiscountRule) {
    const group = this.fb.group({
      discount_rule_id: [rule.id, Validators.required],
      reason: [rule.name, Validators.required],
      type: [rule.value_type, Validators.required],
      value: [rule.value, Validators.required],
      is_promo: [false],
      promo_code: [''],
    });
    this.discountsArray.push(group);
  }

  removeDiscountByRuleId(ruleId: string) {
    const index = this.discountsArray.controls.findIndex(
      (ctrl) => ctrl.get('discount_rule_id')?.value === ruleId
    );
    if (index !== -1) {
      this.discountsArray.removeAt(index);
    }
  }

  removePromoDiscount(index: number) {
    this.discountsArray.removeAt(index);
  }

  removeExclusionByPlanId(planExclusionId: string) {
    const index = this.exclusionsArray.controls.findIndex(
      (ctrl) => ctrl.get('plan_exclusion_id')?.value === planExclusionId
    );
    if (index !== -1) {
      this.exclusionsArray.removeAt(index);
    }
  }

  // --- Actions ---

  cancel() {
    this.dialogRef.close();
  }

  save() {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.isSaving.set(true);
    const formValue = this.form.getRawValue(); // use getRawValue to include disabled fields

    // Construct Payload
    const payload = {
      decision: formValue.decision,
      notes: formValue.notes,
      // Only include modifications if decision is 'terms'
      loadings:
        formValue.decision === 'terms'
          ? formValue.loadings.map((l: any) => ({
              loading_rule_id: l.loading_rule_id,
              value: Number(l.value),
              loading_type: l.loading_type,
              duration_months: l.duration_months ? Number(l.duration_months) : null,
              notes: l.notes,
            }))
          : [],
      discounts:
        formValue.decision === 'terms'
          ? formValue.discounts.map((d: any) => ({
              discount_rule_id: d.discount_rule_id || null,
              reason: d.reason,
              type: d.type,
              value: Number(d.value),
              promo_code: d.promo_code || null,
            }))
          : [],
      exclusions:
        formValue.decision === 'terms'
          ? formValue.exclusions.map((e: any) => ({
              plan_exclusion_id: e.plan_exclusion_id || null,
              exclusion_name: e.exclusion_name,
              exclusion_type: e.exclusion_type,
              benefit_id: e.benefit_id || null,
              is_permanent: e.is_permanent ?? true,
              notes: e.notes || null,
            }))
          : [],
    };

    this.store.underwriteMember(this.data.applicationId, this.member.id, payload).subscribe({
      next: () => {
        this.isSaving.set(false);
        this.feedback.success('Underwriting decision applied successfully');
        this.dialogRef.close(true);
      },
      error: (err) => {
        this.isSaving.set(false);
        this.feedback.error(err?.error?.message || 'Failed to apply decision');
      },
    });
  }
}
