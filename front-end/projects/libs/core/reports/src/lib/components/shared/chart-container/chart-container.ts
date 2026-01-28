import { Component, input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatMenuModule } from '@angular/material/menu';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';

@Component({
  selector: 'lib-chart-container',
  standalone: true,
  imports: [CommonModule, MatIconModule, MatButtonModule, MatMenuModule, MatProgressSpinnerModule],
  template: `
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
      <!-- Header -->
      <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
        <div>
          <h3 class="text-base font-semibold text-gray-900">{{ title() }}</h3>
          @if (subtitle()) {
          <p class="text-sm text-gray-500 mt-0.5">{{ subtitle() }}</p>
          }
        </div>
        @if (showActions()) {
        <div class="flex items-center gap-2">
          <button mat-icon-button [matMenuTriggerFor]="exportMenu" matTooltip="Export">
            <mat-icon fontSet="material-symbols-rounded">download</mat-icon>
          </button>
          <mat-menu #exportMenu="matMenu">
            <button mat-menu-item>
              <mat-icon fontSet="material-symbols-rounded">image</mat-icon>
              <span>Export as PNG</span>
            </button>
            <button mat-menu-item>
              <mat-icon fontSet="material-symbols-rounded">code</mat-icon>
              <span>Export as SVG</span>
            </button>
            <button mat-menu-item>
              <mat-icon fontSet="material-symbols-rounded">table_chart</mat-icon>
              <span>Export as CSV</span>
            </button>
          </mat-menu>
        </div>
        }
      </div>

      <!-- Content -->
      <div class="p-5" [style.min-height]="minHeight()">
        @if (loading()) {
        <div class="flex items-center justify-center h-full min-h-[200px]">
          <mat-spinner diameter="40"></mat-spinner>
        </div>
        } @else if (empty()) {
        <div class="flex flex-col items-center justify-center h-full min-h-[200px] text-gray-400">
          <mat-icon fontSet="material-symbols-rounded">insert_chart_outlined</mat-icon>
          <p class="text-sm">{{ emptyMessage() }}</p>
        </div>
        } @else {
        <ng-content></ng-content>
        }
      </div>
    </div>
  `,
})
export class ChartContainerComponent {
  title = input.required<string>();
  subtitle = input<string>();
  loading = input<boolean>(false);
  empty = input<boolean>(false);
  emptyMessage = input<string>('No data available');
  showActions = input<boolean>(true);
  minHeight = input<string>('300px');
}
