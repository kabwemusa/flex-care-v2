// libs/medical/ui/src/lib/dialogs/application-member-dialog/application-member-dialog.component.ts

import { Component, inject, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';

import { MatDialogRef, MAT_DIALOG_DATA, MatDialogModule } from '@angular/material/dialog';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { MatExpansionModule } from '@angular/material/expansion';
import { provideNativeDateAdapter } from '@angular/material/core';

import {
  ApplicationStore,
  ApplicationMember,
  MEMBER_TYPES,
  RELATIONSHIPS,
  GENDERS,
} from 'medical-data';

interface DialogData {
  applicationId: string;
  member?: ApplicationMember;
  principals: ApplicationMember[];
}

@Component({
  selector: 'lib-application-member-dialog',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    MatDialogModule,
    MatButtonModule,
    MatIconModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatDatepickerModule,
    MatCheckboxModule,
    MatExpansionModule,
  ],
  providers: [provideNativeDateAdapter()],
  templateUrl: './medical-application-member-dialog.html',
})
export class MedicalApplicationMemberDialog implements OnInit {
  private readonly dialogRef = inject(MatDialogRef<MedicalApplicationMemberDialog>);
  private readonly data = inject<DialogData>(MAT_DIALOG_DATA);
  private readonly fb = inject(FormBuilder);
  private readonly store = inject(ApplicationStore);

  readonly memberTypes = MEMBER_TYPES;
  readonly relationships = RELATIONSHIPS;
  readonly genders = GENDERS;

  readonly isEditMode = signal(false);
  readonly isSaving = signal(false);

  get principals(): ApplicationMember[] {
    return this.data.principals || [];
  }

  form!: FormGroup;

  ngOnInit() {
    this.isEditMode.set(!!this.data.member);
    this.buildForm();

    if (this.data.member) {
      this.patchForm(this.data.member);
    }
  }

  private buildForm() {
    this.form = this.fb.group({
      member_type: ['principal', Validators.required],
      relationship: ['self'],
      principal_member_id: [''],
      title: [''],
      first_name: ['', Validators.required],
      middle_name: [''],
      last_name: ['', Validators.required],
      date_of_birth: [null, Validators.required],
      gender: ['', Validators.required],
      marital_status: [''],
      national_id: [''],
      passport_number: [''],
      email: ['', Validators.email],
      phone: [''],
      has_pre_existing_conditions: [false],
      medical_history_notes: [''],
    });

    // Watch member_type changes
    this.form.get('member_type')?.valueChanges.subscribe((type) => {
      if (type === 'principal') {
        this.form.patchValue({ relationship: 'self', principal_member_id: '' });
      } else if (this.form.get('relationship')?.value === 'self') {
        this.form.patchValue({ relationship: '' });
      }
    });
  }

  private patchForm(member: ApplicationMember) {
    this.form.patchValue({
      ...member,
      date_of_birth: member.date_of_birth ? new Date(member.date_of_birth) : null,
    });
  }

  isPrincipal(): boolean {
    return this.form.get('member_type')?.value === 'principal';
  }

  cancel() {
    this.dialogRef.close();
  }

  save() {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.isSaving.set(true);
    const formValue = this.form.value;

    const payload = {
      ...formValue,
      date_of_birth: formValue.date_of_birth
        ? formValue.date_of_birth.toISOString().split('T')[0]
        : null,
    };

    // Clean up empty fields
    if (!payload.principal_member_id) delete payload.principal_member_id;
    if (this.isPrincipal()) payload.relationship = 'self';

    if (this.isEditMode() && this.data.member) {
      this.store.updateMember(this.data.applicationId, this.data.member.id, payload).subscribe({
        next: () => {
          this.isSaving.set(false);
          this.dialogRef.close(true);
        },
        error: () => this.isSaving.set(false),
      });
    } else {
      this.store.addMember(this.data.applicationId, payload).subscribe({
        next: () => {
          this.isSaving.set(false);
          this.dialogRef.close(true);
        },
        error: () => this.isSaving.set(false),
      });
    }
  }
}
