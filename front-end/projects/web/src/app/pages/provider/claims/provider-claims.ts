import { Component, signal, OnInit } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Router, RouterModule } from '@angular/router';
import { DecimalPipe, DatePipe, TitleCasePipe } from '@angular/common';

interface ClaimResult {
  id: string;
  claim_number: string;
  status: string;
  claim_type: string;
  member: {
    member_number: string;
    first_name: string;
    last_name: string;
  };
  service_date: string;
  submitted_amount: number;
  approved_amount: number | null;
  copay_amount: number | null;
  payable_amount: number | null;
  rejection_reason: string | null;
  created_at: string;
}

@Component({
  selector: 'app-provider-claims',
  standalone: true,
  imports: [RouterModule, DecimalPipe, DatePipe, TitleCasePipe],
  templateUrl: './provider-claims.html',
})
export class ProviderClaimsPage implements OnInit {
  // Search
  searchNumber = signal('');
  searchLoading = signal(false);
  searchError = signal('');
  searchResult = signal<ClaimResult | null>(null);

  // New Claim Form
  showForm = signal(false);
  cardNumber = signal('');
  claimType = signal('outpatient');
  serviceDate = signal('');
  diagnosis = signal('');
  serviceCode = signal('');
  serviceDescription = signal('');
  amount = signal('');
  preauthNumber = signal('');
  submitLoading = signal(false);
  submitError = signal('');
  submitResult = signal<ClaimResult | null>(null);

  private apiKey = '';
  private apiSecret = '';

  constructor(private http: HttpClient, private router: Router) {}

  ngOnInit() {
    this.apiKey = localStorage.getItem('provider_api_key') || '';
    this.apiSecret = localStorage.getItem('provider_api_secret') || '';

    if (!this.apiKey || !this.apiSecret) {
      this.router.navigate(['/provider/login']);
    }

    this.serviceDate.set(new Date().toISOString().split('T')[0]);
  }

  private getHeaders(): HttpHeaders {
    return new HttpHeaders({
      'X-API-Key': this.apiKey,
      'X-API-Secret': this.apiSecret,
    });
  }

  searchClaim() {
    const num = this.searchNumber().trim();
    if (!num) return;

    this.searchLoading.set(true);
    this.searchError.set('');
    this.searchResult.set(null);

    this.http
      .get<{ data: ClaimResult }>(`/v1/provider/claims/${num}`, { headers: this.getHeaders() })
      .subscribe({
        next: (res) => {
          this.searchResult.set(res.data);
          this.searchLoading.set(false);
        },
        error: (err) => {
          this.searchError.set(err.error?.message || err.error?.error || 'Claim not found');
          this.searchLoading.set(false);
        },
      });
  }

  submitClaim() {
    if (!this.cardNumber().trim() || !this.diagnosis().trim() || !this.amount()) return;

    this.submitLoading.set(true);
    this.submitError.set('');

    const payload = {
      card_number: this.cardNumber().trim(),
      claim_type: this.claimType(),
      service_date: this.serviceDate(),
      preauth_number: this.preauthNumber().trim() || null,
      diagnoses: [{ code: 'ICD10', description: this.diagnosis() }],
      lines: [
        {
          service_code: this.serviceCode() || 'CONSULT',
          description: this.serviceDescription() || this.diagnosis(),
          quantity: 1,
          unit_price: parseFloat(this.amount()),
          amount: parseFloat(this.amount()),
        },
      ],
    };

    this.http
      .post<{ data: ClaimResult }>('/v1/provider/claims', payload, { headers: this.getHeaders() })
      .subscribe({
        next: (res) => {
          this.submitResult.set(res.data);
          this.submitLoading.set(false);
          this.showForm.set(false);
          // Clear form
          this.cardNumber.set('');
          this.diagnosis.set('');
          this.serviceCode.set('');
          this.serviceDescription.set('');
          this.amount.set('');
          this.preauthNumber.set('');
        },
        error: (err) => {
          this.submitError.set(err.error?.message || err.error?.error || 'Failed to submit claim');
          this.submitLoading.set(false);
        },
      });
  }

  inputValue(event: Event): string {
    return (event.target as HTMLInputElement).value;
  }

  selectValue(event: Event): string {
    return (event.target as HTMLSelectElement).value;
  }

  statusClass(status: string): string {
    switch (status) {
      case 'approved':
      case 'paid':
        return 'bg-green-100 text-green-700';
      case 'submitted':
      case 'processing':
        return 'bg-amber-100 text-amber-700';
      case 'rejected':
      case 'declined':
        return 'bg-red-100 text-red-700';
      default:
        return 'bg-gray-100 text-gray-600';
    }
  }
}
