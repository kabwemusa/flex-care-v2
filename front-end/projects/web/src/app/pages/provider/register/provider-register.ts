import { Component, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { RouterModule } from '@angular/router';

interface RegistrationResult {
  id: string;
  provider_code: string;
  name: string;
  status: string;
  email: string;
}

@Component({
  selector: 'app-provider-register',
  standalone: true,
  imports: [RouterModule],
  templateUrl: './provider-register.html',
})
export class ProviderRegisterPage {
  step = signal(1);

  // Step 1: Facility Info
  facilityName = signal('');
  tradingName = signal('');
  providerType = signal('hospital');
  licenseNumber = signal('');
  registrationNumber = signal('');

  // Step 2: Address & Contact
  address = signal('');
  city = signal('');
  region = signal('');
  country = signal('Zambia');
  postalCode = signal('');
  phone = signal('');
  email = signal('');
  website = signal('');

  // Step 3: Primary Contact & Services
  contactName = signal('');
  contactPhone = signal('');
  contactEmail = signal('');
  services = signal<string[]>([]);
  notes = signal('');
  acceptedTerms = signal(false);

  // Submission
  loading = signal(false);
  error = signal('');
  result = signal<RegistrationResult | null>(null);

  constructor(private http: HttpClient) {}

  goToStep(s: number) {
    if (s > this.step() + 1) return;
    if (s === 2 && !this.step1Valid()) return;
    if (s === 3 && !this.step2Valid()) return;
    this.step.set(s);
  }

  step1Valid(): boolean {
    return this.facilityName().trim().length > 0 && this.providerType().length > 0;
  }

  step2Valid(): boolean {
    return (
      this.address().trim().length > 0 &&
      this.city().trim().length > 0 &&
      this.phone().trim().length > 0 &&
      this.email().includes('@')
    );
  }

  step3Valid(): boolean {
    return (
      this.contactName().trim().length > 0 &&
      this.contactEmail().includes('@') &&
      this.acceptedTerms()
    );
  }

  toggleService(service: string) {
    this.services.update((arr) =>
      arr.includes(service) ? arr.filter((s) => s !== service) : [...arr, service]
    );
  }

  submit() {
    if (!this.step3Valid()) return;
    this.loading.set(true);
    this.error.set('');

    const payload = {
      name: this.facilityName(),
      trading_name: this.tradingName() || null,
      provider_type: this.providerType(),
      license_number: this.licenseNumber() || null,
      registration_number: this.registrationNumber() || null,
      address: this.address(),
      city: this.city(),
      region: this.region() || null,
      country: this.country(),
      postal_code: this.postalCode() || null,
      phone: this.phone(),
      email: this.email(),
      website: this.website() || null,
      contact_name: this.contactName(),
      contact_phone: this.contactPhone() || null,
      contact_email: this.contactEmail(),
      services: this.services().length ? this.services() : null,
      notes: this.notes() || null,
    };

    this.http
      .post<{ data: RegistrationResult }>('/v1/medical/public/providers/register', payload)
      .subscribe({
        next: (res) => {
          this.result.set(res.data);
          this.loading.set(false);
          this.step.set(4);
        },
        error: (err) => {
          this.error.set(err.error?.message ?? 'Registration failed. Please try again.');
          this.loading.set(false);
        },
      });
  }

  inputValue(event: Event): string {
    return (event.target as HTMLInputElement).value;
  }

  selectValue(event: Event): string {
    return (event.target as HTMLSelectElement).value;
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
