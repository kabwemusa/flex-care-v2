// libs/medical/ui/src/lib/dialogs/medical-scheme-dialog/medical-scheme-dialog.ts

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
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatNativeDateModule, provideNativeDateAdapter } from '@angular/material/core';
import { MatChipsModule } from '@angular/material/chips';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { STEPPER_GLOBAL_OPTIONS } from '@angular/cdk/stepper';

import { MedicalScheme, MARKET_SEGMENTS } from 'medical-data';

interface MarketSegment {
  value: string;
  label: string;
  icon: string;
}

@Component({
  selector: 'lib-medical-scheme-dialog',
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
    MatTooltipModule,
    MatDatepickerModule,
    MatNativeDateModule,
    MatChipsModule,
    MatCheckboxModule,
  ],
  providers: [
    {
      provide: STEPPER_GLOBAL_OPTIONS,
      useValue: { showError: true },
    },
    provideNativeDateAdapter(),
  ],
  templateUrl: './medical-scheme-dialog.html',
})
export class MedicalSchemeDialog implements OnInit {
  private readonly fb = inject(FormBuilder);
  readonly dialogRef = inject(MatDialogRef<MedicalSchemeDialog>);
  readonly data = inject<MedicalScheme | null>(MAT_DIALOG_DATA);

  @ViewChild('stepper') stepper!: MatStepper;

  // Constants
  readonly marketSegments: ReadonlyArray<MarketSegment> = MARKET_SEGMENTS;
  readonly requiredDocumentOptions = [
    { value: 'national_id', label: 'National ID' },
    { value: 'passport', label: 'Passport' },
    { value: 'birth_certificate', label: 'Birth Certificate' },
    { value: 'marriage_certificate', label: 'Marriage Certificate' },
    { value: 'medical_declaration', label: 'Medical Declaration' },
    { value: 'company_registration', label: 'Company Registration' },
  ];

  isEditMode = false;
  currentStep = signal(0);

  // Form Groups
  basicInfoForm!: FormGroup;
  eligibilityForm!: FormGroup;
  underwritingForm!: FormGroup;

  ngOnInit() {
    this.isEditMode = !!this.data;
    this.initForms();
  }

  private initForms() {
    // Handle legacy single segment or new multi-segment
    const existingSegments = this.data?.market_segment
      ? this.data.market_segment
      : this.data?.market_segment
      ? [this.data.market_segment]
      : [];

    // Step 1: Basic Information
    this.basicInfoForm = this.fb.group({
      name: [this.data?.name || '', [Validators.required, Validators.minLength(3)]],
      market_segments: [existingSegments, [Validators.required, Validators.minLength(1)]],
      description: [this.data?.description || ''],
      effective_from: [
        this.data?.effective_from ? new Date(this.data.effective_from) : new Date(),
        Validators.required,
      ],
      effective_to: [this.data?.effective_to ? new Date(this.data.effective_to) : null],
      is_active: [this.data?.is_active ?? false],
    });

    // Step 2: Eligibility Rules
    const eligibility = this.data?.eligibility_rules || {};
    this.eligibilityForm = this.fb.group({
      min_age: [eligibility.min_age ?? 0, [Validators.min(0), Validators.max(100)]],
      max_age: [eligibility.max_age ?? 99, [Validators.min(0), Validators.max(120)]],
      min_group_size: [eligibility.min_group_size ?? null, [Validators.min(1)]],
      max_group_size: [eligibility.max_group_size ?? null, [Validators.min(1)]],
      required_documents: [eligibility.required_documents || []],
    });

    // Step 3: Underwriting Rules
    const underwriting = this.data?.underwriting_rules || {};
    this.underwritingForm = this.fb.group({
      require_medical_exam: [underwriting.require_medical_exam ?? false],
      medical_exam_age_threshold: [
        underwriting.medical_exam_age_threshold ?? 50,
        [Validators.min(0)],
      ],
      require_declaration: [underwriting.require_declaration ?? true],
      auto_accept_age_limit: [underwriting.auto_accept_age_limit ?? 45, [Validators.min(0)]],
    });
  }

