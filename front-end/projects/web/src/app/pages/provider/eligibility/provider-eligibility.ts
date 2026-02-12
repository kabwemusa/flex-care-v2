import { Component, signal, OnInit } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Router, RouterModule } from '@angular/router';
import { DecimalPipe, DatePipe, TitleCasePipe } from '@angular/common';

interface EligibilityResult {
  is_eligible: boolean;
  member: {
    id: string;
    member_number: string;
    first_name: string;
    last_name: string;
    date_of_birth: string;
    member_type: string;
    gender: string;
  };
  policy: {
    id: string;
    policy_number: string;
    status: string;
    start_date: string;
    end_date: string;
  };
  plan: {
    id: string;
    name: string;
    tier_level: string;
  };
  benefits: {
    benefit_code: string;
    benefit_name: string;
    is_covered: boolean;
    limit_amount: number | null;
    used_amount: number;
    available_amount: number | null;
    waiting_period_ends: string | null;
    in_waiting_period: boolean;
  }[];
  message: string;
}

@Component({
  selector: 'app-provider-eligibility',
  standalone: true,
  imports: [RouterModule, DecimalPipe, DatePipe, TitleCasePipe],
  templateUrl: './provider-eligibility.html',
})
export class ProviderEligibilityPage implements OnInit {
  cardNumber = signal('');
  loading = signal(false);
  error = signal('');
  result = signal<EligibilityResult | null>(null);

  private apiKey = '';
  private apiSecret = '';

  constructor(private http: HttpClient, private router: Router) {}

  ngOnInit() {
    this.apiKey = localStorage.getItem('provider_api_key') || '';
    this.apiSecret = localStorage.getItem('provider_api_secret') || '';

    if (!this.apiKey || !this.apiSecret) {
      this.router.navigate(['/provider/login']);
    }
  }

  private getHeaders(): HttpHeaders {
    return new HttpHeaders({
      'X-API-Key': this.apiKey,
      'X-API-Secret': this.apiSecret,
    });
  }

  checkEligibility() {
    const card = this.cardNumber().trim();
    if (!card) return;

    this.loading.set(true);
    this.error.set('');
    this.result.set(null);

    this.http
      .post<{ data: EligibilityResult }>(
        '/v1/provider/eligibility/check',
        {
          card_number: card,
          service_codes: [],
          service_date: new Date().toISOString().split('T')[0],
        },
        { headers: this.getHeaders() }
      )
      .subscribe({
        next: (res) => {
          this.result.set(res.data);
          this.loading.set(false);
        },
        error: (err) => {
          this.error.set(err.error?.message || err.error?.error || 'Failed to check eligibility');
          this.loading.set(false);
        },
      });
  }

  inputValue(event: Event): string {
    return (event.target as HTMLInputElement).value;
  }

  memberTypeLabel(type: string): string {
    const labels: Record<string, string> = {
      principal: 'Principal Member',
      spouse: 'Spouse',
      child: 'Child',
      parent: 'Parent',
    };
    return labels[type] ?? type;
  }
}
