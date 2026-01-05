// libs/medical/feature/src/lib/dialogs/medical-plan-exclusion-dialog/medical-plan-exclusion-dialog.ts

import { Component, inject, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogRef, MatDialogModule } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatSlideToggleModule } from '@angular/material/slide-toggle';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { HttpClient } from '@angular/common/http';

import { PlanExclusion, PLAN_EXCLUSION_TYPES, Benefit } from 'medical-data';

@Component({
  selector: 'lib-medical-plan-exclusion-dialog',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    MatDialogModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatSlideToggleModule,
    MatButtonModule,
    MatIconModule,
  ],
  template: `
    <div class="flex max-h-[90vh] w-full flex-col overflow-hidden bg-white sm:w-[600px]">
      <!-- Header -->
      <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
        <div class="flex items-center gap-3">
          <div
            class="flex h-10 w-10 items-center justify-center rounded-full bg-red-100 text-red-600"
          >
            <mat-icon fontSet="material-symbols-rounded">
              {{ isEditMode ? 'edit' : 'block' }}
            </mat-icon>
          </div>
          <div>
            <h2 class="text-lg font-semibold tracking-tight text-slate-900">
              {{ isEditMode ? 'Edit Exclusion' : 'New Exclusion' }}
            </h2>
            <p class="text-xs text-slate-500">
              {{ isEditMode ? 'Update exclusion details' : 'Define what is not covered by this plan' }}
            </p>
          </div>
        </div>

        <button
          mat-icon-button
          (click)="dialogRef.close()"
          class="!text-slate-400 hover:!text-slate-700"
        >
          <mat-icon fontSet="material-symbols-rounded">close</mat-icon>
        </button>
      </div>

      <!-- Content -->
      <div class="flex-1 overflow-y-auto p-6">
        <form [formGroup]="form" class="flex flex-col gap-5">
          <!-- Exclusion Name -->
          <div class="flex flex-col gap-1.5">
            <label class="text-sm font-medium text-slate-700">
              Exclusion Name <span class="text-red-500">*</span>
            </label>
            <mat-form-field appearance="outline" class="w-full" subscriptSizing="dynamic">
              <input matInput formControlName="name" placeholder="e.g. Cosmetic Surgery" />
              <mat-icon matSuffix fontSet="material-symbols-rounded" class="!text-slate-400"
                >block</mat-icon
              >
              @if (form.get('name')?.hasError('required')) {
              <mat-error>Exclusion name is required</mat-error>
              }
            </mat-form-field>
          </div>

          <!-- Exclusion Type -->
          <div class="flex flex-col gap-1.5">
            <label class="text-sm font-medium text-slate-700">
              Exclusion Type <span class="text-red-500">*</span>
            </label>
            <mat-form-field appearance="outline" class="w-full" subscriptSizing="dynamic">
              <mat-select formControlName="exclusion_type" placeholder="Select type">
                @for (type of exclusionTypes; track type.value) {
                <mat-option [value]="type.value">
                  <div class="flex flex-col">
                    <span class="font-medium">{{ type.label }}</span>
                    <span class="text-xs text-slate-500">{{ type.description }}</span>
                  </div>
                </mat-option>
                }
              </mat-select>
              @if (form.get('exclusion_type')?.hasError('required')) {
              <mat-error>Exclusion type is required</mat-error>
              }
            </mat-form-field>
          </div>

          <!-- Benefit (Optional - for benefit-specific exclusions) -->
          <div class="flex flex-col gap-1.5">
            <label class="text-sm font-medium text-slate-700">
              Link to Benefit <span class="text-xs text-slate-500">(Optional)</span>
            </label>
            <mat-form-field appearance="outline" class="w-full" subscriptSizing="dynamic">
              <mat-select formControlName="benefit_id" placeholder="Select benefit or leave blank for general exclusion">
                <mat-option [value]="null">
                  <span class="text-slate-500 italic">General exclusion (applies to all benefits)</span>
                </mat-option>
                @for (benefit of benefits(); track benefit.id) {
                <mat-option [value]="benefit.id">
                  {{ benefit.name }}
                </mat-option>
                }
              </mat-select>
            </mat-form-field>
            <p class="text-xs text-slate-400">
              Leave blank for general plan exclusion, or select a specific benefit
            </p>
          </div>

          <!-- Exclusion Period (for time-limited exclusions) -->
          @if (form.get('exclusion_type')?.value === 'time_limited') {
          <div class="flex flex-col gap-1.5">
            <label class="text-sm font-medium text-slate-700">
              Exclusion Period <span class="text-red-500">*</span>
            </label>
            <mat-form-field appearance="outline" class="w-full" subscriptSizing="dynamic">
              <input
                matInput
                type="number"
                formControlName="exclusion_period_days"
                placeholder="Number of days"
                min="1"
              />
              <span matTextSuffix class="text-slate-500 mr-2">days</span>
              @if (form.get('exclusion_period_days')?.hasError('required')) {
              <mat-error>Exclusion period is required for time-limited exclusions</mat-error>
              }
              @if (form.get('exclusion_period_days')?.hasError('min')) {
              <mat-error>Period must be at least 1 day</mat-error>
              }
            </mat-form-field>
            <p class="text-xs text-slate-400">
              After this period, the exclusion will no longer apply
            </p>
          </div>
          }

          <!-- Description -->
          <div class="flex flex-col gap-1.5">
            <label class="text-sm font-medium text-slate-700">Description</label>
            <mat-form-field appearance="outline" class="w-full" subscriptSizing="dynamic">
              <textarea
                matInput
                formControlName="description"
                rows="3"
                placeholder="Detailed description of what is excluded and why..."
                class="!resize-none"
              ></textarea>
            </mat-form-field>
          </div>

          <!-- Sort Order -->
          <div class="flex flex-col gap-1.5">
            <label class="text-sm font-medium text-slate-700">Sort Order</label>
            <mat-form-field appearance="outline" class="w-full" subscriptSizing="dynamic">
              <input matInput type="number" formControlName="sort_order" min="0" />
            </mat-form-field>
            <p class="text-xs text-slate-400">Lower numbers appear first in exclusions list</p>
          </div>

          <!-- Active Status -->
          <div
            class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50/50 p-4"
          >
            <div class="flex flex-col">
              <span class="text-sm font-semibold text-slate-900">Active Status</span>
              <span class="text-xs text-slate-500">Enable to enforce this exclusion</span>
            </div>
            <mat-slide-toggle formControlName="is_active" color="primary"></mat-slide-toggle>
          </div>
        </form>
      </div>

      <!-- Footer -->
      <div
        class="flex items-center justify-end gap-3 border-t border-slate-100 bg-slate-50 px-6 py-4"
      >
        <button mat-button (click)="dialogRef.close()" class="!text-slate-600 hover:!bg-slate-200">
          Cancel
        </button>
        <button
          mat-flat-button
          color="primary"
          [disabled]="form.invalid || form.pristine"
          (click)="save()"
          class="!px-6 !rounded-lg"
        >
          {{ isEditMode ? 'Save Changes' : 'Add Exclusion' }}
        </button>
      </div>
    </div>
  `,
})
export class MedicalPlanExclusionDialog implements OnInit {
  private readonly fb = inject(FormBuilder);
  private readonly http = inject(HttpClient);
  readonly dialogRef = inject(MatDialogRef<MedicalPlanExclusionDialog>);
  readonly data = inject<{ exclusion?: PlanExclusion; planId: string } | null>(MAT_DIALOG_DATA);

