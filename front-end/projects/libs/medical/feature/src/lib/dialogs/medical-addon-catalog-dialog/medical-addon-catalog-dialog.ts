// libs/medical/ui/src/lib/dialogs/addon-dialog/addon-dialog.ts

import { Component, inject, OnInit, signal, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogRef, MatDialogModule } from '@angular/material/dialog';
import { MatStepper, MatStepperModule } from '@angular/material/stepper';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatSlideToggleModule } from '@angular/material/slide-toggle';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatNativeDateModule, provideNativeDateAdapter } from '@angular/material/core';
import { STEPPER_GLOBAL_OPTIONS } from '@angular/cdk/stepper';

import { Addon, ADDON_TYPES, ADDON_PRICING_TYPES, getLabelByValue } from 'medical-data';

@Component({
  selector: 'lib-medical-addon-dialog',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    MatDialogModule,
    MatStepperModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatSlideToggleModule,
    MatButtonModule,
    MatIconModule,
    MatDatepickerModule,
    MatNativeDateModule,
  ],
  providers: [
    {
      provide: STEPPER_GLOBAL_OPTIONS,
      useValue: { showError: true },
    },
    provideNativeDateAdapter(),
  ],
  templateUrl: './medical-addon-catalog-dialog.html',
})
export class MedicalAddonCatalogDialog implements OnInit {
  private readonly fb = inject(FormBuilder);
  readonly dialogRef = inject(MatDialogRef<MedicalAddonCatalogDialog>);
  readonly data = inject<Addon | null>(MAT_DIALOG_DATA);

  @ViewChild('stepper') stepper!: MatStepper;

  // Constants
  readonly addonTypes = ADDON_TYPES;
  readonly pricingTypes = ADDON_PRICING_TYPES;

  currentStep = signal(0);
  isEditMode = false;

  // Forms
  basicForm!: FormGroup;
  pricingForm!: FormGroup;
  settingsForm!: FormGroup;

  ngOnInit() {
    this.isEditMode = !!this.data?.id;
    this.initForms();
  }

  private initForms() {
    // Step 1: Basic Info
    this.basicForm = this.fb.group({
      name: [this.data?.name || '', Validators.required],
      // code: [this.data?.code || ''],
      addon_type: [this.data?.addon_type || 'rider', Validators.required],
      description: [this.data?.description || ''],
    });

    // Step 2: Pricing (for new addon, just collect default pricing type)
    this.pricingForm = this.fb.group({
      default_pricing_type: ['fixed'],
      default_amount: [null, [Validators.min(0)]],
      default_percentage: [null, [Validators.min(0), Validators.max(100)]],
    });

    // Step 3: Settings
    this.settingsForm = this.fb.group({
      effective_from: [this.data?.effective_from ? new Date(this.data.effective_from) : null],
      effective_to: [this.data?.effective_to ? new Date(this.data.effective_to) : null],
      sort_order: [this.data?.sort_order || 0],
      is_active: [this.data?.is_active ?? true],
    });
  }

  onStepChange(index: number) {
    this.currentStep.set(index);
  }

  // Navigation
  nextStep() {
    if (this.stepper) {
      this.stepper.next();
    }
  }

  prevStep() {
    if (this.stepper) {
      this.stepper.previous();
    }
  }

  get canGoNext(): boolean {
    const step = this.currentStep();
    if (step === 0) return this.basicForm.valid;
    if (step === 1) return this.pricingForm.valid;
    return true;
  }

  get isLastStep(): boolean {
    return this.currentStep() === 2;
  }

  get isFirstStep(): boolean {
    return this.currentStep() === 0;
  }

  get isFormValid(): boolean {
    return this.basicForm.valid && this.pricingForm.valid && this.settingsForm.valid;
  }

  // Display helpers
  getAddonTypeLabel(value: string): string {
    return getLabelByValue(ADDON_TYPES, value);
  }

  getAddonTypeIcon(type: string): string {
    const icons: Record<string, string> = {
      rider: 'add_circle',
      top_up: 'trending_up',
      standalone: 'extension',
    };
    return icons[type] || 'extension';
  }

  getPricingTypeLabel(value: string): string {
    return getLabelByValue(ADDON_PRICING_TYPES, value);
  }

  save() {
    if (!this.isFormValid) return;

    const settingsValues = this.settingsForm.value;

    const result: Partial<Addon> = {
      ...this.basicForm.value,
      effective_from: settingsValues.effective_from?.toISOString().split('T')[0] || null,
      effective_to: settingsValues.effective_to?.toISOString().split('T')[0] || null,
      sort_order: settingsValues.sort_order,
      is_active: settingsValues.is_active,
    };

    this.dialogRef.close(result);
  }
}
