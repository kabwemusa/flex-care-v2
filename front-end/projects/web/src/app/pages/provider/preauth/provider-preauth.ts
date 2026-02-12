import { Component, signal, OnInit } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Router, RouterModule } from '@angular/router';
import { DecimalPipe, DatePipe, TitleCasePipe } from '@angular/common';

interface PreAuthResult {
  id: string;
  preauth_number: string;
  status: string;
  priority: string;
  member: {
    member_number: string;
    first_name: string;
    last_name: string;
  };
  admission_date: string;
  estimated_discharge_date: string | null;
  diagnosis: string;
  total_estimated_amount: number;
  approved_amount: number | null;
  valid_until: string | null;
  created_at: string;
}

@Component({
  selector: 'app-provider-preauth',
  standalone: true,
  imports: [RouterModule, DecimalPipe, DatePipe, TitleCasePipe],
  templateUrl: './provider-preauth.html',
})
export class ProviderPreauthPage implements OnInit {
  // Search
  searchNumber = signal('');
  searchLoading = signal(false);
  searchError = signal('');
  searchResult = signal<PreAuthResult | null>(null);

  // New Pre-Auth Form
  showForm = signal(false);
  cardNumber = signal('');
  admissionDate = signal('');
  estimatedDischargeDate = signal('');
  diagnosis = signal('');
  procedureCode = signal('');
  estimatedAmount = signal('');
  notes = signal('');
  submitLoading = signal(false);
  submitError = signal('');
  submitResult = signal<PreAuthResult | null>(null);

  private apiKey = '';
  private apiSecret = '';

  constructor(private http: HttpClient, private router: Router) {}

  ngOnInit() {
    this.apiKey = localStorage.getItem('provider_api_key') || '';
    this.apiSecret = localStorage.getItem('provider_api_secret') || '';

    if (!this.apiKey || !this.apiSecret) {
      this.router.navigate(['/provider/login']);
    }

    // Default admission date to today
    this.admissionDate.set(new Date().toISOString().split('T')[0]);
  }

  private getHeaders(): HttpHeaders {
    return new HttpHeaders({
      'X-API-Key': this.apiKey,
      'X-API-Secret': this.apiSecret,
    });
  }

  searchPreAuth() {
    const num = this.searchNumber().trim();
    if (!num) return;

    this.searchLoading.set(true);
    this.searchError.set('');
    this.searchResult.set(null);

    this.http
      .get<{ data: PreAuthResult }>(`/v1/provider/preauth/${num}`, { headers: this.getHeaders() })
      .subscribe({
        next: (res) => {
          this.searchResult.set(res.data);
          this.searchLoading.set(false);
        },
        error: (err) => {
          this.searchError.set(err.error?.message || err.error?.error || 'Pre-authorization not found');
          this.searchLoading.set(false);
        },
      });
  }

  submitPreAuth() {
    if (!this.cardNumber().trim() || !this.diagnosis().trim() || !this.estimatedAmount()) return;

    this.submitLoading.set(true);
    this.submitError.set('');

    const payload = {
      card_number: this.cardNumber().trim(),
      admission_type: 'elective',
      admission_date: this.admissionDate(),
      estimated_discharge_date: this.estimatedDischargeDate() || null,
      diagnosis: this.diagnosis(),
      lines: [
        {
          service_type: 'procedure',
          procedure_code: this.procedureCode() || 'GENERAL',
          description: this.diagnosis(),
          estimated_amount: parseFloat(this.estimatedAmount()),
        },
      ],
      notes: this.notes() || null,
    };

    this.http
      .post<{ data: PreAuthResult }>('/v1/provider/preauth', payload, { headers: this.getHeaders() })
      .subscribe({
        next: (res) => {
          this.submitResult.set(res.data);
          this.submitLoading.set(false);
          this.showForm.set(false);
          // Clear form
          this.cardNumber.set('');
          this.diagnosis.set('');
          this.procedureCode.set('');
          this.estimatedAmount.set('');
          this.notes.set('');
        },
        error: (err) => {
          this.submitError.set(err.error?.message || err.error?.error || 'Failed to submit pre-authorization');
          this.submitLoading.set(false);
        },
      });
  }

  inputValue(event: Event): string {
    return (event.target as HTMLInputElement).value;
  }

  statusClass(status: string): string {
    switch (status) {
      case 'approved':
        return 'bg-green-100 text-green-700';
      case 'pending':
        return 'bg-amber-100 text-amber-700';
      case 'declined':
        return 'bg-red-100 text-red-700';
      default:
        return 'bg-gray-100 text-gray-600';
    }
  }
}
