import { Component, input, output, computed, signal, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatMenuModule } from '@angular/material/menu';
import { MatChipsModule } from '@angular/material/chips';
import { DATE_PRESETS, DatePreset } from '../../../models/report.models';
import { provideNativeDateAdapter } from '@angular/material/core';

export interface DateRange {
  from_date: string;
  to_date: string;
}

@Component({
  selector: 'lib-date-range-picker',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    MatFormFieldModule,
    MatInputModule,
    MatDatepickerModule,
    MatButtonModule,
    MatIconModule,
    MatMenuModule,
    MatChipsModule,
  ],
  template: `
    <div class="flex items-center gap-3 flex-wrap">
      <!-- Quick Presets -->
      <div class="flex items-center gap-2">
        @for (preset of visiblePresets(); track preset.label) {
        <button
          mat-stroked-button
          [color]="isPresetActive(preset) ? 'primary' : undefined"
          (click)="selectPreset(preset)"
          class="!text-sm"
        >
          {{ preset.label }}
        </button>
        } @if (showMorePresets()) {
        <button mat-stroked-button [matMenuTriggerFor]="presetMenu">
          <mat-icon fontSet="material-symbols-rounded">more_horiz</mat-icon>
        </button>
        <mat-menu #presetMenu="matMenu">
          @for (preset of hiddenPresets(); track preset.label) {
          <button mat-menu-item (click)="selectPreset(preset)">
            {{ preset.label }}
          </button>
          }
        </mat-menu>
        }
      </div>

      <!-- Custom Date Range -->
      @if (showCustomRange()) {
      <div class="flex items-center gap-2">
        <mat-form-field appearance="outline" class="!text-sm date-field">
          <mat-label>From</mat-label>
          <input
            matInput
            [matDatepicker]="fromPicker"
            [ngModel]="fromDateObj()"
            (ngModelChange)="onFromDateChange($event)"
          />
          <mat-datepicker-toggle matIconSuffix [for]="fromPicker"></mat-datepicker-toggle>
          <mat-datepicker #fromPicker></mat-datepicker>
        </mat-form-field>

        <span class="text-gray-400">-</span>

        <mat-form-field appearance="outline" class="!text-sm date-field">
          <mat-label>To</mat-label>
          <input
            matInput
            [matDatepicker]="toPicker"
            [ngModel]="toDateObj()"
            (ngModelChange)="onToDateChange($event)"
          />
          <mat-datepicker-toggle matIconSuffix [for]="toPicker"></mat-datepicker-toggle>
          <mat-datepicker #toPicker></mat-datepicker>
        </mat-form-field>

        <button mat-flat-button color="primary" (click)="applyCustomRange()">Apply</button>
      </div>
      }

      <!-- Toggle Custom Range -->
      @if (!showCustomRange()) {
      <button mat-stroked-button (click)="toggleCustomRange()" class="!text-sm">
        <mat-icon fontSet="material-symbols-rounded">date_range</mat-icon>
        Custom
      </button>
      } @else {
      <button mat-icon-button (click)="toggleCustomRange()">
        <mat-icon fontSet="material-symbols-rounded">close</mat-icon>
      </button>
      }
    </div>
  `,
  styles: [
    `
      :host {
        display: block;
      }
      .date-field {
        width: 140px;
      }
      ::ng-deep .date-field .mat-mdc-form-field-subscript-wrapper {
        display: none;
      }
      ::ng-deep .date-field .mat-mdc-text-field-wrapper {
        padding: 0 8px;
      }
      ::ng-deep .date-field .mat-mdc-form-field-infix {
        min-height: 40px;
        padding: 8px 0;
      }
    `,
  ],
  providers: [provideNativeDateAdapter()],
})
export class DateRangePickerComponent implements OnInit {
  // Inputs
  fromDate = input<string>();
  toDate = input<string>();
  presets = input<DatePreset[]>(DATE_PRESETS.slice(0, -1)); // Exclude custom by default
  maxVisiblePresets = input<number>(4);

  // Outputs
  dateRangeChange = output<DateRange>();

  // Internal state
  showCustomRange = signal(false);
  selectedPreset = signal<DatePreset | null>(null);
  customFromDate = signal<Date | null>(null);
  customToDate = signal<Date | null>(null);

  // Computed
  visiblePresets = computed(() => this.presets().slice(0, this.maxVisiblePresets()));
  hiddenPresets = computed(() => this.presets().slice(this.maxVisiblePresets()));
  showMorePresets = computed(() => this.presets().length > this.maxVisiblePresets());

  fromDateObj = computed(() => {
    const fromStr = this.fromDate();
    return fromStr ? new Date(fromStr) : this.customFromDate();
  });

  toDateObj = computed(() => {
    const toStr = this.toDate();
    return toStr ? new Date(toStr) : this.customToDate();
  });

  ngOnInit() {
    // Initialize custom dates from inputs
    const fromStr = this.fromDate();
    const toStr = this.toDate();
    if (fromStr) this.customFromDate.set(new Date(fromStr));
    if (toStr) this.customToDate.set(new Date(toStr));

    // Detect initial preset
    this.detectActivePreset();
  }

  isPresetActive(preset: DatePreset): boolean {
    return this.selectedPreset()?.label === preset.label;
  }

  selectPreset(preset: DatePreset) {
    this.selectedPreset.set(preset);
    this.showCustomRange.set(false);

    const toDate = new Date();
    let fromDate: Date;

    if (preset.value === 'ytd') {
      fromDate = new Date(toDate.getFullYear(), 0, 1);
    } else if (preset.value === 'mtd') {
      fromDate = new Date(toDate.getFullYear(), toDate.getMonth(), 1);
    } else if (preset.value === 'qtd') {
      const quarter = Math.floor(toDate.getMonth() / 3);
      fromDate = new Date(toDate.getFullYear(), quarter * 3, 1);
    } else if (preset.value === 'custom') {
      this.showCustomRange.set(true);
      return;
    } else {
      fromDate = new Date();
      fromDate.setDate(fromDate.getDate() - (preset.value as number));
    }

    this.emitDateRange(fromDate, toDate);
  }

  toggleCustomRange() {
    this.showCustomRange.update((v) => !v);
    if (this.showCustomRange()) {
      this.selectedPreset.set(null);
    }
  }

  onFromDateChange(date: Date | null) {
    this.customFromDate.set(date);
  }

  onToDateChange(date: Date | null) {
    this.customToDate.set(date);
  }

  applyCustomRange() {
    const from = this.customFromDate();
    const to = this.customToDate();
    if (from && to) {
      this.selectedPreset.set(null);
      this.emitDateRange(from, to);
    }
  }

  private emitDateRange(fromDate: Date, toDate: Date) {
    this.dateRangeChange.emit({
      from_date: fromDate.toISOString().split('T')[0],
      to_date: toDate.toISOString().split('T')[0],
    });
  }

  private detectActivePreset() {
    const fromStr = this.fromDate();
    const toStr = this.toDate();
    if (!fromStr || !toStr) return;

    const from = new Date(fromStr);
    const to = new Date(toStr);
    const diffDays = Math.round((to.getTime() - from.getTime()) / (1000 * 60 * 60 * 24));

    const preset = this.presets().find((p) => {
      if (typeof p.value === 'number' && Math.abs(p.value - diffDays) <= 1) {
        return true;
      }
      return false;
    });

    if (preset) {
      this.selectedPreset.set(preset);
    }
  }
}