  form!: FormGroup;
  isEditMode = false;
  exclusionTypes = PLAN_EXCLUSION_TYPES;
  benefits = signal<Benefit[]>([]);

  ngOnInit() {
    this.isEditMode = !!this.data?.exclusion;
    this.loadBenefits();
    this.initForm();
  }

  private initForm() {
    const exclusion = this.data?.exclusion;

    this.form = this.fb.group({
      name: [exclusion?.name || '', [Validators.required, Validators.minLength(2)]],
      exclusion_type: [exclusion?.exclusion_type || '', Validators.required],
      benefit_id: [exclusion?.benefit_id || null],
      exclusion_period_days: [exclusion?.exclusion_period_days || null],
      description: [exclusion?.description || ''],
      sort_order: [exclusion?.sort_order ?? 0, [Validators.min(0)]],
      is_active: [exclusion?.is_active ?? true],
    });

    // Add conditional validation for exclusion_period_days
    this.form.get('exclusion_type')?.valueChanges.subscribe((type) => {
      const periodControl = this.form.get('exclusion_period_days');
      if (type === 'time_limited') {
        periodControl?.setValidators([Validators.required, Validators.min(1)]);
      } else {
        periodControl?.clearValidators();
        periodControl?.setValue(null);
      }
      periodControl?.updateValueAndValidity();
    });
  }

  private loadBenefits() {
    // Load benefits for the dropdown
    this.http.get<any>('/api/v1/medical/benefits?per_page=1000').subscribe({
      next: (res) => {
        this.benefits.set(res.data || []);
      },
    });
  }

  save() {
    if (this.form.invalid) return;
    this.dialogRef.close(this.form.value);
  }
}
