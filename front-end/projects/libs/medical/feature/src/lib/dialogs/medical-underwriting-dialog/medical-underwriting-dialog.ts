// libs/medical/ui/src/lib/dialogs/underwriting-dialog/underwriting-dialog.component.ts

import { Component, computed, inject, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, FormArray, ReactiveFormsModule, Validators } from '@angular/forms';

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
import { provideNativeDateAdapter } from '@angular/material/core';

import {
  ApplicationStore,
  LoadingRuleStore,
  ApplicationMember,
  LoadingRule,
  LOADING_TYPES,
  DURATION_TYPES,
  getLabelByValue,
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
  private readonly feedback = inject(FeedbackService);

  readonly loadingTypes = LOADING_TYPES;
  readonly durationTypes = DURATION_TYPES;

  readonly isSaving = signal(false);
  readonly loadingRules = computed(() => this.loadingRuleStore.loadingRules());

  form!: FormGroup;

  get member(): ApplicationMember {
    return this.data.member;
  }

  get memberName(): string {
    return this.member.full_name || `${this.member.first_name} ${this.member.last_name}`;
  }

  get loadingsArray(): FormArray {
    return this.form.get('loadings') as FormArray;
  }

  get exclusionsArray(): FormArray {
    return this.form.get('exclusions') as FormArray;
  }

  ngOnInit() {
    this.buildForm();
    this.loadingRuleStore.loadAll();
  }

  private buildForm() {
    this.form = this.fb.group({
      decision: ['approve', Validators.required],
      loadings: this.fb.array([]),
      exclusions: this.fb.array([]),
      notes: [''],
    });
  }

  addLoading() {
    const loadingGroup = this.fb.group({
      loading_rule_id: [''],
      condition_name: ['', Validators.required],
      icd10_code: [''],
      loading_type: ['percentage', Validators.required],
      value: [0, [Validators.required, Validators.min(0)]],
      duration_type: ['permanent'],
      duration_months: [null],
      notes: [''],
    });

    this.loadingsArray.push(loadingGroup);
  }

  removeLoading(index: number) {
    this.loadingsArray.removeAt(index);
  }

  addExclusion() {
    const exclusionGroup = this.fb.group({
      exclusion_name: ['', Validators.required],
      exclusion_type: ['condition'],
      benefit_id: [''],
      icd10_codes: [''],
      description: [''],
      is_permanent: [true],
      end_date: [null],
      notes: [''],
    });

    this.exclusionsArray.push(exclusionGroup);
  }

  removeExclusion(index: number) {
    this.exclusionsArray.removeAt(index);
  }

  formatCurrency(amount: number): string {
    return new Intl.NumberFormat('en-ZM', {
      style: 'currency',
      currency: 'ZMW',
      minimumFractionDigits: 2,
    }).format(amount || 0);
  }

  cancel() {
    this.dialogRef.close();
  }

  save() {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.isSaving.set(true);

    const formValue = this.form.value;

    const payload = {
      decision: formValue.decision,
      notes: formValue.notes,
      loadings:
        formValue.decision === 'terms'
          ? formValue.loadings.map((l: any) => ({
              ...l,
              value: Number(l.value),
              duration_months: l.duration_months ? Number(l.duration_months) : null,
            }))
          : [],
      exclusions:
        formValue.decision === 'terms'
          ? formValue.exclusions.map((e: any) => ({
              ...e,
              end_date: e.end_date ? e.end_date.toISOString().split('T')[0] : null,
              icd10_codes: e.icd10_codes
                ? e.icd10_codes.split(',').map((c: string) => c.trim())
                : [],
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
        this.feedback.error(err?.error?.message || 'Failed to apply underwriting decision');
      },
    });
  }
}
