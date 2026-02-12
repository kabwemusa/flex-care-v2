import { Component, signal } from '@angular/core';
import { Router, RouterModule } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { DecimalPipe, DatePipe } from '@angular/common';
import { MemberPortalService } from '../../../services/member-portal.service';

interface ClaimForm {
  service_type: string;
  provider_name: string;
  service_date: string;
  amount: number | null;
  diagnosis: string;
  notes: string;
}

@Component({
  selector: 'app-portal-claim-submit',
  standalone: true,
  imports: [RouterModule, FormsModule, DecimalPipe, DatePipe],
  templateUrl: './claim-submit.html',
})
export class PortalClaimSubmit {
  form = signal<ClaimForm>({
    service_type: '',
    provider_name: '',
    service_date: '',
    amount: null,
    diagnosis: '',
    notes: '',
  });

  files = signal<File[]>([]);
  submitting = signal(false);
  error = signal('');
  step = signal(1);

  serviceTypes = [
    { value: 'consultation', label: 'Doctor Consultation' },
    { value: 'laboratory', label: 'Laboratory Tests' },
    { value: 'imaging', label: 'X-Ray / Imaging' },
    { value: 'pharmacy', label: 'Pharmacy / Medication' },
    { value: 'dental', label: 'Dental Services' },
    { value: 'optical', label: 'Optical Services' },
    { value: 'hospitalization', label: 'Hospitalization' },
    { value: 'surgery', label: 'Surgery' },
    { value: 'physiotherapy', label: 'Physiotherapy' },
    { value: 'maternity', label: 'Maternity' },
    { value: 'other', label: 'Other' },
  ];

  constructor(
    private portalService: MemberPortalService,
    private router: Router
  ) {}

  updateForm(field: keyof ClaimForm, value: any) {
    this.form.update((f) => ({ ...f, [field]: value }));
  }

  onFilesSelected(event: Event) {
    const input = event.target as HTMLInputElement;
    if (input.files) {
      const newFiles = Array.from(input.files);
      this.files.update((existing) => [...existing, ...newFiles]);
    }
    input.value = '';
  }

  removeFile(index: number) {
    this.files.update((files) => files.filter((_, i) => i !== index));
  }

  formatFileSize(bytes: number): string {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
  }

  nextStep() {
    if (this.step() === 1 && this.validateStep1()) {
      this.step.set(2);
    } else if (this.step() === 2) {
      this.step.set(3);
    }
  }

  prevStep() {
    if (this.step() > 1) {
      this.step.update((s) => s - 1);
    }
  }

  validateStep1(): boolean {
    const f = this.form();
    if (!f.service_type) {
      this.error.set('Please select a service type');
      return false;
    }
    if (!f.provider_name.trim()) {
      this.error.set('Please enter the provider name');
      return false;
    }
    if (!f.service_date) {
      this.error.set('Please enter the service date');
      return false;
    }
    if (!f.amount || f.amount <= 0) {
      this.error.set('Please enter a valid amount');
      return false;
    }
    this.error.set('');
    return true;
  }

  submitClaim() {
    if (!this.validateStep1()) {
      this.step.set(1);
      return;
    }

    if (this.files().length === 0) {
      this.error.set('Please upload at least one receipt or document');
      this.step.set(2);
      return;
    }

    this.submitting.set(true);
    this.error.set('');

    const formData = new FormData();
    const f = this.form();
    formData.append('service_type', f.service_type);
    formData.append('provider_name', f.provider_name);
    formData.append('service_date', f.service_date);
    formData.append('amount', String(f.amount));
    formData.append('diagnosis', f.diagnosis);
    formData.append('notes', f.notes);

    this.files().forEach((file, i) => {
      formData.append(`documents[${i}]`, file);
    });

    this.portalService.submitClaim(formData).subscribe({
      next: (res) => {
        this.submitting.set(false);
        this.router.navigate(['/portal/claims', res.data.id]);
      },
      error: (err) => {
        this.error.set(err.error?.message ?? 'Failed to submit claim');
        this.submitting.set(false);
      },
    });
  }
}
