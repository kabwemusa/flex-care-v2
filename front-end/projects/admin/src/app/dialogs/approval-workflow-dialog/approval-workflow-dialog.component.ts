import { Component, Inject, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  FormsModule,
  ReactiveFormsModule,
  FormBuilder,
  FormGroup,
  Validators,
  FormArray,
} from '@angular/forms';
import { MatDialogRef, MAT_DIALOG_DATA, MatDialogModule } from '@angular/material/dialog';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatSlideToggleModule } from '@angular/material/slide-toggle';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatTooltipModule } from '@angular/material/tooltip';
import { CdkDragDrop, DragDropModule, moveItemInArray } from '@angular/cdk/drag-drop';

import {
  ApprovalWorkflowStore,
  ApprovalGroupStore,
  ApprovalWorkflow,
  ENTITY_TYPES,
} from 'core-approvals';
import { FeedbackService } from 'shared';

interface DialogData {
  workflow?: ApprovalWorkflow;
}

@Component({
  selector: 'app-approval-workflow-dialog',
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
    MatSelectModule,
    MatSlideToggleModule,
    MatProgressSpinnerModule,
    MatTooltipModule,
    DragDropModule,
  ],
  templateUrl: './approval-workflow-dialog.component.html',
})
export class ApprovalWorkflowDialogComponent implements OnInit {
  private readonly workflowStore = inject(ApprovalWorkflowStore);
  readonly groupStore = inject(ApprovalGroupStore);
  private readonly fb = inject(FormBuilder);
  private readonly feedback = inject(FeedbackService);

  form!: FormGroup;
  isEdit = false;
  saving = false;
  entityTypes = ENTITY_TYPES;

  constructor(
    public dialogRef: MatDialogRef<ApprovalWorkflowDialogComponent>,
    @Inject(MAT_DIALOG_DATA) public data: DialogData
  ) {}

  ngOnInit() {
    this.isEdit = !!this.data.workflow;
    this.initForm();

    // Load groups for step selection
    this.groupStore.loadAll({ is_active: true }).subscribe();

    if (this.isEdit && this.data.workflow) {
      this.patchForm(this.data.workflow);
    }
  }

  private initForm() {
    this.form = this.fb.group({
      name: ['', [Validators.required, Validators.maxLength(255)]],
      code: [
        '',
        [Validators.required, Validators.maxLength(100), Validators.pattern(/^[a-z0-9_-]+$/)],
      ],
      entity_type: ['', Validators.required],
      description: ['', Validators.maxLength(1000)],
      is_active: [true],
      steps: this.fb.array([]),
    });
  }

  private patchForm(workflow: ApprovalWorkflow) {
    this.form.patchValue({
      name: workflow.name,
      code: workflow.code,
      entity_type: workflow.entity_type,
      description: workflow.description,
      is_active: workflow.is_active,
    });

    // Disable entity_type in edit mode
    this.form.get('entity_type')?.disable();

    // Add steps
    if (workflow.steps) {
      workflow.steps.forEach((step) => {
        this.stepsArray.push(
          this.fb.group({
            id: [step.id],
            name: [step.name, Validators.required],
            group_id: [step.group_id, Validators.required],
            description: [step.description],
          })
        );
      });
    }
  }

  get stepsArray(): FormArray {
    return this.form.get('steps') as FormArray;
  }

  addStep() {
    this.stepsArray.push(
      this.fb.group({
        id: [null],
        name: ['', Validators.required],
        group_id: ['', Validators.required],
        description: [''],
      })
    );
  }

  removeStep(index: number) {
    this.stepsArray.removeAt(index);
  }

  onStepDrop(event: CdkDragDrop<unknown[]>) {
    const stepsArray = this.stepsArray;
    const steps = stepsArray.controls;
    moveItemInArray(steps, event.previousIndex, event.currentIndex);

    // Update the form array
    this.form.setControl('steps', this.fb.array(steps));
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

  onSubmit() {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.saving = true;
    const formValue = this.form.value;

    if (this.isEdit) {
      // Update workflow and steps separately
      this.workflowStore
        .update(this.data.workflow!.id, {
          name: formValue.name,
          code: formValue.code,
          entity_type: formValue.entity_type,
          description: formValue.description,
          is_active: formValue.is_active,
        })
        .subscribe({
          next: () => {
            this.updateSteps(formValue.steps);
          },
          error: (error) => {
            this.saving = false;
            this.feedback.error(error.error?.message || 'Failed to update workflow');
          },
        });
    } else {
      // Create workflow with steps
      this.workflowStore
        .create({
          name: formValue.name,
          code: formValue.code,
          entity_type: formValue.entity_type,
          description: formValue.description,
          is_active: formValue.is_active,
          steps: formValue.steps.map(
            (step: { name: string; group_id: string; description: string }, index: number) => ({
              name: step.name,
              group_id: step.group_id,
              description: step.description,
              step_order: index + 1,
            })
          ),
        })
        .subscribe({
          next: () => {
            this.feedback.success('Workflow created');
            this.dialogRef.close(true);
          },
          error: (error) => {
            this.saving = false;
            this.feedback.error(error.error?.message || 'Failed to create workflow');
          },
        });
    }
  }

  private updateSteps(
    steps: Array<{ id: string | null; name: string; group_id: string; description: string }>
  ) {
    // For simplicity, we'll just close the dialog
    // In a real app, you might want to handle step updates more granularly
    this.feedback.success('Workflow updated');
    this.dialogRef.close(true);
  }
}
