// @medical/shared/components/prompt-dialog.ts
import { Component, inject, OnInit, signal } from '@angular/core';
import { MAT_DIALOG_DATA, MatDialogRef, MatDialogModule } from '@angular/material/dialog';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { FormsModule } from '@angular/forms';

export interface PromptDialogData {
  title: string;
  message: string;
  inputType?: 'text' | 'textarea';
  placeholder?: string;
  required?: boolean;
  minLength?: number;
  confirmLabel?: string;
  cancelLabel?: string;
}

@Component({
  selector: 'lib-prompt-dialog',
  standalone: true,
  imports: [
    MatDialogModule,
    MatButtonModule,
    MatFormFieldModule,
    MatInputModule,
    FormsModule,
  ],
  template: `
    <div class="p-6 max-w-md">
      <h3 class="text-xl font-bold text-slate-900">{{ data.title }}</h3>
      <p class="mt-2 text-slate-600 leading-relaxed">{{ data.message }}</p>

      <div class="mt-4">
        @if (data.inputType === 'textarea') {
          <mat-form-field appearance="outline" class="w-full">
            <textarea
              matInput
              [(ngModel)]="inputValue"
              [placeholder]="data.placeholder || ''"
              rows="4"
              class="resize-none"
            ></textarea>
          </mat-form-field>
        } @else {
          <mat-form-field appearance="outline" class="w-full">
            <input
              matInput
              [(ngModel)]="inputValue"
              [placeholder]="data.placeholder || ''"
            />
          </mat-form-field>
        }
        @if (showError()) {
          <p class="text-sm text-red-600 -mt-4 mb-2">
            @if (data.minLength && inputValue.length < data.minLength) {
              Please enter at least {{ data.minLength }} characters.
            } @else {
              This field is required.
            }
          </p>
        }
      </div>

      <div class="mt-4 flex justify-end gap-3">
        <button mat-button (click)="ref.close(null)">
          {{ data.cancelLabel || 'Cancel' }}
        </button>
        <button
          mat-flat-button
          color="primary"
          (click)="submit()"
          [disabled]="!isValid()"
          class="rounded-lg"
        >
          {{ data.confirmLabel || 'Submit' }}
        </button>
      </div>
    </div>
  `,
})
export class PromptDialog implements OnInit {
  ref = inject(MatDialogRef<PromptDialog>);
  data = inject<PromptDialogData>(MAT_DIALOG_DATA);

  inputValue = '';
  showError = signal(false);

  ngOnInit() {
    // Set defaults
    if (!this.data.inputType) this.data.inputType = 'text';
    if (this.data.required === undefined) this.data.required = true;
  }

  isValid(): boolean {
    if (this.data.required && !this.inputValue.trim()) return false;
    if (this.data.minLength && this.inputValue.trim().length < this.data.minLength) return false;
    return true;
  }

  submit() {
    if (!this.isValid()) {
      this.showError.set(true);
      return;
    }
    this.ref.close(this.inputValue.trim());
  }
}
