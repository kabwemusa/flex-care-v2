// Approval Timeline Component - Shows approval workflow progress and history
import { Component, Input, computed, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatIconModule } from '@angular/material/icon';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';

import { ApprovalHistory, ApprovalRequest, ApprovalStep } from '../../models/approval.models';

export interface ApprovalTimelineData {
  status: string;
  has_request: boolean;
  request_id?: string;
  workflow?: {
    id: string;
    name: string;
    steps: ApprovalStep[];
  };
  current_step?: {
    id: string;
    name: string;
    group: string;
    order: number;
  };
  current_step_number?: number;
  total_steps?: number;
  progress_percentage?: number;
  can_approve?: boolean;
  histories?: ApprovalHistory[];
}

@Component({
  selector: 'lib-approval-timeline',
  standalone: true,
  imports: [
    CommonModule,
    MatIconModule,
    MatTooltipModule,
    MatProgressSpinnerModule,
  ],
  template: `
    @if (data()) {
      <div class="approval-timeline">
        <!-- Header -->
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
            <mat-icon fontSet="material-symbols-rounded" class="!text-lg text-slate-400">
              approval
            </mat-icon>
            Approval Status
          </h3>
          @if (data()!.has_request) {
            <span [class]="getStatusBadgeClass(data()!.status)">
              {{ getStatusLabel(data()!.status) }}
            </span>
          }
        </div>

        @if (!data()!.has_request) {
          <!-- No Approval Request -->
          <div class="text-center py-6 bg-slate-50 rounded-lg border border-slate-200">
            <mat-icon fontSet="material-symbols-rounded" class="!text-3xl text-slate-300 mb-2">
              hourglass_empty
            </mat-icon>
            <p class="text-sm text-slate-500">No approval workflow started</p>
          </div>
        } @else {
          <!-- Workflow Progress -->
          <div class="mb-4">
            <div class="flex items-center justify-between text-xs text-slate-500 mb-1">
              <span>{{ data()!.workflow?.name }}</span>
              <span>{{ data()!.current_step_number }}/{{ data()!.total_steps }} steps</span>
            </div>
            <div class="w-full bg-slate-200 rounded-full h-2">
              <div
                class="rounded-full h-2 transition-all duration-500"
                [class]="getProgressBarClass(data()!.status)"
                [style.width.%]="data()!.progress_percentage"
              ></div>
            </div>
          </div>

          <!-- Steps Timeline -->
          <div class="relative">
            @for (step of getStepsWithStatus(); track step.id; let i = $index; let last = $last) {
              <div class="flex gap-3 relative">
                <!-- Timeline Line -->
                @if (!last) {
                  <div class="absolute left-[15px] top-8 w-0.5 h-[calc(100%-8px)]"
                    [class]="step.completed ? 'bg-green-300' : 'bg-slate-200'">
                  </div>
                }

                <!-- Step Indicator -->
                <div class="relative z-10 flex-shrink-0">
                  @if (step.completed) {
                    <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center">
                      <mat-icon fontSet="material-symbols-rounded" class="!text-lg text-green-600">
                        check_circle
                      </mat-icon>
                    </div>
                  } @else if (step.rejected) {
                    <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center">
                      <mat-icon fontSet="material-symbols-rounded" class="!text-lg text-red-600">
                        cancel
                      </mat-icon>
                    </div>
                  } @else if (step.returned) {
                    <div class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center">
                      <mat-icon fontSet="material-symbols-rounded" class="!text-lg text-amber-600">
                        undo
                      </mat-icon>
                    </div>
                  } @else if (step.current) {
                    <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center ring-2 ring-blue-300 ring-offset-2">
                      <mat-icon fontSet="material-symbols-rounded" class="!text-lg text-blue-600">
                        pending
                      </mat-icon>
                    </div>
                  } @else {
                    <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center">
                      <span class="text-xs font-medium text-slate-400">{{ step.order }}</span>
                    </div>
                  }
                </div>

                <!-- Step Content -->
                <div class="flex-1 pb-6">
                  <div class="flex items-start justify-between">
                    <div>
                      <p class="text-sm font-medium"
                        [class.text-slate-900]="step.completed || step.current"
                        [class.text-slate-500]="!step.completed && !step.current">
                        {{ step.name }}
                      </p>
                      <p class="text-xs text-slate-500 flex items-center gap-1 mt-0.5">
                        <mat-icon fontSet="material-symbols-rounded" class="!text-xs">group</mat-icon>
                        {{ step.group_name }}
                      </p>
                    </div>
                    @if (step.current && showActions) {
                      <span class="px-2 py-0.5 text-[10px] font-medium rounded-full bg-blue-100 text-blue-700">
                        Awaiting Action
                      </span>
                    }
                  </div>

                  <!-- History for this step -->
                  @if (step.history) {
                    <div class="mt-2 pl-3 border-l-2"
                      [class.border-green-200]="step.history.action === 'approved'"
                      [class.border-red-200]="step.history.action === 'rejected'"
                      [class.border-amber-200]="step.history.action === 'returned'">
                      <p class="text-xs text-slate-600">
                        <span class="font-medium">{{ step.history.actioned_by_user?.username || step.history.actioned_by_user?.email }}</span>
                        {{ step.history.action === 'approved' ? 'approved' : step.history.action === 'rejected' ? 'rejected' : 'returned' }}
                        on {{ formatDate(step.history.actioned_at) }}
                      </p>
                      @if (step.history.comments) {
                        <p class="text-xs text-slate-500 mt-1 italic">"{{ step.history.comments }}"</p>
                      }
                    </div>
                  }
                </div>
              </div>
            }
          </div>
        }
      </div>
    } @else if (loading()) {
      <div class="flex items-center justify-center py-8">
        <mat-spinner diameter="24"></mat-spinner>
      </div>
    }
  `,
  styles: [`
    :host {
      display: block;
    }
  `]
})
export class ApprovalTimelineComponent {
  @Input({ required: true }) set approvalData(value: ApprovalTimelineData | null) {
    this.data.set(value);
  }
  @Input() showActions = false;
  @Input() set isLoading(value: boolean) {
    this.loading.set(value);
  }

