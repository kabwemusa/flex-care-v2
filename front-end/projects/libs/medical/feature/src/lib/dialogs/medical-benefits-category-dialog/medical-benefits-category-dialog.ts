// libs/medical/ui/src/lib/dialogs/benefit-category-dialog/benefit-category-dialog.component.ts

import { Component, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogRef, MatDialogModule } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatSlideToggleModule } from '@angular/material/slide-toggle';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';

import { BenefitCategory } from 'medical-data';

@Component({
  selector: 'lib-benefit-category-dialog',
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
    <div class="flex max-h-[90vh] w-full flex-col overflow-hidden bg-white sm:w-[500px]">
      <!-- Header -->
      <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
        <div class="flex items-center gap-3">
          <div
            class="flex h-10 w-10 items-center justify-center rounded-full bg-purple-100 text-purple-600"
          >
            <mat-icon fontSet="material-symbols-rounded">
              {{ isEditMode ? 'edit' : 'create_new_folder' }}
            </mat-icon>
          </div>
          <div>
            <h2 class="text-lg font-semibold tracking-tight text-slate-900">
              {{ isEditMode ? 'Edit Category' : 'New Category' }}
            </h2>
            <p class="text-xs text-slate-500">
              {{ isEditMode ? 'Update category details' : 'Create a new benefit category' }}
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
          <!-- Category Name -->
          <div class="flex flex-col gap-1.5">
            <label class="text-sm font-medium text-slate-700">
              Category Name <span class="text-red-500">*</span>
            </label>
            <mat-form-field appearance="outline" class="w-full" subscriptSizing="dynamic">
              <input matInput formControlName="name" placeholder="e.g. In-Patient Services" />
              <mat-icon matSuffix fontSet="material-symbols-rounded" class="!text-slate-400"
                >folder</mat-icon
              >
              @if (form.get('name')?.hasError('required')) {
              <mat-error>Category name is required</mat-error>
              }
            </mat-form-field>
          </div>

          <!-- Description -->
          <div class="flex flex-col gap-1.5">
            <label class="text-sm font-medium text-slate-700">Description</label>
            <mat-form-field appearance="outline" class="w-full" subscriptSizing="dynamic">
              <textarea
                matInput
                formControlName="description"
                rows="3"
                placeholder="Brief description of this category..."
                class="!resize-none"
              ></textarea>
            </mat-form-field>
          </div>

          <!-- Icon & Color -->
          <div class="grid grid-cols-2 gap-4">
            <div class="flex flex-col gap-1.5">
              <label class="text-sm font-medium text-slate-700">Icon</label>
              <mat-form-field appearance="outline" class="w-full" subscriptSizing="dynamic">
                <mat-select formControlName="icon" placeholder="Select icon">
                  @for (icon of iconOptions; track icon.value) {
                  <mat-option [value]="icon.value">
                    <div class="flex items-center gap-2">
                      <mat-icon fontSet="material-symbols-rounded">{{ icon.value }}</mat-icon>
                      <span>{{ icon.label }}</span>
                    </div>
                  </mat-option>
                  }
                </mat-select>
              </mat-form-field>
            </div>

            <div class="flex flex-col gap-1.5">
              <label class="text-sm font-medium text-slate-700">Color</label>
              <mat-form-field appearance="outline" class="w-full" subscriptSizing="dynamic">
                <mat-select formControlName="color" placeholder="Select color">
                  @for (color of colorOptions; track color.value) {
                  <mat-option [value]="color.value">
                    <div class="flex items-center gap-2">
                      <span
                        class="h-4 w-4 rounded-full"
                        [style.background-color]="color.value"
                      ></span>
                      <span>{{ color.label }}</span>
                    </div>
                  </mat-option>
                  }
                </mat-select>
              </mat-form-field>
            </div>
          </div>

          <!-- Sort Order -->
          <div class="flex flex-col gap-1.5">
            <label class="text-sm font-medium text-slate-700">Sort Order</label>
            <mat-form-field appearance="outline" class="w-full" subscriptSizing="dynamic">
              <input matInput type="number" formControlName="sort_order" min="0" />
            </mat-form-field>
            <p class="text-xs text-slate-400">Lower numbers appear first</p>
          </div>

          <!-- Active Status -->
          <div
            class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50/50 p-4"
          >
            <div class="flex flex-col">
              <span class="text-sm font-semibold text-slate-900">Active Status</span>
              <span class="text-xs text-slate-500">Enable to show in benefit selection</span>
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
          {{ isEditMode ? 'Save Changes' : 'Create Category' }}
        </button>
      </div>
    </div>
  `,
})
export class MedicalBenefitsCategoryDialog implements OnInit {
  private readonly fb = inject(FormBuilder);
  readonly dialogRef = inject(MatDialogRef<MedicalBenefitsCategoryDialog>);
  readonly data = inject<BenefitCategory | null>(MAT_DIALOG_DATA);

  form!: FormGroup;
  isEditMode = false;

  iconOptions = [
    { value: 'local_hospital', label: 'Hospital' },
    { value: 'medical_services', label: 'Medical' },
    { value: 'healing', label: 'Healing' },
    { value: 'dentistry', label: 'Dental' },
    { value: 'visibility', label: 'Optical' },
    { value: 'pregnant_woman', label: 'Maternity' },
    { value: 'medication', label: 'Medication' },
    { value: 'spa', label: 'Wellness' },
    { value: 'emergency', label: 'Emergency' },
    { value: 'psychology', label: 'Mental Health' },
    { value: 'biotech', label: 'Lab/Diagnostics' },
    { value: 'vaccines', label: 'Vaccines' },
  ];

  colorOptions = [
    { value: '#3b82f6', label: 'Blue' },
    { value: '#8b5cf6', label: 'Purple' },
    { value: '#10b981', label: 'Green' },
    { value: '#f59e0b', label: 'Amber' },
    { value: '#ef4444', label: 'Red' },
    { value: '#ec4899', label: 'Pink' },
    { value: '#06b6d4', label: 'Cyan' },
    { value: '#6366f1', label: 'Indigo' },
  ];

  ngOnInit() {
    this.isEditMode = !!this.data;
    this.form = this.fb.group({
      name: [this.data?.name || '', [Validators.required, Validators.minLength(2)]],
      description: [this.data?.description || ''],
      icon: [this.data?.icon || 'folder'],
      color: [this.data?.color || '#8b5cf6'],
      sort_order: [this.data?.sort_order ?? 0, [Validators.min(0)]],
      is_active: [this.data?.is_active ?? true],
    });
  }

  save() {
    if (this.form.invalid) return;
    this.dialogRef.close(this.form.value);
  }
}
