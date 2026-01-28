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

import { ApprovalGroupStore, ApprovalGroup } from 'core-approvals';
import { FeedbackService } from 'shared';
import { ApprovalGroupDialogComponent } from '../../../dialogs/approval-group-dialog/approval-group-dialog.component';

@Component({
  selector: 'app-approval-groups',
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
  ],
  templateUrl: './approval-groups.component.html',
})
export class ApprovalGroupsComponent implements OnInit {
  readonly store = inject(ApprovalGroupStore);
  private readonly dialog = inject(MatDialog);
  private readonly feedback = inject(FeedbackService);

  searchQuery = '';

  ngOnInit() {
    this.loadGroups();
  }

  loadGroups() {
    this.store.loadAll({ search: this.searchQuery || undefined }).subscribe();
  }

  onSearch() {
    this.loadGroups();
  }

  openGroupDialog(group?: ApprovalGroup) {
    const dialogRef = this.dialog.open(ApprovalGroupDialogComponent, {
      width: '600px',
      maxWidth: '95vw',
      maxHeight: '90vh',
      data: { group },
      disableClose: true,
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (result) {
        this.loadGroups();
      }
    });
  }

  manageMembers(group: ApprovalGroup) {
    const dialogRef = this.dialog.open(ApprovalGroupDialogComponent, {
      width: '600px',
      maxWidth: '95vw',
      maxHeight: '90vh',
      data: { group, manageMembersMode: true },
      disableClose: true,
    });

    dialogRef.afterClosed().subscribe((result) => {
      if (result) {
        this.loadGroups();
      }
    });
  }

  deleteGroup(group: ApprovalGroup) {
    this.feedback
      .confirm(
        'Delete Group',
        `Are you sure you want to delete "${group.name}"? This cannot be undone.`
      )
      .then((confirmed) => {
        if (confirmed) {
          this.store.delete(group.id).subscribe({
            next: () => {
              this.feedback.success('Group deleted successfully');
            },
            error: (error) => {
              this.feedback.error(error.error?.message || 'Failed to delete group');
            },
          });
        }
      });
  }

  toggleActive(group: ApprovalGroup) {
    this.store.update(group.id, { is_active: !group.is_active }).subscribe({
      next: () => {
        this.feedback.success(`Group ${group.is_active ? 'deactivated' : 'activated'}`);
      },
      error: (error) => {
        this.feedback.error(error.error?.message || 'Failed to update group');
      },
    });
  }
}
