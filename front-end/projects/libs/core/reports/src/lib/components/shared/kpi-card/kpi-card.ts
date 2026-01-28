import { Component, input, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatIconModule } from '@angular/material/icon';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { formatCurrency, formatNumber, formatPercent } from '../../../constants/report.constants';

@Component({
  selector: 'lib-kpi-card',
  standalone: true,
  imports: [CommonModule, MatIconModule, MatTooltipModule, MatProgressSpinnerModule],
  template: `
    <div
      class="bg-white rounded-xl border border-gray-200 p-5 hover:shadow-lg transition-all duration-200"
      [class.animate-pulse]="loading()"
    >
      <div class="flex items-start justify-between">
        <div class="flex-1 min-w-0">
          <p class="text-sm font-medium text-gray-500 truncate">{{ label() }}</p>
          @if (loading()) {
            <div class="mt-2 h-8 bg-gray-200 rounded w-24 animate-pulse"></div>
          } @else {
            <p class="mt-1 text-2xl font-bold" [class]="valueColorClass()">
              {{ formattedValue() }}
            </p>
          }
          @if (change() !== undefined && !loading()) {
            <div class="mt-2 flex items-center text-sm" [class]="changeColorClass()">
              <mat-icon fontSet="material-symbols-rounded">{{ changeIcon() }}</mat-icon>
              <span class="ml-1">{{ Math.abs(change()!) }}% vs last period</span>
            </div>
          }
          @if (subtitle() && !loading()) {
            <p class="mt-2 text-xs text-gray-400">{{ subtitle() }}</p>
          }
        </div>
        <div
          class="ml-4 p-3 rounded-xl flex-shrink-0"
          [class]="iconBgClass()"
        >
          <mat-icon fontSet="material-symbols-rounded" [class]="iconColorClass()">{{ icon() }}</mat-icon>
        </div>
      </div>
    </div>
  `,
})
export class KpiCardComponent {
  readonly Math = Math;

  // Inputs
  label = input.required<string>();
  value = input.required<number | string>();
  icon = input<string>('analytics');
  format = input<'number' | 'currency' | 'percent' | 'text' | 'compact'>('number');
  change = input<number>();
  subtitle = input<string>();
  color = input<'default' | 'success' | 'warning' | 'danger' | 'info'>('default');
  loading = input<boolean>(false);

  // Formatted value
  formattedValue = computed(() => {
    const val = this.value();
    if (typeof val === 'string') return val;

    switch (this.format()) {
      case 'currency':
        return formatCurrency(val);
      case 'percent':
        return formatPercent(val);
      case 'compact':
        return this.formatCompact(val);
      case 'number':
      default:
        return formatNumber(val);
    }
  });

  // Value color
  valueColorClass = computed(() => {
    switch (this.color()) {
      case 'success':
        return 'text-green-600';
      case 'warning':
        return 'text-amber-600';
      case 'danger':
        return 'text-red-600';
      case 'info':
        return 'text-blue-600';
      default:
        return 'text-gray-900';
    }
  });

  // Change indicator
  changeIcon = computed(() => {
    const change = this.change();
    if (!change) return 'remove';
    return change > 0 ? 'trending_up' : 'trending_down';
  });

  changeColorClass = computed(() => {
    const change = this.change();
    if (!change) return 'text-gray-500';
    return change > 0 ? 'text-green-600' : 'text-red-600';
  });

  // Icon background
  iconBgClass = computed(() => {
    switch (this.color()) {
      case 'success':
        return 'bg-green-100';
      case 'warning':
        return 'bg-amber-100';
      case 'danger':
        return 'bg-red-100';
      case 'info':
        return 'bg-blue-100';
      default:
        return 'bg-gray-100';
    }
  });

  // Icon color
  iconColorClass = computed(() => {
    switch (this.color()) {
      case 'success':
        return '!text-green-600';
      case 'warning':
        return '!text-amber-600';
      case 'danger':
        return '!text-red-600';
      case 'info':
        return '!text-blue-600';
      default:
        return '!text-gray-600';
    }
  });

  private formatCompact(value: number): string {
    if (value >= 1_000_000) {
      return `${(value / 1_000_000).toFixed(1)}M`;
    }
    if (value >= 1_000) {
      return `${(value / 1_000).toFixed(1)}K`;
    }
    return value.toLocaleString();
  }
}
