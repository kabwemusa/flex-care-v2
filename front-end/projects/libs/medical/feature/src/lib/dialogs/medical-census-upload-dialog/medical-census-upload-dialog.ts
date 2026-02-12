// libs/medical/feature/src/lib/dialogs/medical-census-upload-dialog/medical-census-upload-dialog.ts

import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators, FormsModule } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogModule, MatDialogRef } from '@angular/material/dialog';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatSelectModule } from '@angular/material/select';
import { MatInputModule } from '@angular/material/input';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatTableModule } from '@angular/material/table';
import { MatChipsModule } from '@angular/material/chips';
import { ApplicationStore, SchemeListStore, PlanListStore, RateCardListStore } from 'medical-data';
import { FeedbackService } from 'shared';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { provideNativeDateAdapter } from '@angular/material/core';

interface DialogData {
  group_id: string;
  group_name: string;
}

interface CensusImportResult {
  import_key: string;
  summary: {
    total_rows: number;
    valid_rows: number;
    invalid_rows: number;
    member_type_breakdown: Record<string, number>;
    has_errors: boolean;
  };
  preview: any[];
  errors?: any[];
}

@Component({
  selector: 'lib-medical-census-upload-dialog',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    FormsModule,
    MatDialogModule,
    MatButtonModule,
    MatIconModule,
    MatFormFieldModule,
    MatSelectModule,
    MatInputModule,
    MatProgressSpinnerModule,
    MatTableModule,
    MatChipsModule,
    MatDatepickerModule,
  ],
  providers: [provideNativeDateAdapter()],
  templateUrl: './medical-census-upload-dialog.html',
  styleUrls: ['./medical-census-upload-dialog.scss'],
})
export class MedicalCensusUploadDialog {
  readonly fb = inject(FormBuilder);
  readonly dialogRef = inject(MatDialogRef<MedicalCensusUploadDialog>);
  readonly data = inject<DialogData>(MAT_DIALOG_DATA);
  readonly applicationStore = inject(ApplicationStore);
  readonly schemeStore = inject(SchemeListStore);
  readonly planStore = inject(PlanListStore);
  readonly rateCardStore = inject(RateCardListStore);
  readonly feedback = inject(FeedbackService);

  // Form and state
  uploadForm!: FormGroup;
  applicationForm!: FormGroup;

  // Signals
  currentStep = signal<'upload' | 'preview' | 'configure'>('upload');
  uploading = signal(false);
  creating = signal(false);
  selectedFile = signal<File | null>(null);
  importResult = signal<CensusImportResult | null>(null);
  isDragOver = signal(false);

  // Data (loaded from permission-free lookup endpoints)
  readonly schemes = signal<{ id: string; name: string; code: string }[]>([]);
  readonly plans = signal<{ id: string; name: string; code: string }[]>([]);
  readonly rateCards = signal<{ id: string; name: string; code: string }[]>([]);

  readonly billingFrequencies = [
    { value: 'monthly', label: 'Monthly' },
    { value: 'quarterly', label: 'Quarterly' },
    { value: 'semi_annual', label: 'Semi-Annual' },
    { value: 'annual', label: 'Annual' },
  ];

  // Table columns for preview
  previewColumns = ['name', 'dob', 'gender', 'member_type', 'id_number'];

  constructor() {
    this.uploadForm = this.fb.group({
      file: [null, Validators.required],
    });

    this.applicationForm = this.fb.group({
      scheme_id: ['', Validators.required],
      plan_id: [{ value: '', disabled: true }, Validators.required],
      rate_card_id: [{ value: '', disabled: true }, Validators.required],
      inception_date: ['', Validators.required],
      billing_frequency: ['monthly', Validators.required],
    });

    // Load schemes using permission-free lookup endpoint
    this.schemeStore.loadDropdown().subscribe({
      next: (res) => this.schemes.set(res.data.map((s: any) => ({ id: s.id, name: s.name, code: s.code }))),
    });

    // Watch scheme changes to load plans
    this.applicationForm.get('scheme_id')?.valueChanges.subscribe((schemeId) => {
      const planControl = this.applicationForm.get('plan_id');
      const rateCardControl = this.applicationForm.get('rate_card_id');
      if (schemeId) {
        this.loadPlansForScheme(schemeId);
        planControl?.enable();
      } else {
        this.plans.set([]);
        this.rateCards.set([]);
        planControl?.disable();
        rateCardControl?.disable();
        this.applicationForm.patchValue({ plan_id: '', rate_card_id: '' });
      }
    });

    // Watch plan changes to load rate cards
    this.applicationForm.get('plan_id')?.valueChanges.subscribe((planId) => {
      const rateCardControl = this.applicationForm.get('rate_card_id');
      if (planId) {
        this.loadRateCardsForPlan(planId);
        rateCardControl?.enable();
      } else {
        this.rateCards.set([]);
        rateCardControl?.disable();
        this.applicationForm.patchValue({ rate_card_id: '' });
      }
    });
  }

  private loadPlansForScheme(schemeId: string) {
    // Use permission-free lookup endpoint
    this.planStore.loadDropdown(schemeId).subscribe({
      next: (res) => {
        if (res.data) {
          this.plans.set(res.data.map((p: any) => ({ id: p.id, name: p.name, code: p.code })));
        }
      },
    });
  }

