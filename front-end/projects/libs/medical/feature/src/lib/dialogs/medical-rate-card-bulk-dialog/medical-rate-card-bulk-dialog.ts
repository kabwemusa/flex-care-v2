// libs/medical/ui/src/lib/dialogs/rate-card-bulk-import-dialog/rate-card-bulk-import-dialog.ts

import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogRef, MatDialogModule } from '@angular/material/dialog';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatTableModule } from '@angular/material/table';

import { RateCardEntry, RateCardListStore } from 'medical-data';
import { FeedbackService } from 'shared';

interface DialogData {
  rateCardId: string;
}

interface ParsedEntry {
  min_age: number;
  max_age: number;
  gender?: 'M' | 'F';
  region_code?: string;
  base_premium: number;
  error?: string;
}

@Component({
  selector: 'lib-rate-card-bulk-import-dialog',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    MatDialogModule,
    MatButtonModule,
    MatIconModule,
    MatProgressSpinnerModule,
    MatTableModule,
  ],
  template: `
    <div class="flex max-h-[90vh] w-full flex-col overflow-hidden bg-white sm:w-[700px]">
      <!-- Header -->
      <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
        <div class="flex items-center gap-3">
          <div
            class="flex h-10 w-10 items-center justify-center rounded-full bg-green-100 text-green-600"
          >
            <mat-icon fontSet="material-symbols-rounded">upload</mat-icon>
          </div>
          <div>
            <h2 class="text-lg font-semibold tracking-tight text-slate-900">Bulk Import Entries</h2>
            <p class="text-xs text-slate-500">Import multiple age bands at once</p>
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
      <div class="flex-1 overflow-y-auto p-6 space-y-4">
        <!-- Instructions -->
        <div class="rounded-lg bg-blue-50 border border-blue-100 p-4">
          <div class="flex gap-3">
            <mat-icon fontSet="material-symbols-rounded" class="!text-blue-500">info</mat-icon>
            <div>
              <p class="text-sm font-medium text-blue-900">CSV Format</p>
              <p class="text-xs text-blue-700 mt-1">
                Paste CSV data with columns:
                <code class="bg-blue-100 px-1 rounded">min_age, max_age, base_premium</code>
                <br />Optional columns:
                <code class="bg-blue-100 px-1 rounded">gender (M/F), region_code</code>
              </p>
            </div>
          </div>
        </div>

        <!-- Example -->
        <div class="rounded-lg bg-slate-50 border border-slate-200 p-4">
          <p class="text-xs text-slate-500 mb-2">Example:</p>
          <pre class="text-xs text-slate-700 font-mono">
min_age,max_age,base_premium
0,17,150.00
18,30,250.00
31,45,350.00
46,60,500.00
61,150,750.00</pre
          >
        </div>

        <!-- Text Area -->
        <div class="flex flex-col gap-1.5">
          <label class="text-sm font-medium text-slate-700">Paste CSV Data</label>
          <textarea
            [(ngModel)]="csvInput"
            (input)="parseInput()"
            rows="8"
            class="w-full rounded-lg border border-slate-200 bg-white p-3 text-sm font-mono text-slate-900 outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
            placeholder="Paste your CSV data here..."
          ></textarea>
        </div>

        <!-- Preview -->
        @if (parsedEntries().length > 0) {
        <div class="rounded-xl border border-slate-200 overflow-hidden">
          <div class="px-4 py-2 bg-slate-50 border-b border-slate-200">
            <p class="text-sm font-medium text-slate-700">
              Preview ({{ validEntries().length }} valid, {{ errorCount() }} errors)
            </p>
          </div>
          <div class="max-h-48 overflow-y-auto">
            <table class="w-full text-sm">
              <thead class="bg-slate-50 sticky top-0">
                <tr>
                  <th class="px-3 py-2 text-left text-xs font-medium text-slate-500">Age Band</th>
                  <th class="px-3 py-2 text-left text-xs font-medium text-slate-500">Gender</th>
                  <th class="px-3 py-2 text-left text-xs font-medium text-slate-500">Region</th>
                  <th class="px-3 py-2 text-right text-xs font-medium text-slate-500">Premium</th>
                  <th class="px-3 py-2 text-left text-xs font-medium text-slate-500">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                @for (entry of parsedEntries(); track $index) {
                <tr [class]="entry.error ? 'bg-red-50' : ''">
                  <td class="px-3 py-2">{{ entry.min_age }} - {{ entry.max_age }}</td>
                  <td class="px-3 py-2">{{ entry.gender || 'All' }}</td>
                  <td class="px-3 py-2">{{ entry.region_code || '-' }}</td>
                  <td class="px-3 py-2 text-right">{{ entry.base_premium | number : '1.2-2' }}</td>
                  <td class="px-3 py-2">
                    @if (entry.error) {
                    <span class="text-red-600 text-xs">{{ entry.error }}</span>
                    } @else {
                    <mat-icon
                      fontSet="material-symbols-rounded"
                      class="!text-green-500 !text-[16px]"
                      >check</mat-icon
                    >
                    }
                  </td>
                </tr>
                }
              </tbody>
            </table>
          </div>
        </div>
        }
      </div>

      <!-- Footer -->
      <div
        class="flex items-center justify-between border-t border-slate-100 bg-slate-50 px-6 py-4"
      >
        <span class="text-sm text-slate-500">
          @if (parsedEntries().length > 0) {
          {{ validEntries().length }} entries ready to import }
        </span>

        <div class="flex items-center gap-3">
          <button mat-button (click)="dialogRef.close()" class="!text-slate-600">Cancel</button>
          <button
            mat-flat-button
            color="primary"
            [disabled]="validEntries().length === 0 || isImporting()"
            (click)="import()"
          >
            @if (isImporting()) {
            <mat-spinner diameter="20" class="!mr-2"></mat-spinner>
            } Import {{ validEntries().length }} Entries
          </button>
        </div>
      </div>
    </div>
  `,
})
export class RateCardBulkImportDialog {
  readonly dialogRef = inject(MatDialogRef<RateCardBulkImportDialog>);
  readonly data = inject<DialogData>(MAT_DIALOG_DATA);
  private readonly store = inject(RateCardListStore);
  private readonly feedback = inject(FeedbackService);

