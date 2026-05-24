import { Component, signal, computed, OnInit, inject, DestroyRef } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { RouterModule } from '@angular/router';
import { DecimalPipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { MatSelectModule } from '@angular/material/select';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatNativeDateModule } from '@angular/material/core';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import {
  PublicMedicalService,
  QuoteResult,
  ApplyMember,
} from '../../services/public-medical.service';
import { ToastService } from '../../services/toast.service';
import { UiUtilsService } from '../../services/ui-utils.service';

// ── Saved quote shape (written by quote.ts → read here) ───────────────────────

interface SavedQuote {
  quote: QuoteResult;
  members: { member_type: string; age: number; gender: string; name: string }[];
  selectedAddonIds: string[];
  billingFrequency: string;
}

// ── Per-field validation errors for inline feedback ───────────────────────────

interface MemberErrors {
  first_name?: string;
  last_name?: string;
  date_of_birth?: string;
}

// ── Component ─────────────────────────────────────────────────────────────────

@Component({
  selector: 'app-apply',
  standalone: true,
  imports: [
    RouterModule,
    DecimalPipe,
    FormsModule,
    MatSelectModule,
    MatDatepickerModule,
    MatNativeDateModule,
    MatFormFieldModule,
    MatInputModule,
  ],
  templateUrl: './apply.html',
})
export class ApplyPage implements OnInit {
  private readonly medicalService = inject(PublicMedicalService);
  private readonly toast          = inject(ToastService);
  readonly ui                     = inject(UiUtilsService);
  private readonly destroyRef     = inject(DestroyRef);

  // ── Wizard state ──────────────────────────────────────────────────────────
  step = signal(1);

  // ── Loaded from localStorage ──────────────────────────────────────────────
  savedQuote  = signal<SavedQuote | null>(null);
  noQuoteData = signal(false);

  // ── Step 2: Member details ────────────────────────────────────────────────
  applyMembers  = signal<ApplyMember[]>([]);
  memberErrors  = signal<MemberErrors[]>([]);

  // ── Step 3: Contact & coverage ────────────────────────────────────────────
  contactName      = signal('');
  contactEmail     = signal('');
  contactPhone     = signal('');
  startDate        = signal('');
  billingFrequency = signal('monthly');
  acceptedTerms    = signal(false);
  contactErrors    = signal<{ email?: string; terms?: string }>({});

  // ── Step 4: Result ────────────────────────────────────────────────────────
  submitLoading     = signal(false);
  applicationResult = signal<{ id: string; application_number: string; status: string; plan: { name: string }; contact_email: string; total_premium: number } | null>(null);

  /** Total member count — used for the step-1 summary badge. */
  memberCount = computed(() => this.applyMembers().length);

  /**
   * Pure validity check — safe to use in templates and computed signals.
   * Does NOT write to any signal; use validateAndShowMemberErrors() when
   * you also need to surface per-field error messages.
   */
  readonly membersValid = computed(() =>
    this.applyMembers().every(
      (m) => m.first_name.trim() && m.last_name.trim() && m.date_of_birth,
    ),
  );

  /**
   * Pure contact-step validity check — safe to use in templates.
   * Does NOT write to any signal; use validateAndShowContactErrors() when
   * you also need to surface per-field error messages.
   */
  readonly contactValid = computed(
    () => this.contactEmail().includes('@') && this.acceptedTerms(),
  );

  // ── Lifecycle ────────────────────────────────────────────────────────────

  ngOnInit(): void {
    const raw = localStorage.getItem('flex_quote');
    if (!raw) {
      this.noQuoteData.set(true);
      return;
    }

    try {
      const data: SavedQuote = JSON.parse(raw);
      this.savedQuote.set(data);
      this.billingFrequency.set(data.billingFrequency);

      // Initialise member forms — split the single `name` from the quote step into first/last.
      const members: ApplyMember[] = data.members.map((m) => {
        const parts = (m.name || '').trim().split(/\s+/);
        return {
          member_type:   m.member_type,
          gender:        m.gender || 'M',
          first_name:    parts[0] || '',
          last_name:     parts.slice(1).join(' ') || '',
          date_of_birth: '',
          national_id:   null,
          email:         null,
          phone:         null,
        };
      });

      this.applyMembers.set(members);
      this.memberErrors.set(members.map(() => ({})));

      // Default start date: 30 days from today.
      const d = new Date();
      d.setDate(d.getDate() + 30);
      this.startDate.set(d.toISOString().split('T')[0]);
    } catch {
      this.noQuoteData.set(true);
    }
  }