  private loadRateCardsForPlan(planId: string) {
    // Use permission-free lookup endpoint
    this.rateCardStore.loadDropdown(planId).subscribe({
      next: (res) => {
        if (res.data) {
          this.rateCards.set(res.data.map((r: any) => ({ id: r.id, name: r.name, code: r.code })));

          // Auto-select if only one rate card
          if (res.data.length === 1 && !this.applicationForm.get('rate_card_id')?.value) {
            this.applicationForm.patchValue({ rate_card_id: res.data[0].id });
          }
        }
      },
    });
  }

  onFileSelected(event: Event) {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files.length > 0) {
      this.handleFile(input.files[0]);
    }
  }

  onFileDrop(event: DragEvent) {
    event.preventDefault();
    event.stopPropagation();
    this.isDragOver.set(false);

    if (event.dataTransfer?.files && event.dataTransfer.files.length > 0) {
      this.handleFile(event.dataTransfer.files[0]);
    }
  }

  onDragOver(event: DragEvent) {
    event.preventDefault();
    event.stopPropagation();
    this.isDragOver.set(true);
  }

  onDragLeave(event: DragEvent) {
    event.preventDefault();
    event.stopPropagation();
    this.isDragOver.set(false);
  }

  handleFile(file: File) {
    const validExtensions = ['csv', 'xlsx', 'xls'];
    const extension = file.name.split('.').pop()?.toLowerCase();

    if (!extension || !validExtensions.includes(extension)) {
      this.feedback.error('Invalid file type. Please upload CSV or Excel file.');
      return;
    }

    if (file.size > 10 * 1024 * 1024) {
      // 10MB
      this.feedback.error('File size exceeds 10MB limit.');
      return;
    }

    this.selectedFile.set(file);
    this.uploadForm.patchValue({ file });
  }

  uploadCensus() {
    if (!this.selectedFile()) {
      this.feedback.error('Please select a file to upload.');
      return;
    }

    this.uploading.set(true);

    const formData = new FormData();
    formData.append('file', this.selectedFile()!);
    formData.append('group_id', this.data.group_id);

    this.applicationStore.importCensus(formData).subscribe({
      next: (response) => {
        this.uploading.set(false);

        if (response.data.summary.has_errors) {
          this.feedback.error(
            `Census file has ${response.data.summary.invalid_rows} invalid rows. Please review and correct.`
          );
        } else {
          this.feedback.success(
            `Census imported: ${response.data.summary.valid_rows} valid members found.`
          );
        }

        this.importResult.set(response.data);
        this.currentStep.set('preview');
      },
      error: (err) => {
        this.uploading.set(false);
        this.feedback.error(err?.error?.message || 'Failed to import census file.');
      },
    });
  }

  goToConfiguration() {
    this.currentStep.set('configure');
  }

  backToPreview() {
    this.currentStep.set('preview');
  }

  backToUpload() {
    this.currentStep.set('upload');
    this.selectedFile.set(null);
    this.importResult.set(null);
  }

  createApplication() {
    if (this.applicationForm.invalid) {
      this.feedback.error('Please fill in all required fields.');
      return;
    }

    const result = this.importResult();
    if (!result) {
      this.feedback.error('No census data available.');
      return;
    }

    this.creating.set(true);

    const data = {
      import_key: result.import_key,
      ...this.applicationForm.value,
    };

    this.applicationStore.createFromCensus(data).subscribe({
      next: (response) => {
        this.creating.set(false);
        this.feedback.success(
          `Application created with ${result.summary.valid_rows} members successfully!`
        );
        this.dialogRef.close(response.data);
      },
      error: (err) => {
        this.creating.set(false);
        this.feedback.error(err?.error?.message || 'Failed to create application from census.');
      },
    });
  }

  cancel() {
    this.dialogRef.close();
  }

  getMemberTypeBreakdown() {
    const breakdown = this.importResult()?.summary.member_type_breakdown || {};
    return Object.entries(breakdown).map(([type, count]) => ({
      type: type.charAt(0).toUpperCase() + type.slice(1),
      count,
    }));
  }

  downloadTemplate() {
    const csvContent = `first_name,last_name,date_of_birth,gender,email,phone,id_number,employee_number,member_type,relationship,principal_id_number
John,Doe,1985-06-15,M,john.doe@company.com,+260971234567,123456/78/1,EMP001,principal,,
Jane,Doe,1987-03-20,F,jane.doe@company.com,,234567/89/1,,spouse,spouse,123456/78/1
Jimmy,Doe,2010-05-10,M,,,345678/90/1,,child,child,123456/78/1
Sarah,Smith,1990-08-25,F,sarah.smith@company.com,+260973456789,456789/01/1,EMP002,principal,,
Tom,Smith,2015-02-14,M,,,567890/12/1,,child,child,456789/01/1
Michael,Johnson,1978-11-30,M,michael.j@company.com,+260974567890,678901/23/1,EMP003,principal,,
Lisa,Johnson,1980-05-18,F,lisa.j@company.com,,789012/34/1,,spouse,spouse,678901/23/1
Emma,Johnson,2012-09-22,F,,,890123/45/1,,child,child,678901/23/1
David,Brown,1992-03-08,M,david.brown@company.com,+260975678901,901234/56/1,EMP004,principal,,
Robert,Wilson,1988-07-14,M,robert.w@company.com,+260976789012,012345/67/1,EMP005,principal,,
Mary,Wilson,1990-12-25,F,mary.w@company.com,,123456/89/1,,spouse,spouse,012345/67/1`;

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);

    link.setAttribute('href', url);
    link.setAttribute('download', 'census-template.csv');
    link.style.visibility = 'hidden';

    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  }
}
