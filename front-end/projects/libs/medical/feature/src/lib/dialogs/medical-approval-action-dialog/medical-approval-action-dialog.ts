// libs/medical/feature/src/lib/dialogs/medical-approval-action-dialog/medical-approval-action-dialog.ts

import { Component, inject, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';

import { MatDialogModule, MatDialogRef, MAT_DIALOG_DATA } from '@angular/material/dialog';
import { MatButtonModule } from '@angular/material/button';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';

import { ApplicationStore } from 'medical-data';
import { FeedbackService } from 'shared';

export type ApprovalAction = 'approve' | 'reject' | 'return';

export interface ApprovalActionDialogData {
  applicationId: string;
  applicationNumber?: string;
  action: ApprovalAction;
  approvalStatus?: any;
}

@Component({
  selector: 'lib-medical-approval-action-dialog',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    MatDialogModule,
    MatButtonModule,
    MatFormFieldModule,
    MatInputModule,
    MatIconModule,
    MatProgressSpinnerModule,
  ],
  templateUrl: './medical-approval-action-dialog.html',
})
export class MedicalApprovalActionDialog implements OnInit {
  private readonly fb = inject(FormBuilder);
  private readonly store = inject(ApplicationStore);
  private readonly feedback = inject(FeedbackService);
  readonly dialogRef = inject(MatDialogRef<MedicalApprovalActionDialog>);
  readonly data = inject<ApprovalActionDialogData>(MAT_DIALOG_DATA);

  readonly isSubmitting = signal(false);
  readonly approvalStatus = signal<any>(null);

  readonly form = this.fb.group({
    comments: ['', []],
    reason: ['', []],
  });

  ngOnInit() {
    // Set validators based on action
    if (this.data.action === 'reject' || this.data.action === 'return') {
      this.form
        .get('reason')
        ?.setValidators([
          Validators.required,
          Validators.minLength(10),
          Validators.maxLength(1000),
        ]);
    }

    // Load approval status if not provided
    if (this.data.approvalStatus) {
      this.approvalStatus.set(this.data.approvalStatus);
    } else {
      this.loadApprovalStatus();
    }
  }

  private loadApprovalStatus() {
    this.store.getApprovalStatus(this.data.applicationId).subscribe({
      next: (res) => this.approvalStatus.set(res.data),
      error: () => {},
    });
  }

  get actionConfig() {
    const configs: Record<
      ApprovalAction,
      { title: string; icon: string; color: string; buttonLabel: string; description: string }
    > = {
      approve: {
        title: 'Approve Application',
        icon: 'check_circle',
        color: 'emerald',
        buttonLabel: 'Approve',
        description:
          'This step will be approved and the application will proceed to the next approval step (if any).',
      },
      reject: {
        title: 'Reject Application',
        icon: 'cancel',
        color: 'red',
        buttonLabel: 'Reject',
        description:
          'The application will be rejected and the approval process will be terminated. Please provide a detailed reason.',
      },
      return: {
        title: 'Return for Amendment',
        icon: 'undo',
        color: 'amber',
        buttonLabel: 'Return',
        description:
          'The application will be returned to the initiator for amendments. Please explain what changes are needed.',
      },
    };
    return configs[this.data.action];
  }

  cancel() {
    this.dialogRef.close();
  }

  submit() {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.isSubmitting.set(true);

    const action$ =
      this.data.action === 'approve'
        ? this.store.approvalApprove(this.data.applicationId, this.form.value.comments || undefined)
        : this.data.action === 'reject'
        ? this.store.approvalReject(this.data.applicationId, this.form.value.reason!)
        : this.store.approvalReturn(this.data.applicationId, this.form.value.reason!);

    action$.subscribe({
      next: (res) => {
        this.isSubmitting.set(false);
        const isFinal = !!(
          (res.data as any)?.is_final ?? (res.data as any)?.approval_status?.is_final
        );
        if (this.data.action === 'approve') {
          this.feedback.success(
            isFinal ? 'Application fully approved!' : 'Approval step completed'
          );
        } else if (this.data.action === 'reject') {
          this.feedback.success('Application rejected');
        } else {
          this.feedback.success('Application returned for amendment');
        }
        this.dialogRef.close(res.data);
      },
      error: (err) => {
        this.isSubmitting.set(false);
        this.feedback.error(err?.error?.message || `Failed to ${this.data.action} application`);
      },
    });
  }
}
