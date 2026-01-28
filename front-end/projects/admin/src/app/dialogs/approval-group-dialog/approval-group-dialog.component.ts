import { Component, Inject, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  FormsModule,
  ReactiveFormsModule,
  FormBuilder,
  FormGroup,
  Validators,
} from '@angular/forms';
import { MatDialogRef, MAT_DIALOG_DATA, MatDialogModule } from '@angular/material/dialog';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSlideToggleModule } from '@angular/material/slide-toggle';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatChipsModule } from '@angular/material/chips';
import { MatAutocompleteModule } from '@angular/material/autocomplete';
import { MatTooltipModule } from '@angular/material/tooltip';

import { ApprovalGroupStore, ApprovalGroup, UserRef } from 'core-approvals';
import { FeedbackService } from 'shared';
import { debounceTime, Subject } from 'rxjs';

interface DialogData {
  group?: ApprovalGroup;
  manageMembersMode?: boolean;
}

@Component({
  selector: 'app-approval-group-dialog',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    ReactiveFormsModule,
    MatDialogModule,
    MatButtonModule,
    MatIconModule,
    MatFormFieldModule,
    MatInputModule,
    MatSlideToggleModule,
    MatProgressSpinnerModule,
    MatChipsModule,
    MatAutocompleteModule,
    MatTooltipModule,
  ],
  templateUrl: './approval-group-dialog.component.html',
})
export class ApprovalGroupDialogComponent implements OnInit {
  readonly store = inject(ApprovalGroupStore);
  private readonly fb = inject(FormBuilder);
  private readonly feedback = inject(FeedbackService);

  form!: FormGroup;
  isEdit = false;
  manageMembersMode = false;
  saving = false;

  userSearchQuery = '';
  private searchSubject = new Subject<string>();

  constructor(
    public dialogRef: MatDialogRef<ApprovalGroupDialogComponent>,
    @Inject(MAT_DIALOG_DATA) public data: DialogData
  ) {}

  ngOnInit() {
    this.isEdit = !!this.data.group;
    this.manageMembersMode = this.data.manageMembersMode || false;

    this.initForm();

    if (this.isEdit && this.data.group) {
      this.patchForm(this.data.group);

      // Load the full group data with members for manage members mode
      if (this.manageMembersMode) {
        this.store.loadOne(this.data.group.id).subscribe();
      }

      this.store.loadAvailableUsers(this.data.group.id).subscribe();
    }

    // Setup search debounce
    this.searchSubject.pipe(debounceTime(300)).subscribe((query) => {
      if (this.data.group) {
        this.store.loadAvailableUsers(this.data.group.id, query).subscribe();
      }
    });
  }

  private initForm() {
    this.form = this.fb.group({
      name: ['', [Validators.required, Validators.maxLength(255)]],
      code: [
        '',
        [Validators.required, Validators.maxLength(100), Validators.pattern(/^[a-z0-9_-]+$/)],
      ],
      description: ['', Validators.maxLength(1000)],
      is_active: [true],
    });
  }

  private patchForm(group: ApprovalGroup) {
    this.form.patchValue({
      name: group.name,
      code: group.code,
      description: group.description,
      is_active: group.is_active,
    });
  }

  onUserSearch(event: Event) {
    const query = (event.target as HTMLInputElement).value;
    this.searchSubject.next(query);
  }

  addMember(user: UserRef) {
    if (!this.data.group) return;

    this.store.addMembers(this.data.group.id, { user_ids: [user.id] }).subscribe({
      next: () => {
        this.feedback.success('Member added');
        this.store.loadAvailableUsers(this.data.group!.id, this.userSearchQuery).subscribe();
      },
      error: (error) => {
        this.feedback.error(error.error?.message || 'Failed to add member');
      },
    });
  }

  removeMember(userId: string) {
    if (!this.data.group) return;

    this.store.removeMember(this.data.group.id, userId).subscribe({
      next: () => {
        this.feedback.success('Member removed');
        this.store.loadAvailableUsers(this.data.group!.id, this.userSearchQuery).subscribe();
      },
      error: (error) => {
        this.feedback.error(error.error?.message || 'Failed to remove member');
      },
    });
  }

  onSubmit() {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.saving = true;
    const formValue = this.form.value;

    const request = this.isEdit
      ? this.store.update(this.data.group!.id, formValue)
      : this.store.create(formValue);

    request.subscribe({
      next: () => {
        this.feedback.success(this.isEdit ? 'Group updated' : 'Group created');
        this.dialogRef.close(true);
      },
      error: (error) => {
        this.saving = false;
        this.feedback.error(error.error?.message || 'Failed to save group');
      },
    });
  }

  generateCode() {
    const name = this.form.get('name')?.value || '';
    const code = name
      .toLowerCase()
      .replace(/[^a-z0-9\s-]/g, '')
      .replace(/\s+/g, '_')
      .substring(0, 50);
    this.form.get('code')?.setValue(code);
  }
}
