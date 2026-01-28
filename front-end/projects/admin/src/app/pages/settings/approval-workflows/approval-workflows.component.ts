import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatMenuModule } from '@angular/material/menu';
import { MatDividerModule } from '@angular/material/divider';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatDialogModule, MatDialog } from '@angular/material/dialog';
import { MatTooltipModule } from '@angular/material/tooltip';
import { MatChipsModule } from '@angular/material/chips';
import { MatSelectModule } from '@angular/material/select';

import { ApprovalWorkflowStore, ApprovalWorkflow, ENTITY_TYPES } from 'core-approvals';
import { FeedbackService } from 'shared';
import { ApprovalWorkflowDialogComponent } from '../../../dialogs/approval-workflow-dialog/approval-workflow-dialog.component';

@Component({
  selector: 'app-approval-workflows',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    MatIconModule,
    MatButtonModule,
    MatMenuModule,
    MatDividerModule,
    MatProgressSpinnerModule,
    MatDialogModule,
    MatTooltipModule,
    MatChipsModule,
    MatSelectModule,
  ],
  templateUrl: './approval-workflows.component.html',
})
export class ApprovalWorkflowsComponent implements OnInit {
  readonly store = inject(ApprovalWorkflowStore);
  private readonly dialog = inject(MatDialog);
  private readonly feedback = inject(FeedbackService);

  searchQuery = '';
  selectedEntityType = '';
  entityTypes = ENTITY_TYPES;

  ngOnInit() {
    this.store.loadEntityTypes().subscribe();
    this.loadWorkflows();
  }

  loadWorkflows() {
    this.store
      .loadAll({
        search: this.searchQuery || undefined,
        entity_type: this.selectedEntityType || undefined,
      })
      .subscribe();
  }

  onSearch() {
    this.loadWorkflows();
  }

  onEntityTypeChange() {
    this.loadWorkflows();
  }

  getEntityTypeLabel(code: string): string {
    const type = this.entityTypes.find((t) => t.code === code);
    return type?.label || code;
  }

  openWorkflowDialog(workflow?: ApprovalWorkflow) {
    const dialogRef = this.dialog.open(ApprovalWorkflowDialogComponent, {
      width: '700px',
      maxWidth: '95vw',
      maxHeight: '90vh',
      data: { workflow },
      disableClose: true,
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (result) {
        this.loadWorkflows();
      }
    });
  }

  deleteWorkflow(workflow: ApprovalWorkflow) {
    this.feedback
      .confirm(
        'Delete Workflow',
        `Are you sure you want to delete "${workflow.name}"? This cannot be undone.`
      )
      .then((confirmed) => {
        if (confirmed) {
          this.store.delete(workflow.id).subscribe({
            next: () => {
              this.feedback.success('Workflow deleted successfully');
            },
            error: (error) => {
              this.feedback.error(error.error?.message || 'Failed to delete workflow');
            },
          });
        }
      });
  }

  toggleActive(workflow: ApprovalWorkflow) {
    this.store.update(workflow.id, { is_active: !workflow.is_active }).subscribe({
      next: () => {
        this.feedback.success(`Workflow ${workflow.is_active ? 'deactivated' : 'activated'}`);
      },
      error: (error) => {
        this.feedback.error(error.error?.message || 'Failed to update workflow');
      },
    });
  }
}
