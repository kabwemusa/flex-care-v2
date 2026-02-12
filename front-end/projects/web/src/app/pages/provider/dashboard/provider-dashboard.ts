import { Component, signal, OnInit } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Router, RouterModule } from '@angular/router';
import { DecimalPipe, TitleCasePipe } from '@angular/common';

interface ProviderProfile {
  id: string;
  provider_code: string;
  name: string;
  trading_name: string | null;
  provider_type: string;
  status: string;
  is_network_provider: boolean;
  network_tier: string | null;
  email: string;
  phone: string;
  address: string;
  city: string;
  total_claims_count: number;
  approved_claims_count: number;
  total_claims_amount: number;
}

@Component({
  selector: 'app-provider-dashboard',
  standalone: true,
  imports: [RouterModule, DecimalPipe, TitleCasePipe],
  templateUrl: './provider-dashboard.html',
})
export class ProviderDashboardPage implements OnInit {
  profile = signal<ProviderProfile | null>(null);
  loading = signal(true);
  error = signal('');

  constructor(private http: HttpClient, private router: Router) {}

  ngOnInit() {
    const apiKey = localStorage.getItem('provider_api_key');
    const apiSecret = localStorage.getItem('provider_api_secret');

    if (!apiKey || !apiSecret) {
      this.router.navigate(['/provider/login']);
      return;
    }

    this.http
      .get<{ data: ProviderProfile }>('/v1/provider/account/profile', {
        headers: new HttpHeaders({
          'X-API-Key': apiKey,
          'X-API-Secret': apiSecret,
        }),
      })
      .subscribe({
        next: (res) => {
          this.profile.set(res.data);
          this.loading.set(false);
        },
        error: (err) => {
          if (err.status === 401 || err.status === 403) {
            localStorage.removeItem('provider_api_key');
            localStorage.removeItem('provider_api_secret');
            this.router.navigate(['/provider/login']);
          } else {
            this.error.set(err.error?.message ?? 'Failed to load profile');
            this.loading.set(false);
          }
        },
      });
  }

  logout() {
    localStorage.removeItem('provider_api_key');
    localStorage.removeItem('provider_api_secret');
    localStorage.removeItem('provider_name');
    this.router.navigate(['/provider/login']);
  }

  providerTypeLabel(type: string): string {
    const labels: Record<string, string> = {
      hospital: 'Hospital',
      clinic: 'Clinic',
      pharmacy: 'Pharmacy',
      lab: 'Laboratory',
      optical: 'Optical Center',
      dental: 'Dental Clinic',
      specialist: 'Specialist Practice',
    };
    return labels[type] ?? type;
  }
}