  // ── Navigation ────────────────────────────────────────────────────────────

  canNavigateToStep(s: number): boolean {
    if (s <= 2) return true;
    if (s === 3) return this.membersValid();
    return false;
  }

  goToStep(s: number): void {
    if (s === 3 && !this.validateAndShowMemberErrors()) {
      this.toast.warning('Please complete all member details before continuing.');
      return;
    }
    if (s === 3) this.prefillContact();
    this.step.set(s);
  }

  // ── Validation ────────────────────────────────────────────────────────────

  /**
   * Validates all members and writes per-field errors into memberErrors.
   * Only call this imperatively (button clicks, form submit) — never from a
   * template expression, as it writes to a signal during change detection.
   */
  private validateAndShowMemberErrors(): boolean {
    const errors: MemberErrors[] = this.applyMembers().map((m) => {
      const e: MemberErrors = {};
      if (!m.first_name.trim())  e.first_name    = 'First name is required';
      if (!m.last_name.trim())   e.last_name     = 'Last name is required';
      if (!m.date_of_birth)      e.date_of_birth = 'Date of birth is required';
      return e;
    });

    this.memberErrors.set(errors);
    return errors.every((e) => Object.keys(e).length === 0);
  }

  /**
   * Validates the contact step and writes per-field errors into contactErrors.
   * Only call this imperatively (form submit) — never from a template expression.
   */
  private validateAndShowContactErrors(): boolean {
    const errors: { email?: string; terms?: string } = {};
    if (!this.contactEmail().includes('@')) {
      errors.email = 'Please enter a valid email address';
    }
    if (!this.acceptedTerms()) {
      errors.terms = 'You must accept the terms and conditions';
    }
    this.contactErrors.set(errors);
    return Object.keys(errors).length === 0;
  }

  // ── Step 2: Member field update ───────────────────────────────────────────

  updateApplyMember(index: number, field: keyof ApplyMember, value: string): void {
    this.applyMembers.update((members) =>
      members.map((m, i) => (i === index ? { ...m, [field]: value } : m))
    );

    // Clear the specific field error on input so feedback is immediate.
    this.memberErrors.update((errors) =>
      errors.map((e, i) => {
        if (i !== index) return e;
        const updated = { ...e };
        delete updated[field as keyof MemberErrors];
        return updated;
      })
    );
  }

  selectValue(event: Event): string {
    return (event.target as HTMLSelectElement).value;
  }

  // ── Step 3: Contact pre-fill ──────────────────────────────────────────────

  private prefillContact(): void {
    if (this.contactName()) return;
    const principal = this.applyMembers().find((m) => m.member_type === 'principal');
    if (principal) {
      this.contactName.set(`${principal.first_name} ${principal.last_name}`.trim());
    }
  }

  // ── Step 3: Submit ────────────────────────────────────────────────────────

  submitApplication(): void {
    if (!this.validateAndShowMemberErrors()) {
      this.toast.warning('Some member details are incomplete. Please review.');
      return;
    }

    if (!this.validateAndShowContactErrors()) return;

    const quote = this.savedQuote();
    if (!quote) return;

    this.submitLoading.set(true);

    this.medicalService
      .submitApplication({
        plan_id:             quote.quote.plan.id,
        members:             this.applyMembers(),
        addon_ids:           quote.selectedAddonIds,
        contact_name:        this.contactName() || null,
        contact_email:       this.contactEmail(),
        contact_phone:       this.contactPhone() || null,
        proposed_start_date: this.startDate(),
        billing_frequency:   this.billingFrequency(),
      })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (res) => {
          this.applicationResult.set(res.data);
          this.submitLoading.set(false);
          this.step.set(4);
          localStorage.removeItem('flex_quote');
          this.toast.success(
            `Application ${res.data.application_number} submitted successfully!`,
            'Application Received'
          );
        },
        error: (err) => {
          this.submitLoading.set(false);
          this.toast.error(
            err.error?.message ?? 'Failed to submit your application. Please try again.',
            'Submission Failed'
          );
        },
      });
  }

  // ── Date helpers for mat-datepicker ──────────────────────────────────────

  toDate(dateStr: string): Date | null {
    if (!dateStr) return null;
    return new Date(dateStr);
  }

  formatDate(date: Date | null): string {
    if (!date) return '';
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
  }
}