  csvInput = '';
  parsedEntries = signal<ParsedEntry[]>([]);
  isImporting = signal(false);

  validEntries = () => this.parsedEntries().filter((e) => !e.error);
  errorCount = () => this.parsedEntries().filter((e) => e.error).length;

  parseInput() {
    if (!this.csvInput.trim()) {
      this.parsedEntries.set([]);
      return;
    }

    const lines = this.csvInput.trim().split('\n');
    const entries: ParsedEntry[] = [];

    // Check if first line is header
    let startIndex = 0;
    const firstLine = lines[0].toLowerCase();
    if (
      firstLine.includes('min_age') ||
      firstLine.includes('age') ||
      firstLine.includes('premium')
    ) {
      startIndex = 1;
    }

    for (let i = startIndex; i < lines.length; i++) {
      const line = lines[i].trim();
      if (!line) continue;

      const parts = line.split(',').map((p) => p.trim());

      try {
        const entry: ParsedEntry = {
          min_age: parseInt(parts[0], 10),
          max_age: parseInt(parts[1], 10),
          base_premium: parseFloat(parts[2]),
        };

        // Validate
        if (isNaN(entry.min_age) || isNaN(entry.max_age) || isNaN(entry.base_premium)) {
          entry.error = 'Invalid number';
        } else if (entry.min_age < 0 || entry.max_age < 0) {
          entry.error = 'Age cannot be negative';
        } else if (entry.min_age > entry.max_age) {
          entry.error = 'Min > Max';
        } else if (entry.base_premium < 0) {
          entry.error = 'Premium cannot be negative';
        }

        // Optional gender (4th column)
        if (parts[3]) {
          const g = parts[3].toUpperCase();
          if (g === 'M' || g === 'F') {
            entry.gender = g;
          }
        }

        // Optional region (5th column)
        if (parts[4]) {
          entry.region_code = parts[4];
        }

        entries.push(entry);
      } catch (err) {
        entries.push({
          min_age: 0,
          max_age: 0,
          base_premium: 0,
          error: 'Parse error',
        });
      }
    }

    this.parsedEntries.set(entries);
  }

  import() {
    const valid = this.validEntries();
    if (valid.length === 0) return;

    this.isImporting.set(true);

    this.store.bulkImportEntries(this.data.rateCardId, valid).subscribe({
      next: () => {
        this.feedback.success(`Imported ${valid.length} entries`);
        this.dialogRef.close(true);
      },
      error: (err) => {
        this.feedback.error(err?.error?.message ?? 'Import failed');
        this.isImporting.set(false);
      },
    });
  }
}
