// libs/medical/ui/src/lib/dialogs/rate-card-entry-dialog/rate-card-entry-dialog.ts

import { Component, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogRef, MatDialogModule } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';

import { RateCardEntry } from 'medical-data';

@Component({
  selector: 'lib-rate-card-entry-dialog',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    MatDialogModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatButtonModule,
    MatIconModule,
  ],
  template: `
    <div class="flex max-h-[90vh] w-full flex-col overflow-hidden bg-white sm:w-[500px]">
      <!-- Header -->
      <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
        <div class="flex items-center gap-3">
          <div
            class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 text-blue-600"
          >
            <mat-icon fontSet="material-symbols-rounded">person</mat-icon>
          </div>
          <div>
            <h2 class="text-lg font-semibold tracking-tight text-slate-900">
              {{ isEditMode ? 'Edit Entry' : 'Add Entry' }}
            </h2>
            <p class="text-xs text-slate-500">Age-based premium entry</p>
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
        <form [formGroup]="form" class="space-y-5">
          <!-- Age Range -->
          <div class="grid grid-cols-2 gap-4">
            <div class="flex flex-col gap-1.5">
              <label class="text-sm font-medium text-slate-700">
                Min Age <span class="text-red-500">*</span>
              </label>
              <mat-form-field appearance="outline" class="w-full" subscriptSizing="dynamic">
                <input matInput type="number" formControlName="min_age" min="0" max="150" />
                <span matTextSuffix class="text-slate-400">years</span>
                @if (form.get('min_age')?.hasError('required')) {
                <mat-error>Required</mat-error>
                }
              </mat-form-field>
            </div>

            <div class="flex flex-col gap-1.5">
              <label class="text-sm font-medium text-slate-700">
                Max Age <span class="text-red-500">*</span>
              </label>
              <mat-form-field appearance="outline" class="w-full" subscriptSizing="dynamic">
                <input matInput type="number" formControlName="max_age" min="0" max="150" />
                <span matTextSuffix class="text-slate-400">years</span>
                @if (form.get('max_age')?.hasError('required')) {
                <mat-error>Required</mat-error>
                }
              </mat-form-field>
            </div>
          </div>

          <p class="text-xs text-slate-400">Use 150 or 999 for "and above" (e.g., 65+)</p>

          <!-- Gender -->
          <div class="flex flex-col gap-1.5">
            <label class="text-sm font-medium text-slate-700">Gender</label>
            <mat-form-field appearance="outline" class="w-full" subscriptSizing="dynamic">
              <mat-select formControlName="gender">
                <mat-option [value]="null">All (Unisex)</mat-option>
                <mat-option value="M">Male</mat-option>
                <mat-option value="F">Female</mat-option>
              </mat-select>
            </mat-form-field>
            <p class="text-xs text-slate-400">Leave as "All" for gender-neutral pricing</p>
          </div>

          <!-- Region -->
          <div class="flex flex-col gap-1.5">
            <label class="text-sm font-medium text-slate-700">Region Code</label>
            <mat-form-field appearance="outline" class="w-full" subscriptSizing="dynamic">
              <input matInput formControlName="region_code" placeholder="e.g., LSK, KIT" />
            </mat-form-field>
            <p class="text-xs text-slate-400">Leave empty for national/all regions</p>
          </div>

          <!-- Base Premium -->
          <div class="flex flex-col gap-1.5">
            <label class="text-sm font-medium text-slate-700">
              Base Premium <span class="text-red-500">*</span>
            </label>
            <mat-form-field appearance="outline" class="w-full" subscriptSizing="dynamic">
              <span matTextPrefix class="text-slate-400 mr-1">ZMW</span>
              <input matInput type="number" formControlName="base_premium" min="0" step="0.01" />
              @if (form.get('base_premium')?.hasError('required')) {
              <mat-error>Required</mat-error>
              } @if (form.get('base_premium')?.hasError('min')) {
              <mat-error>Must be positive</mat-error>
              }
            </mat-form-field>
          </div>

          <!-- Preview -->
          <div class="rounded-lg bg-slate-50 border border-slate-200 p-4">
            <p class="text-xs text-slate-500 mb-2">Preview</p>
            <p class="text-sm font-medium text-slate-900">
              Age {{ form.get('min_age')?.value || 0 }} - {{ form.get('max_age')?.value || 0 }} @if
              (form.get('gender')?.value) { ({{
                form.get('gender')?.value === 'M' ? 'Male' : 'Female'
              }}) } @if (form.get('region_code')?.value) { [{{ form.get('region_code')?.value }}] }
              : ZMW {{ form.get('base_premium')?.value | number : '1.2-2' }}
            </p>
          </div>
        </form>
      </div>

      <!-- Footer -->
      <div
        class="flex items-center justify-end gap-3 border-t border-slate-100 bg-slate-50 px-6 py-4"
      >
        <button mat-button (click)="dialogRef.close()" class="!text-slate-600">Cancel</button>
        <button mat-flat-button color="primary" [disabled]="!form.valid" (click)="save()">
          {{ isEditMode ? 'Update Entry' : 'Add Entry' }}
        </button>
      </div>
    </div>
  `,
})
export class RateCardEntryDialog implements OnInit {
  private readonly fb = inject(FormBuilder);
  readonly dialogRef = inject(MatDialogRef<RateCardEntryDialog>);
  readonly data = inject<RateCardEntry | null>(MAT_DIALOG_DATA);

  form!: FormGroup;
  isEditMode = false;

  ngOnInit() {
    this.isEditMode = !!this.data?.id;

    this.form = this.fb.group({
      min_age: [
        this.data?.min_age ?? 0,
        [Validators.required, Validators.min(0), Validators.max(150)],
      ],
      max_age: [
        this.data?.max_age ?? 0,
        [Validators.required, Validators.min(0), Validators.max(999)],
      ],
      gender: [this.data?.gender || null],
      region_code: [this.data?.region_code || ''],
      base_premium: [this.data?.base_premium ?? 0, [Validators.required, Validators.min(0)]],
    });
  }

  save() {
    if (!this.form.valid) return;

    const value = this.form.value;
    // Clean up null/empty values
    if (!value.gender) delete value.gender;
    if (!value.region_code) delete value.region_code;

    this.dialogRef.close(value);
  }
}