  onStepChange(index: number) {
    this.currentStep.set(index);
  }

  // Get selected segments with their full details for display
  getSelectedSegments(): MarketSegment[] {
    const selectedValues: string[] = this.basicInfoForm.get('market_segments')?.value || [];
    return this.marketSegments.filter((s) => selectedValues.includes(s.value));
  }

  // Check if any group-based segment is selected (for showing group size fields)
  hasGroupSegment(): boolean {
    const selectedValues: string[] = this.basicInfoForm.get('market_segments')?.value || [];
    return selectedValues.includes('corporate') || selectedValues.includes('sme');
  }

  getSegmentIcon(value: string): string {
    return MARKET_SEGMENTS.find((s) => s.value === value)?.icon || 'category';
  }

  // Step Navigation
  nextStep() {
    if (this.canProceedToNextStep()) {
      this.stepper.next();
    }
  }

  previousStep() {
    this.stepper.previous();
  }

  canProceedToNextStep(): boolean {
    switch (this.currentStep()) {
      case 0:
        return this.basicInfoForm.valid;
      case 1:
        return this.eligibilityForm.valid;
      case 2:
        return this.underwritingForm.valid;
      default:
        return false;
    }
  }

  get isFormValid(): boolean {
    return this.basicInfoForm.valid && this.eligibilityForm.valid && this.underwritingForm.valid;
  }

  save() {
    if (!this.isFormValid) return;

    // Format dates
    const basicInfo = this.basicInfoForm.value;
    const effectiveFrom =
      basicInfo.effective_from instanceof Date
        ? basicInfo.effective_from.toISOString().split('T')[0]
        : basicInfo.effective_from;
    const effectiveTo =
      basicInfo.effective_to instanceof Date
        ? basicInfo.effective_to.toISOString().split('T')[0]
        : basicInfo.effective_to;

    // Build eligibility rules (only include non-empty values)
    const eligibility = this.eligibilityForm.value;
    const eligibilityRules: Record<string, unknown> = {};
    if (eligibility.min_age > 0) eligibilityRules['min_age'] = eligibility.min_age;
    if (eligibility.max_age < 99) eligibilityRules['max_age'] = eligibility.max_age;
    if (eligibility.min_group_size) eligibilityRules['min_group_size'] = eligibility.min_group_size;
    if (eligibility.max_group_size) eligibilityRules['max_group_size'] = eligibility.max_group_size;
    if (eligibility.required_documents?.length > 0) {
      eligibilityRules['required_documents'] = eligibility.required_documents;
    }

    // Build underwriting rules
    const underwriting = this.underwritingForm.value;
    const underwritingRules: Record<string, unknown> = {
      require_medical_exam: underwriting.require_medical_exam,
      require_declaration: underwriting.require_declaration,
    };
    if (underwriting.require_medical_exam) {
      underwritingRules['medical_exam_age_threshold'] = underwriting.medical_exam_age_threshold;
    }
    if (underwriting.auto_accept_age_limit) {
      underwritingRules['auto_accept_age_limit'] = underwriting.auto_accept_age_limit;
    }

    const result: Partial<MedicalScheme> = {
      name: basicInfo.name,
      // market_segment: basicInfo.market_segments,
      // Keep backward compatibility - use first segment as primary
      market_segment: basicInfo.market_segments[0],
      description: basicInfo.description || null,
      effective_from: effectiveFrom,
      effective_to: effectiveTo || null,
      is_active: basicInfo.is_active,
      eligibility_rules:
        Object.keys(eligibilityRules).length > 0
          ? (eligibilityRules as MedicalScheme['eligibility_rules'])
          : undefined,
      underwriting_rules: underwritingRules as MedicalScheme['underwriting_rules'],
    };

    this.dialogRef.close(result);
  }
}
