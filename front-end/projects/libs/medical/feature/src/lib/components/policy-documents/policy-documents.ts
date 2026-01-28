// libs/medical/feature/src/lib/components/policy-documents/policy-documents.ts

import { Component, computed, effect, inject, input, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';

import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatSelectModule } from '@angular/material/select';
import { MatInputModule } from '@angular/material/input';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { MatTableModule } from '@angular/material/table';
import { MatTooltipModule } from '@angular/material/tooltip';

import { PolicyStore, DOCUMENT_TYPES } from 'medical-data';
import { FeedbackService } from 'shared';

@Component({
  selector: 'lib-policy-documents',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    MatButtonModule,
    MatIconModule,
    MatFormFieldModule,
    MatSelectModule,
    MatInputModule,
    MatProgressSpinnerModule,
    MatTableModule,
    MatTooltipModule,
  ],
  templateUrl: './policy-documents.html',
})
export class PolicyDocumentsComponent {
  private readonly fb = inject(FormBuilder);
  private readonly store = inject(PolicyStore);
  private readonly feedback = inject(FeedbackService);

  // Inputs
  readonly policyId = input.required<string>();
  readonly canEdit = input<boolean>(true);

  // State
  readonly documents = signal<any[]>([]);
  readonly isLoading = signal(false);
  readonly isUploading = signal(false);
  readonly selectedFile = signal<File | null>(null);

  readonly documentTypes = DOCUMENT_TYPES;
  readonly displayedColumns = [
    'document_type',
    'title',
    'file_name',
    'created_at',
    'actions',
  ];

  readonly uploadForm = this.fb.group({
    document_type: ['', Validators.required],
    title: ['', Validators.required],
    file: [null as File | null, Validators.required],
  });

  readonly hasDocuments = computed(() => this.documents().length > 0);

  constructor() {
    // Load documents when policy ID changes
    effect(() => {
      const policyId = this.policyId();
      if (policyId) {
        this.loadDocuments();
      }
    });
  }

  loadDocuments() {
    const policyId = this.policyId();
    if (!policyId) return;

    this.isLoading.set(true);
    this.store.loadDocuments(policyId).subscribe({
      next: (res) => {
        this.documents.set(res.data || []);
        this.isLoading.set(false);
      },
      error: () => {
        this.isLoading.set(false);
        this.feedback.error('Failed to load documents');
      },
    });
  }

  onFileSelected(event: Event) {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files.length > 0) {
      const file = input.files[0];
      this.selectedFile.set(file);
      this.uploadForm.patchValue({ file });

      // Auto-fill title with filename if empty
      if (!this.uploadForm.get('title')?.value) {
        const titleWithoutExt = file.name.replace(/\.[^/.]+$/, '');
        this.uploadForm.patchValue({ title: titleWithoutExt });
      }
    }
  }

  removeSelectedFile() {
    this.selectedFile.set(null);
    this.uploadForm.patchValue({ file: null });
  }

  uploadDocument() {
    if (this.uploadForm.invalid) {
      this.uploadForm.markAllAsTouched();
      return;
    }

    const formValue = this.uploadForm.value;
    const file = this.selectedFile();

    if (!file) {
      this.feedback.error('Please select a file');
      return;
    }

    const formData = new FormData();
    formData.append('file', file);
    formData.append('document_type', formValue.document_type!);
    formData.append('title', formValue.title!);

    this.isUploading.set(true);

    this.store.uploadDocument(this.policyId(), formData).subscribe({
      next: () => {
        this.feedback.success('Document uploaded successfully');
        this.uploadForm.reset();
        this.selectedFile.set(null);
        this.isUploading.set(false);
        this.loadDocuments();
      },
      error: (err) => {
        this.feedback.error(err?.error?.message || 'Failed to upload document');
        this.isUploading.set(false);
      },
    });
  }

  getDocumentTypeLabel(type: string): string {
    return this.documentTypes.find((t) => t.value === type)?.label || type;
  }

  formatFileSize(bytes: number): string {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
  }

  formatDate(date: string): string {
    return new Date(date).toLocaleDateString();
  }

  downloadDocument(doc: any) {
    this.store.downloadDocument(this.policyId(), doc.id).subscribe({
      next: (response) => {
        const blob = response.body;
        if (blob) {
          const url = window.URL.createObjectURL(blob);
          const link = document.createElement('a');
          link.href = url;
          link.download = doc.file_name;
          link.click();
          window.URL.revokeObjectURL(url);
        }
      },
      error: () => {
        this.feedback.error('Failed to download document');
      },
    });
  }

  previewDocument(doc: any) {
    const previewableTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];

    if (!previewableTypes.includes(doc.mime_type)) {
      this.downloadDocument(doc);
      return;
    }

    this.store.downloadDocument(this.policyId(), doc.id, true).subscribe({
      next: (response) => {
        const blob = response.body;
        if (blob) {
          const url = window.URL.createObjectURL(blob);
          window.open(url, '_blank');
          setTimeout(() => window.URL.revokeObjectURL(url), 1000);
        }
      },
      error: () => {
        this.feedback.error('Failed to preview document');
      },
    });
  }

  isPreviewable(doc: any): boolean {
    const previewableTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
    return previewableTypes.includes(doc.mime_type);
  }
}