  readonly data = signal<ApprovalTimelineData | null>(null);
  readonly loading = signal(false);

  getStepsWithStatus() {
    const data = this.data();
    if (!data?.workflow?.steps) return [];

    const histories = data.histories || [];
    const currentStepId = data.current_step?.id;

    return data.workflow.steps
      .sort((a, b) => a.step_order - b.step_order)
      .map(step => {
        const history = histories.find(h => h.step_id === step.id);
        const isCurrentStep = step.id === currentStepId;
        const stepOrder = step.step_order;
        const currentOrder = data.current_step?.order || 0;

        return {
          id: step.id,
          name: step.name,
          order: step.step_order,
          group_name: step.group?.name || 'Unknown Group',
          completed: history?.action === 'approved',
          rejected: data.status === 'rejected' && history?.action === 'rejected',
          returned: data.status === 'returned' && history?.action === 'returned',
          current: isCurrentStep && data.status === 'pending',
          history: history,
        };
      });
  }

  getStatusLabel(status: string): string {
    const labels: Record<string, string> = {
      pending: 'In Progress',
      approved: 'Approved',
      rejected: 'Rejected',
      returned: 'Returned',
      cancelled: 'Cancelled',
    };
    return labels[status] || status;
  }

  getStatusBadgeClass(status: string): string {
    const classes: Record<string, string> = {
      pending: 'px-2 py-0.5 text-xs font-medium rounded-full bg-blue-100 text-blue-700',
      approved: 'px-2 py-0.5 text-xs font-medium rounded-full bg-green-100 text-green-700',
      rejected: 'px-2 py-0.5 text-xs font-medium rounded-full bg-red-100 text-red-700',
      returned: 'px-2 py-0.5 text-xs font-medium rounded-full bg-amber-100 text-amber-700',
      cancelled: 'px-2 py-0.5 text-xs font-medium rounded-full bg-slate-100 text-slate-600',
    };
    return classes[status] || 'px-2 py-0.5 text-xs font-medium rounded-full bg-slate-100 text-slate-600';
  }

  getProgressBarClass(status: string): string {
    const classes: Record<string, string> = {
      pending: 'bg-blue-500',
      approved: 'bg-green-500',
      rejected: 'bg-red-500',
      returned: 'bg-amber-500',
      cancelled: 'bg-slate-400',
    };
    return classes[status] || 'bg-slate-400';
  }

  formatDate(date: string): string {
    return new Date(date).toLocaleDateString('en-ZM', {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  }
}
