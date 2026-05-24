import { Component, signal, computed, inject, DestroyRef } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Router, RouterModule } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { DecimalPipe, DatePipe, TitleCasePipe } from '@angular/common';
import { MemberPortalService } from '../../../services/member-portal.service';
import { ToastService } from '../../../services/toast.service';
import { UiUtilsService } from '../../../services/ui-utils.service';

interface ClaimForm {
  service_type:  string;
  provider_name: string;
  service_date:  string;
  amount:        number | null;
  diagnosis:     string;
  notes:         string;
}

/** Per-field validation errors for inline UX feedback. */
interface ClaimFormErrors {
  service_type?:  string;
  provider_name?: string;
  service_date?:  string;
  amount?:        string;
  documents?:     string;
}

// ── Service types for the selector ────────────────────────────────────────────

const SERVICE_TYPES: { value: string; label: string; icon: string }[] = [
  { value: 'consultation',   label: 'Doctor Consultation',   icon: 'stethoscope' },
  { value: 'laboratory',     label: 'Laboratory Tests',       icon: 'biotech' },
  { value: 'imaging',        label: 'X-Ray / Imaging',        icon: 'radiology' },
  { value: 'pharmacy',       label: 'Pharmacy / Medication',  icon: 'medication' },
  { value: 'dental',         label: 'Dental Services',        icon: 'dentistry' },
  { value: 'optical',        label: 'Optical Services',       icon: 'visibility' },
  { value: 'hospitalization',label: 'Hospitalization',        icon: 'local_hospital' },
  { value: 'surgery',        label: 'Surgery',                icon: 'emergency' },
  { value: 'physiotherapy',  label: 'Physiotherapy',          icon: 'self_improvement' },
  { value: 'maternity',      label: 'Maternity',              icon: 'pregnant_woman' },
  { value: 'other',          label: 'Other',                  icon: 'more_horiz' },
];

@Component({
  selector: 'app-portal-claim-submit',
  standalone: true,
  imports: [RouterModule, FormsModule, DecimalPipe, DatePipe, TitleCasePipe],
  templateUrl: './claim-submit.html',
})
export class PortalClaimSubmit {
  private readonly portalService = inject(MemberPortalService);
  private readonly router        = inject(Router);
  private readonly toast         = inject(ToastService);
  private readonly destroyRef    = inject(DestroyRef);
  readonly ui                    = inject(UiUtilsService);

  readonly serviceTypes = SERVICE_TYPES;

  form = signal<ClaimForm>({
    service_type:  '',
    provider_name: '',
    service_date:  '',
    amount:        null,
    diagnosis:     '',
    notes:         '',
  });

  files       = signal<File[]>([]);
  errors      = signal<ClaimFormErrors>({});
  submitting  = signal(false);
  step        = signal(1);

  /** True only when all required step-1 fields are filled — drives "Next" button state. */
  step1Complete = computed(() => {
    const f = this.form();
    return !!(f.service_type && f.provider_name.trim() && f.service_date && f.amount && f.amount > 0);
  });

  // ── Form updates ──────────────────────────────────────────────────────────

  updateForm(field: keyof ClaimForm, value: string | number | null): void {
    this.form.update((f) => ({ ...f, [field]: value }));
    // Clear field-specific error as user types.
    this.errors.update((e) => {
      const updated = { ...e };
      delete updated[field as keyof ClaimFormErrors];
      return updated;
    });
  }

  // ── File handling ─────────────────────────────────────────────────────────

  onFilesSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    if (input.files) {
      this.files.update((existing) => [...existing, ...Array.from(input.files!)]);
    }
    input.value = '';
    // Clear documents error once user adds a file.
    if (this.files().length > 0) {
      this.errors.update((e) => { const u = { ...e }; delete u.documents; return u; });
    }
  }

  removeFile(index: number): void {
    this.files.update((files) => files.filter((_, i) => i !== index));
  }

  formatFileSize(bytes: number): string {
    if (bytes < 1024)             return `${bytes} B`;
    if (bytes < 1024 * 1024)      return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
  }

  // ── Step navigation ───────────────────────────────────────────────────────

  nextStep(): void {
    if (this.step() === 1) {
      if (!this.validateStep1()) return; // stays on step 1 with inline errors
      this.step.set(2);
    } else if (this.step() === 2) {
      if (this.files().length === 0) {
        this.errors.update((e) => ({
          ...e,
          documents: 'Please upload at least one receipt or supporting document',
        }));
        return;
      }
      this.step.set(3);
    }
  }

  prevStep(): void {
    if (this.step() > 1) this.step.update((s) => s - 1);
  }

  // ── Validation ────────────────────────────────────────────────────────────

  /** Validates step-1 fields and populates inline errors. Returns true if valid. */
  validateStep1(): boolean {
    const f = this.form();
    const e: ClaimFormErrors = {};

    if (!f.service_type)             e.service_type  = 'Please select a service type';
    if (!f.provider_name.trim())     e.provider_name = 'Please enter the provider/facility name';
    if (!f.service_date)             e.service_date  = 'Please enter the date the service was received';
    if (!f.amount || f.amount <= 0)  e.amount        = 'Please enter a valid amount greater than zero';

    this.errors.set(e);
    return Object.keys(e).length === 0;
  }

  // ── Submit ────────────────────────────────────────────────────────────────

  submitClaim(): void {
    // Final guard — in case user reached step 3 via back button without fixing issues.
    if (!this.validateStep1()) {
      this.step.set(1);
      this.toast.warning('Please complete the claim details before submitting.');
      return;
    }

    if (this.files().length === 0) {
      this.step.set(2);
      this.errors.update((e) => ({
        ...e,
        documents: 'Please upload at least one receipt or supporting document',
      }));
      this.toast.warning('Supporting documents are required.');
      return;
    }

    this.submitting.set(true);

    const formData = new FormData();
    const f        = this.form();

    formData.append('service_type',  f.service_type);
    formData.append('provider_name', f.provider_name);
    formData.append('service_date',  f.service_date);
    formData.append('amount',        String(f.amount));
    if (f.diagnosis) formData.append('diagnosis', f.diagnosis);
    if (f.notes)     formData.append('notes',     f.notes);

    this.files().forEach((file, i) => formData.append(`documents[${i}]`, file));

    this.portalService.submitClaim(formData)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (res) => {
          this.submitting.set(false);
          this.toast.success('Your claim has been submitted successfully.', 'Claim Received');
          this.router.navigate(['/portal/claims', res.data.id]);
        },
        error: (err) => {
          this.submitting.set(false);
          this.toast.error(
            err.error?.message ?? 'Failed to submit claim. Please try again.',
            'Submission Failed'
          );
        },
      });
  }
}
