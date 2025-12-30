// libs/medical/ui/src/lib/dialogs/discount-rule-dialog/discount-rule-dialog.ts

import { Component, inject, OnInit, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogRef, MatDialogModule } from '@angular/material/dialog';
import { MatStepper, MatStepperModule } from '@angular/material/stepper';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatNativeDateModule, provideNativeDateAdapter } from '@angular/material/core';
import { MatSlideToggleModule } from '@angular/material/slide-toggle';

import {
  DiscountRule,
  SchemeListStore,
  PlanListStore,
  DISCOUNT_TYPES,
  DISCOUNT_APPLICATION,
  VALUE_TYPES,
  APPLIES_TO,
} from 'medical-data';

@Component({
  selector: 'lib-discount-rule-dialog',
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
    MatDatepickerModule,
    MatNativeDateModule,
    MatSlideToggleModule,
  ],
  providers: [provideNativeDateAdapter()],
  templateUrl: './medical-discount-dialog.html',
})
export class MedicalDiscountDialog implements OnInit {
  private readonly fb = inject(FormBuilder);
  readonly dialogRef = inject(MatDialogRef<MedicalDiscountDialog>);
  readonly data = inject<DiscountRule | null>(MAT_DIALOG_DATA);
  private readonly schemeStore = inject(SchemeListStore);
  private readonly planStore = inject(PlanListStore);

  @ViewChild('stepper') stepper!: MatStepper;

  isEditMode = false;

  // Forms
  basicForm!: FormGroup;
  scopeForm!: FormGroup;
  optionsForm!: FormGroup;

  // Constants
  readonly adjustmentTypes = DISCOUNT_TYPES;
  readonly applicationMethods = DISCOUNT_APPLICATION;
  readonly valueTypes = VALUE_TYPES;
  readonly appliesToOptions = APPLIES_TO;

  // Data
  schemes = this.schemeStore.schemes;
  filteredPlans = this.planStore.plans;

  ngOnInit() {
    this.isEditMode = !!this.data?.id;

    // Load reference data
    this.schemeStore.loadAll();
    this.planStore.loadAll();

    this.initForms();
  }

  private initForms() {
    const d = this.data;

    this.basicForm = this.fb.group({
      adjustment_type: [d?.adjustment_type || 'discount', Validators.required],
      name: [d?.name || '', Validators.required],
      description: [d?.description || ''],
      value_type: [d?.value_type || 'percentage', Validators.required],
      value: [d?.value ?? 0, [Validators.required, Validators.min(0)]],
      application_method: [d?.application_method || 'automatic', Validators.required],
    });

    this.scopeForm = this.fb.group({
      applies_to: [d?.applies_to || 'total'],
      scheme_id: [d?.scheme_id || null],
      plan_id: [d?.plan_id || null],
      min_group_size: [d?.trigger_rules?.min_group_size || null],
      min_members: [d?.trigger_rules?.min_members || null],
      min_premium: [d?.trigger_rules?.min_premium || null],
      billing_frequency: [d?.trigger_rules?.billing_frequency || null],
    });

    this.optionsForm = this.fb.group({
      priority: [d?.priority ?? 0],
      can_stack: [d?.can_stack ?? true],
      max_discount_amount: [d?.max_total_discount || null],
      usage_limit: [d?.max_uses || null],
      effective_from: [d?.effective_from ? new Date(d.effective_from) : null],
      effective_to: [d?.effective_to ? new Date(d.effective_to) : null],
      is_active: [d?.is_active ?? true],
    });
  }

  onSchemeChange(schemeId: string | null) {
    // Filter plans by scheme
    if (schemeId) {
      this.planStore.loadAll({ scheme_id: schemeId });
    } else {
      this.planStore.loadAll();
    }
    this.scopeForm.patchValue({ plan_id: null });
  }

  // Stepper navigation
  isFirstStep(): boolean {
    return this.stepper?.selectedIndex === 0;
  }

  isLastStep(): boolean {
    return this.stepper?.selectedIndex === 2;
  }

  canGoNext(): boolean {
    const idx = this.stepper?.selectedIndex ?? 0;
    if (idx === 0) return this.basicForm.valid;
    if (idx === 1) return this.scopeForm.valid;
    return true;
  }

  canSave(): boolean {
    return this.basicForm.valid && this.scopeForm.valid && this.optionsForm.valid;
  }

  nextStep() {
    if (this.canGoNext()) {
      this.stepper.next();
    }
  }

  prevStep() {
    this.stepper.previous();
  }

  save() {
    if (!this.canSave()) return;

    const basic = this.basicForm.value;
    const scope = this.scopeForm.value;
    const options = this.optionsForm.value;

    // Build trigger_rules
    const triggerRules: any = {};
    if (scope.min_group_size) triggerRules.min_group_size = scope.min_group_size;
    if (scope.min_members) triggerRules.min_members = scope.min_members;
    if (scope.min_premium) triggerRules.min_premium = scope.min_premium;
    if (scope.billing_frequency) triggerRules.billing_frequency = scope.billing_frequency;

    const result: Partial<DiscountRule> = {
      ...basic,
      applies_to: scope.applies_to,
      scheme_id: scope.scheme_id,
      plan_id: scope.plan_id,
      trigger_rules: Object.keys(triggerRules).length > 0 ? triggerRules : null,
      priority: options.priority,
      can_stack: options.can_stack,
      max_total_discount: options.max_discount_amount,
      max_uses: options.usage_limit,
      effective_from: options.effective_from?.toISOString()?.split('T')[0],
      effective_to: options.effective_to?.toISOString()?.split('T')[0],
      is_active: options.is_active,
    };

    this.dialogRef.close(result);
  }
}
