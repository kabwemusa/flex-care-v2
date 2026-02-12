import { Component, signal, OnInit } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { RouterModule } from '@angular/router';
import { DecimalPipe } from '@angular/common';
import { MatSelectModule } from '@angular/material/select';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatNativeDateModule } from '@angular/material/core';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';

// ── Interfaces ────────────────────────────────────────────────────────────

interface SavedQuote {
  quote: {
    plan: { id: string; code: string; name: string };
    rate_card: { id: string; code: string; version: string };
    members: { member_type: string; age: number; gender: string | null; premium: number }[];
    addons: { name: string; amount: number }[];
    base_premium: number;
    addon_premium: number;
    total_discount: number;
    total_loading: number;
    final_premium: number;
    currency: string;
    frequency: string;
  };
  members: { member_type: string; age: number; gender: string; name: string }[];
  selectedAddonIds: string[];
  billingFrequency: string;
}

interface ApplyMember {
  member_type: string;
  gender: string;
  first_name: string;
  last_name: string;
  date_of_birth: string;
  national_id: string;
  email: string;
  phone: string;
}

interface ApplicationResult {
  id: string;
  application_number: string;
  status: string;
  plan: { id: string; name: string };
  contact_email: string;
  proposed_start_date: string;
  member_count: number;
  total_premium: number;
}

// ── Component ─────────────────────────────────────────────────────────────

@Component({
  selector: 'app-apply',
  standalone: true,
  imports: [
    RouterModule,
    DecimalPipe,
    MatSelectModule,
    MatDatepickerModule,
    MatNativeDateModule,
    MatFormFieldModule,
    MatInputModule,
  ],
  templateUrl: './apply.html',
})
export class ApplyPage implements OnInit {
  step = signal(1);

  // Loaded from localStorage
  savedQuote = signal<SavedQuote | null>(null);
  noQuoteData = signal(false);

  // Step 2: Member details
  applyMembers = signal<ApplyMember[]>([]);

  // Step 3: Contact & coverage
  contactName = signal('');
  contactEmail = signal('');
  contactPhone = signal('');
  startDate = signal('');
  billingFrequency = signal('monthly');
  acceptedTerms = signal(false);

  // Step 4: Result
  submitLoading = signal(false);
  submitError = signal('');
  applicationResult = signal<ApplicationResult | null>(null);

  constructor(private http: HttpClient) {}

  ngOnInit() {
    const raw = localStorage.getItem('flex_quote');
    if (!raw) {
      this.noQuoteData.set(true);
      return;
    }

    try {
      const data: SavedQuote = JSON.parse(raw);
      this.savedQuote.set(data);
      this.billingFrequency.set(data.billingFrequency);

      // Initialize member forms — split the name field from the quote step
      this.applyMembers.set(
        data.members.map((m) => {
          const parts = (m.name || '').trim().split(/\s+/);
          return {
            member_type: m.member_type,
            gender: m.gender || 'M',
            first_name: parts[0] || '',
            last_name: parts.slice(1).join(' ') || '',
            date_of_birth: '',
            national_id: '',
            email: '',
            phone: '',
          };
        })
      );

      // Default start date: 30 days from now
      const d = new Date();
      d.setDate(d.getDate() + 30);
      this.startDate.set(d.toISOString().split('T')[0]);
    } catch {
      this.noQuoteData.set(true);
    }
  }

  // ── Navigation ───────────────────────────────────────────────────────────

  canNavigateToStep(s: number): boolean {
    if (s === 1) return true; // Can always go back to step 1
    if (s === 2) return true; // Can always go to step 2 (review to details)
    if (s === 3) return this.membersValid(); // Need valid member details
    return false;
  }

  goToStep(s: number) {
    if (!this.canNavigateToStep(s)) return;
    if (s === 3) this.prefillContact(); // Auto-fill contact name when entering step 3
    this.step.set(s);
  }

  // ── Validation ───────────────────────────────────────────────────────────

  membersValid(): boolean {
    return this.applyMembers().every((m) => m.first_name.trim() && m.last_name.trim() && m.date_of_birth);
  }

  contactValid(): boolean {
    return this.contactEmail().includes('@') && this.acceptedTerms();
  }

  // ── Step 2: Member updates ───────────────────────────────────────────────

  updateApplyMember(index: number, field: string, value: string) {
    this.applyMembers.update((members) =>
      members.map((m, i) => (i === index ? { ...m, [field]: value } : m))
    );
  }

  selectValue(event: Event): string {
    return (event.target as HTMLSelectElement).value;
  }

  // ── Step 3: Contact pre-fill ─────────────────────────────────────────────

  private prefillContact() {
    if (this.contactName()) return;
    const principal = this.applyMembers().find((m) => m.member_type === 'principal');
    if (principal) {
      this.contactName.set(`${principal.first_name} ${principal.last_name}`.trim());
    }
  }

  // ── Step 3: Submit ───────────────────────────────────────────────────────

  submitApplication() {
    const quote = this.savedQuote();
    if (!quote) return;

    this.submitLoading.set(true);
    this.submitError.set('');

    const payload = {
      plan_id: quote.quote.plan.id,
      members: this.applyMembers().map((m) => ({
        member_type: m.member_type,
        first_name: m.first_name,
        last_name: m.last_name,
        date_of_birth: m.date_of_birth,
        gender: m.gender || null,
        national_id: m.national_id || null,
        email: m.email || null,
        phone: m.phone || null,
      })),
      addon_ids: quote.selectedAddonIds,
      contact_name: this.contactName() || null,
      contact_email: this.contactEmail(),
      contact_phone: this.contactPhone() || null,
      proposed_start_date: this.startDate(),
      billing_frequency: this.billingFrequency(),
    };

    this.http.post<{ data: ApplicationResult }>('/v1/medical/public/applications', payload).subscribe({
      next: (res) => {
        this.applicationResult.set(res.data);
        this.submitLoading.set(false);
        this.step.set(4);
        localStorage.removeItem('flex_quote');
      },
      error: (err) => {
        this.submitError.set(err.error?.message ?? 'Failed to submit application');
        this.submitLoading.set(false);
      },
    });
  }

  // ── Helpers ──────────────────────────────────────────────────────────────

  memberTypeLabel(type: string): string {
    const labels: Record<string, string> = {
      principal: 'Primary Member',
      spouse: 'Spouse',
      child: 'Child',
      parent: 'Parent',
    };
    return labels[type] ?? type;
  }

  frequencyLabel(f: string): string {
    const labels: Record<string, string> = {
      monthly: 'Monthly',
      quarterly: 'Quarterly',
      semi_annual: 'Semi-Annual',
      annual: 'Annual',
    };
    return labels[f] ?? f;
  }

  // ── Date helpers for mat-datepicker ─────────────────────────────────────

  toDate(dateStr: string): Date | null {
    if (!dateStr) return null;
    return new Date(dateStr);
  }

  formatDate(date: Date | null): string {
    if (!date) return '';
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }
}
