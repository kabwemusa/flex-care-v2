// libs/medical/ui/src/lib/dialogs/rate-card-tier-dialog/rate-card-tier-dialog.ts

import { Component, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogRef, MatDialogModule } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';

import { RateCardTier } from 'medical-data';

@Component({
  selector: 'lib-rate-card-tier-dialog',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    MatDialogModule,
    MatFormFieldModule,
    MatInputModule,
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
            <mat-icon fontSet="material-symbols-rounded">groups</mat-icon>
          </div>
          <div>
            <h2 class="text-lg font-semibold tracking-tight text-slate-900">
              {{ isEditMode ? 'Edit Tier' : 'Add Tier' }}
            </h2>
            <p class="text-xs text-slate-500">Family-size based pricing tier</p>
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
          <!-- Tier Name -->
          <div class="flex flex-col gap-1.5">
            <label class="text-sm font-medium text-slate-700">
              Tier Name <span class="text-red-500">*</span>
            </label>
            <mat-form-field appearance="outline" class="w-full" subscriptSizing="dynamic">
              <input
                matInput
                formControlName="tier_name"
                placeholder="e.g., Individual, M+1, Family"
              />
              @if (form.get('tier_name')?.hasError('required')) {
              <mat-error>Required</mat-error>
              }
            </mat-form-field>
          </div>

          <!-- Tier Description -->
          <div class="flex flex-col gap-1.5">
            <label class="text-sm font-medium text-slate-700">Description</label>
            <mat-form-field appearance="outline" class="w-full" subscriptSizing="dynamic">
              <input
                matInput
                formControlName="tier_description"
                placeholder="e.g., Principal + 1 dependent"
              />
            </mat-form-field>
          </div>

          <!-- Member Range -->
          <div class="grid grid-cols-2 gap-4">
            <div class="flex flex-col gap-1.5">
              <label class="text-sm font-medium text-slate-700">
                Min Members <span class="text-red-500">*</span>
              </label>
              <mat-form-field appearance="outline" class="w-full" subscriptSizing="dynamic">
                <input matInput type="number" formControlName="min_members" min="1" />
                @if (form.get('min_members')?.hasError('required')) {
                <mat-error>Required</mat-error>
                }
              </mat-form-field>
            </div>

            <div class="flex flex-col gap-1.5">
              <label class="text-sm font-medium text-slate-700">Max Members</label>
              <mat-form-field appearance="outline" class="w-full" subscriptSizing="dynamic">
                <input matInput type="number" formControlName="max_members" min="1" />
              </mat-form-field>
            </div>
          </div>
          <p class="text-xs text-slate-400">Leave "Max Members" empty for unlimited (e.g., 5+)</p>

          <!-- Tier Premium -->
          <div class="flex flex-col gap-1.5">
            <label class="text-sm font-medium text-slate-700">
              Tier Premium <span class="text-red-500">*</span>
            </label>
            <mat-form-field appearance="outline" class="w-full" subscriptSizing="dynamic">
              <span matTextPrefix class="text-slate-400 mr-1">ZMW</span>
              <input matInput type="number" formControlName="tier_premium" min="0" step="0.01" />
              @if (form.get('tier_premium')?.hasError('required')) {
              <mat-error>Required</mat-error>
              }
            </mat-form-field>
            <p class="text-xs text-slate-400">Fixed premium for this tier</p>
          </div>

          <!-- Extra Member Premium -->
          <div class="flex flex-col gap-1.5">
            <label class="text-sm font-medium text-slate-700">Extra Member Premium</label>
            <mat-form-field appearance="outline" class="w-full" subscriptSizing="dynamic">
              <span matTextPrefix class="text-slate-400 mr-1">ZMW</span>
              <input
                matInput
                type="number"
                formControlName="extra_member_premium"
                min="0"
                step="0.01"
              />
            </mat-form-field>
            <p class="text-xs text-slate-400">
              Additional premium per member beyond max (for open-ended tiers)
            </p>
          </div>

          <!-- Sort Order -->
          <div class="flex flex-col gap-1.5">
            <label class="text-sm font-medium text-slate-700">Sort Order</label>
            <mat-form-field appearance="outline" class="w-full" subscriptSizing="dynamic">
              <input matInput type="number" formControlName="sort_order" min="0" />
            </mat-form-field>
            <p class="text-xs text-slate-400">Lower numbers appear first</p>
          </div>

          <!-- Preview -->
          <div class="rounded-lg bg-slate-50 border border-slate-200 p-4">
            <p class="text-xs text-slate-500 mb-2">Preview</p>
            <div class="flex items-center justify-between">
              <div>
                <p class="text-sm font-medium text-slate-900">
                  {{ form.get('tier_name')?.value || 'Tier Name' }}
                </p>
                <p class="text-xs text-slate-500">
                  {{ form.get('min_members')?.value || 1 }}
                  @if (form.get('max_members')?.value) { - {{ form.get('max_members')?.value }}
                  } @else { + } members
                </p>
              </div>
              <div class="text-right">
                <p class="text-sm font-semibold text-slate-900">
                  ZMW {{ form.get('tier_premium')?.value | number : '1.2-2' }}
                </p>
                @if (form.get('extra_member_premium')?.value) {
                <p class="text-xs text-slate-500">
                  + ZMW {{ form.get('extra_member_premium')?.value | number : '1.2-2' }}/extra
                </p>
                }
              </div>
            </div>
          </div>
        </form>
      </div>

      <!-- Footer -->
      <div
        class="flex items-center justify-end gap-3 border-t border-slate-100 bg-slate-50 px-6 py-4"
      >
        <button mat-button (click)="dialogRef.close()" class="!text-slate-600">Cancel</button>
        <button mat-flat-button color="primary" [disabled]="!form.valid" (click)="save()">
          {{ isEditMode ? 'Update Tier' : 'Add Tier' }}
        </button>
      </div>
    </div>
  `,
})
export class RateCardTierDialog implements OnInit {
  private readonly fb = inject(FormBuilder);
  readonly dialogRef = inject(MatDialogRef<RateCardTierDialog>);
  readonly data = inject<RateCardTier | null>(MAT_DIALOG_DATA);

  form!: FormGroup;
  isEditMode = false;

  ngOnInit() {
    this.isEditMode = !!this.data?.id;

    this.form = this.fb.group({
      tier_name: [this.data?.tier_name || '', Validators.required],
      tier_description: [this.data?.tier_description || ''],
      min_members: [this.data?.min_members ?? 1, [Validators.required, Validators.min(1)]],
      max_members: [this.data?.max_members || null],
      tier_premium: [this.data?.tier_premium ?? 0, [Validators.required, Validators.min(0)]],
      extra_member_premium: [this.data?.extra_member_premium || null],
      sort_order: [this.data?.sort_order ?? 0],
    });
  }

  save() {
    if (!this.form.valid) return;

    const value = this.form.value;
    // Clean up null/empty values
    if (!value.tier_description) delete value.tier_description;
    if (!value.max_members) delete value.max_members;
    if (!value.extra_member_premium) delete value.extra_member_premium;

    this.dialogRef.close(value);
  }
}
