import { Component, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router, RouterModule } from '@angular/router';

@Component({
  selector: 'app-provider-login',
  standalone: true,
  imports: [RouterModule],
  templateUrl: './provider-login.html',
})
export class ProviderLoginPage {
  apiKey = signal('');
  apiSecret = signal('');
  loading = signal(false);
  error = signal('');

  constructor(private http: HttpClient, private router: Router) {}

  login() {
    if (!this.apiKey() || !this.apiSecret()) return;
    this.loading.set(true);
    this.error.set('');

    // Verify credentials by calling the ping endpoint
    this.http
      .get<{ data: { provider_name: string } }>('/v1/provider/account/ping', {
        headers: {
          'X-API-Key': this.apiKey(),
          'X-API-Secret': this.apiSecret(),
        },
      })
      .subscribe({
        next: (res) => {
          localStorage.setItem('provider_api_key', this.apiKey());
          localStorage.setItem('provider_api_secret', this.apiSecret());
          localStorage.setItem('provider_name', res.data?.provider_name || '');
          this.loading.set(false);
          this.router.navigate(['/provider']);
        },
        error: (err) => {
          const msg = err.error?.message || err.error?.error || 'Invalid API credentials';
          this.error.set(msg);
          this.loading.set(false);
        },
      });
  }

  inputValue(event: Event): string {
    return (event.target as HTMLInputElement).value;
  }
}
