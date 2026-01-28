// Approval Status Badge Component - A compact badge showing approval status
import { Component, Input, computed, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatIconModule } from '@angular/material/icon';
import { MatTooltipModule } from '@angular/material/tooltip';

export interface ApprovalStatusInfo {
  has_request: boolean;
  status?: string;
  current_step?: {
    id: string;
    name: string;
    group: string;
    order: number;
  };
  progress_percentage?: number;
  current_step_number?: number;
  total_steps?: number;
}

@Component({
  selector: 'lib-approval-status-badge',
  standalone: true,
  imports: [CommonModule, MatIconModule, MatTooltipModule],
  template: `
    @if (status()?.has_request) {
      @switch (displayMode) {
        @case ('compact') {
          <!-- Compact: Just icon with tooltip -->
          <span
            [matTooltip]="getTooltip()"
            matTooltipPosition="above"
            [class]="getBadgeClass()"
            class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium cursor-default"
          >
            <mat-icon fontSet="material-symbols-rounded" class="!text-xs">
              {{ getIcon() }}
            </mat-icon>
            {{ getLabel() }}
          </span>
        }
        @case ('inline') {
          <!-- Inline: Icon + text + progress -->
          <div class="flex items-center gap-2">
            <span
              [class]="getBadgeClass()"
              class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium"
            >
              <mat-icon fontSet="material-symbols-rounded" class="!text-sm">
                {{ getIcon() }}
              </mat-icon>
              {{ getLabel() }}
            </span>
            @if (status()?.status === 'pending' && status()?.current_step) {
              <span class="text-xs text-slate-500">
                Step {{ status()?.current_step_number }}/{{ status()?.total_steps }}
              </span>
            }
          </div>
        }
        @case ('detailed') {
          <!-- Detailed: Full info with progress bar -->
          <div class="flex flex-col gap-1">
            <div class="flex items-center gap-2">
              <span
                [class]="getBadgeClass()"
                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium"
              >
                <mat-icon fontSet="material-symbols-rounded" class="!text-sm">
                  {{ getIcon() }}
                </mat-icon>
                {{ getLabel() }}
              </span>
              @if (status()?.status === 'pending' && status()?.current_step) {
                <span class="text-xs text-slate-500">
                  {{ status()?.current_step?.name }}
                </span>
              }
            </div>
            @if (status()?.status === 'pending' && showProgress) {
              <div class="flex items-center gap-2">
                <div class="w-20 h-1.5 bg-slate-200 rounded-full overflow-hidden">
                  <div
                    class="h-full bg-blue-500 rounded-full transition-all"
                    [style.width.%]="status()?.progress_percentage || 0"
                  ></div>
                </div>
                <span class="text-[10px] text-slate-400">
                  {{ status()?.progress_percentage }}%
                </span>
              </div>
            }
          </div>
        }
      }
    }
  `,
  styles: [`
    :host {
      display: inline-flex;
    }
  `]
})
export class ApprovalStatusBadgeComponent {
  @Input({ required: true }) set approvalStatus(value: ApprovalStatusInfo | null | undefined) {
    this.status.set(value || null);
  }
  @Input() displayMode: 'compact' | 'inline' | 'detailed' = 'compact';
  @Input() showProgress = true;

  readonly status = signal<ApprovalStatusInfo | null>(null);

  getIcon(): string {
    const s = this.status();
    if (!s?.has_request) return '';

    const icons: Record<string, string> = {
      pending: 'pending',
      approved: 'check_circle',
      rejected: 'cancel',
      returned: 'undo',
      cancelled: 'block',
    };
    return icons[s.status || 'pending'] || 'pending';
  }

  getLabel(): string {
    const s = this.status();
    if (!s?.has_request) return '';

    const labels: Record<string, string> = {
      pending: 'Approval Pending',
      approved: 'Approved',
      rejected: 'Rejected',
      returned: 'Returned',
      cancelled: 'Cancelled',
    };
    return labels[s.status || 'pending'] || 'Pending';
  }

  getBadgeClass(): string {
    const s = this.status();
    if (!s?.has_request) return '';

    const classes: Record<string, string> = {
      pending: 'bg-blue-100 text-blue-700 border border-blue-200',
      approved: 'bg-green-100 text-green-700 border border-green-200',
      rejected: 'bg-red-100 text-red-700 border border-red-200',
      returned: 'bg-amber-100 text-amber-700 border border-amber-200',
      cancelled: 'bg-slate-100 text-slate-600 border border-slate-200',
    };
    return classes[s.status || 'pending'] || 'bg-slate-100 text-slate-600';
  }

  getTooltip(): string {
    const s = this.status();
    if (!s?.has_request) return '';

    if (s.status === 'pending' && s.current_step) {
      return `Waiting for: ${s.current_step.name} (${s.current_step.group}) - Step ${s.current_step_number}/${s.total_steps}`;
    }

    return this.getLabel();
  }
}
