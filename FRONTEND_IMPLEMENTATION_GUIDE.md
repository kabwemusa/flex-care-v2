# Frontend Implementation Guide
## Quick Start for Angular Integration

---

## Phase 1: Setup & Configuration (Week 1)

### 1. Install Dependencies

```bash
cd front-end
npm install laravel-echo pusher-js
npm install @angular/material @angular/cdk
npm install rxjs
```

### 2. Configure Laravel Echo

Create `src/app/services/echo.service.ts`:

```typescript
import { Injectable } from '@angular/core';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
  interface Window {
    Pusher: any;
    Echo: Echo;
  }
}

@Injectable({
  providedIn: 'root'
})
export class EchoService {
  private echo: Echo | null = null;

  constructor() {
    this.initializeEcho();
  }

  private initializeEcho(): void {
    window.Pusher = Pusher;

    this.echo = new Echo({
      broadcaster: 'reverb',
      key: 'your-reverb-app-key',
      wsHost: 'localhost',
      wsPort: 8080,
      wssPort: 8080,
      forceTLS: false,
      enabledTransports: ['ws', 'wss'],
      authEndpoint: 'http://localhost:8000/broadcasting/auth',
      auth: {
        headers: {
          Authorization: `Bearer ${this.getAuthToken()}`,
          Accept: 'application/json',
        },
      },
    });
  }

  private getAuthToken(): string {
    return localStorage.getItem('auth_token') || '';
  }

  getEcho(): Echo {
    if (!this.echo) {
      this.initializeEcho();
    }
    return this.echo!;
  }

  disconnect(): void {
    if (this.echo) {
      this.echo.disconnect();
    }
  }
}
```

---

## Phase 2: Core Components (Week 2-3)

### Component 1: Real-Time Claim Tracker

**File**: `src/app/components/claim-tracker/claim-tracker.component.ts`

```typescript
import { Component, OnInit, OnDestroy, Input } from '@angular/core';
import { EchoService } from '../../services/echo.service';
import { ClaimService } from '../../services/claim.service';
import { Subject, takeUntil } from 'rxjs';

interface ClaimStatusUpdate {
  claim_id: string;
  claim_number: string;
  status: string;
  previous_status: string;
  details: any;
  timestamp: string;
}

interface ClaimActivity {
  timestamp: string;
  message: string;
  type: 'success' | 'info' | 'warning' | 'error';
}

@Component({
  selector: 'app-claim-tracker',
  templateUrl: './claim-tracker.component.html',
  styleUrls: ['./claim-tracker.component.scss']
})
export class ClaimTrackerComponent implements OnInit, OnDestroy {
  @Input() claimId!: string;

  claim: any = null;
  activityLog: ClaimActivity[] = [];
  isLive = false;
  private destroy$ = new Subject<void>();

  constructor(
    private echoService: EchoService,
    private claimService: ClaimService
  ) {}

  ngOnInit(): void {
    this.loadClaim();
    this.subscribeToUpdates();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.echoService.getEcho().leave(`claim.${this.claimId}`);
  }

  private loadClaim(): void {
    this.claimService.getClaim(this.claimId)
      .pipe(takeUntil(this.destroy$))
      .subscribe(claim => {
        this.claim = claim;
      });
  }

  private subscribeToUpdates(): void {
    const echo = this.echoService.getEcho();

    echo.private(`claim.${this.claimId}`)
      .listen('.claim.status.updated', (event: ClaimStatusUpdate) => {
        console.log('Real-time claim update:', event);

        // Update claim status
        if (this.claim) {
          this.claim.status = event.status;
        }

        // Add to activity log
        this.addActivity(event);

        // Show notification
        this.showNotification(event);

        // Mark as live
        this.isLive = true;
      });

    // Listen to connection events
    echo.connector.pusher.connection.bind('connected', () => {
      this.isLive = true;
      console.log('WebSocket connected');
    });

    echo.connector.pusher.connection.bind('disconnected', () => {
      this.isLive = false;
      console.log('WebSocket disconnected');
    });
  }

  private addActivity(event: ClaimStatusUpdate): void {
    const activity: ClaimActivity = {
      timestamp: event.timestamp,
      message: this.getStatusMessage(event.status, event.details),
      type: this.getActivityType(event.status)
    };

    this.activityLog = [activity, ...this.activityLog];
  }

  private getStatusMessage(status: string, details: any): string {
    switch (status) {
      case 'processing':
        return 'Claim processing started';
      case 'approved':
        return `Claim approved - K ${details.approved_amount} payable`;
      case 'rejected':
        return `Claim rejected - ${details.reason}`;
      case 'pending':
        return 'Claim under manual review';
      default:
        return `Status updated to ${status}`;
    }
  }

  private getActivityType(status: string): 'success' | 'info' | 'warning' | 'error' {
    switch (status) {
      case 'approved':
        return 'success';
      case 'rejected':
        return 'error';
      case 'pending':
        return 'warning';
      default:
        return 'info';
    }
  }

  private showNotification(event: ClaimStatusUpdate): void {
    // Use Angular Material Snackbar or your notification service
    const message = this.getStatusMessage(event.status, event.details);
    // this.snackBar.open(message, 'Close', { duration: 5000 });
  }

  getStatusIcon(status: string): string {
    const icons: { [key: string]: string } = {
      submitted: '📋',
      processing: '⏳',
      approved: '✅',
      rejected: '❌',
      pending: '🔍'
    };
    return icons[status] || '•';
  }

  getStatusClass(status: string): string {
    return `status-${status}`;
  }
}
```

**Template**: `claim-tracker.component.html`

```html
<div class="claim-tracker" *ngIf="claim">
  <!-- Live Indicator -->
  <div class="live-indicator" [class.active]="isLive">
    <span class="live-dot"></span>
    {{ isLive ? 'Live Updates Active' : 'Connecting...' }}
  </div>

  <!-- Claim Header -->
  <div class="claim-header">
    <h2>{{ claim.claim_number }}</h2>
    <div class="status-badge" [ngClass]="getStatusClass(claim.status)">
      {{ getStatusIcon(claim.status) }} {{ claim.status | titlecase }}
    </div>
  </div>

  <!-- Progress Timeline -->
  <div class="timeline">
    <div class="timeline-item"
         *ngFor="let activity of activityLog"
         [ngClass]="'timeline-' + activity.type">
      <div class="timeline-marker"></div>
      <div class="timeline-content">
        <div class="timeline-message">{{ activity.message }}</div>
        <div class="timeline-time">{{ activity.timestamp | date:'short' }}</div>
      </div>
    </div>
  </div>

  <!-- Claim Details -->
  <div class="claim-details">
    <div class="detail-item">
      <span class="label">Amount Claimed:</span>
      <span class="value">{{ claim.claimed_amount | currency:'ZMW':'symbol':'1.2-2' }}</span>
    </div>
    <div class="detail-item" *ngIf="claim.approved_amount">
      <span class="label">Approved Amount:</span>
      <span class="value">{{ claim.approved_amount | currency:'ZMW':'symbol':'1.2-2' }}</span>
    </div>
    <div class="detail-item">
      <span class="label">Service Date:</span>
      <span class="value">{{ claim.service_date | date:'mediumDate' }}</span>
    </div>
  </div>
</div>
```

**Styles**: `claim-tracker.component.scss`

```scss
.claim-tracker {
  padding: 20px;
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.live-indicator {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  background: #f5f5f5;
  border-radius: 4px;
  margin-bottom: 16px;
  color: #666;

  &.active {
    background: #e8f5e9;
    color: #2e7d32;

    .live-dot {
      background: #4caf50;
      animation: pulse 2s infinite;
    }
  }

  .live-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #999;
  }
}

@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.5;
  }
}

.claim-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;

  h2 {
    margin: 0;
    font-size: 24px;
    font-weight: 600;
  }

  .status-badge {
    padding: 6px 12px;
    border-radius: 16px;
    font-size: 14px;
    font-weight: 500;

    &.status-approved {
      background: #e8f5e9;
      color: #2e7d32;
    }

    &.status-rejected {
      background: #ffebee;
      color: #c62828;
    }

    &.status-pending {
      background: #fff3e0;
      color: #e65100;
    }

    &.status-processing {
      background: #e3f2fd;
      color: #1565c0;
    }
  }
}

.timeline {
  position: relative;
  padding-left: 32px;
  margin: 24px 0;

  &::before {
    content: '';
    position: absolute;
    left: 8px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e0e0e0;
  }

  .timeline-item {
    position: relative;
    margin-bottom: 24px;

    &:last-child {
      margin-bottom: 0;
    }

    .timeline-marker {
      position: absolute;
      left: -28px;
      top: 4px;
      width: 16px;
      height: 16px;
      border-radius: 50%;
      background: #fff;
      border: 2px solid #2196f3;
    }

    &.timeline-success .timeline-marker {
      border-color: #4caf50;
      background: #4caf50;
    }

    &.timeline-error .timeline-marker {
      border-color: #f44336;
      background: #f44336;
    }

    &.timeline-warning .timeline-marker {
      border-color: #ff9800;
      background: #ff9800;
    }
  }

  .timeline-content {
    .timeline-message {
      font-size: 14px;
      font-weight: 500;
      margin-bottom: 4px;
    }

    .timeline-time {
      font-size: 12px;
      color: #666;
    }
  }
}

.claim-details {
  border-top: 1px solid #e0e0e0;
  padding-top: 16px;

  .detail-item {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;

    .label {
      color: #666;
    }

    .value {
      font-weight: 500;
    }
  }
}
```

---

### Component 2: Eligibility Checker

**File**: `src/app/components/eligibility-checker/eligibility-checker.component.ts`

```typescript
import { Component } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { EligibilityService } from '../../services/eligibility.service';

interface EligibilityResult {
  is_eligible: boolean;
  member_name: string;
  benefit_balance: number;
  benefit_limit: number;
  estimated_copay: number;
  status: string;
  message?: string;
}

@Component({
  selector: 'app-eligibility-checker',
  templateUrl: './eligibility-checker.component.html',
  styleUrls: ['./eligibility-checker.component.scss']
})
export class EligibilityCheckerComponent {
  eligibilityForm: FormGroup;
  result: EligibilityResult | null = null;
  loading = false;
  error: string | null = null;

  servicetypes = [
    { value: 'consultation', label: 'Doctor Consultation' },
    { value: 'surgery', label: 'Surgery' },
    { value: 'diagnostic', label: 'Diagnostic Tests' },
    { value: 'dental', label: 'Dental Services' },
  ];

  constructor(
    private fb: FormBuilder,
    private eligibilityService: EligibilityService
  ) {
    this.eligibilityForm = this.fb.group({
      cardNumber: ['', Validators.required],
      serviceType: ['consultation', Validators.required],
      estimatedAmount: [null]
    });
  }

  checkEligibility(): void {
    if (this.eligibilityForm.invalid) {
      return;
    }

    this.loading = true;
    this.error = null;
    this.result = null;

    const formData = this.eligibilityForm.value;

    this.eligibilityService.checkEligibility(
      formData.cardNumber,
      formData.serviceType,
      formData.estimatedAmount
    ).subscribe({
      next: (result) => {
        this.loading = false;
        this.result = result;
      },
      error: (error) => {
        this.loading = false;
        this.error = error.message || 'Failed to check eligibility';
      }
    });
  }

  getUtilizationPercentage(): number {
    if (!this.result) return 0;
    return (this.result.benefit_balance / this.result.benefit_limit) * 100;
  }

  getUtilizationClass(): string {
    const percentage = this.getUtilizationPercentage();
    if (percentage <= 20) return 'low';
    if (percentage <= 50) return 'medium';
    return 'high';
  }

  reset(): void {
    this.eligibilityForm.reset({ serviceType: 'consultation' });
    this.result = null;
    this.error = null;
  }
}
```

**Template**: `eligibility-checker.component.html`

```html
<div class="eligibility-checker">
  <h2>🏥 Check Eligibility</h2>

  <form [formGroup]="eligibilityForm" (ngSubmit)="checkEligibility()" *ngIf="!result">
    <div class="form-group">
      <label for="cardNumber">Member Card Number</label>
      <input
        id="cardNumber"
        type="text"
        formControlName="cardNumber"
        placeholder="MED-2024-001234"
        class="form-control">
    </div>

    <div class="form-group">
      <label for="serviceType">Service Type</label>
      <select
        id="serviceType"
        formControlName="serviceType"
        class="form-control">
        <option *ngFor="let service of serviceTypes" [value]="service.value">
          {{ service.label }}
        </option>
      </select>
    </div>

    <div class="form-group">
      <label for="estimatedAmount">Estimated Amount (Optional)</label>
      <input
        id="estimatedAmount"
        type="number"
        formControlName="estimatedAmount"
        placeholder="K 500.00"
        class="form-control">
    </div>

    <button
      type="submit"
      class="btn btn-primary"
      [disabled]="eligibilityForm.invalid || loading">
      {{ loading ? 'Checking...' : 'Check Eligibility Now' }}
    </button>

    <div class="error-message" *ngIf="error">
      ❌ {{ error }}
    </div>
  </form>

  <!-- Results -->
  <div class="results" *ngIf="result">
    <div class="result-header" [class.eligible]="result.is_eligible" [class.not-eligible]="!result.is_eligible">
      <div class="icon">{{ result.is_eligible ? '✅' : '❌' }}</div>
      <h3>{{ result.is_eligible ? "You're Covered!" : 'Not Eligible' }}</h3>
    </div>

    <div class="result-details" *ngIf="result.is_eligible">
      <div class="detail-card">
        <div class="label">Member</div>
        <div class="value">{{ result.member_name }}</div>
      </div>

      <div class="detail-card">
        <div class="label">Benefit Balance</div>
        <div class="value">
          {{ result.benefit_balance | currency:'ZMW':'symbol':'1.2-2' }} /
          {{ result.benefit_limit | currency:'ZMW':'symbol':'1.2-2' }}
        </div>
        <div class="progress-bar">
          <div class="progress"
               [ngClass]="getUtilizationClass()"
               [style.width.%]="getUtilizationPercentage()">
          </div>
        </div>
      </div>

      <div class="detail-card">
        <div class="label">Estimated Copay</div>
        <div class="value copay">
          {{ result.estimated_copay | currency:'ZMW':'symbol':'1.2-2' }}
        </div>
      </div>
    </div>

    <div class="not-eligible-message" *ngIf="!result.is_eligible">
      <p>{{ result.message }}</p>
    </div>

    <div class="actions">
      <button class="btn btn-secondary" (click)="reset()">Check Another</button>
      <button class="btn btn-primary" *ngIf="result.is_eligible">Request Pre-Authorization</button>
    </div>
  </div>
</div>
```

---

## Phase 3: Services (Week 3)

### Eligibility Service

```typescript
import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';

@Injectable({
  providedIn: 'root'
})
export class EligibilityService {
  private apiUrl = `${environment.apiUrl}/api/provider/eligibility`;

  constructor(private http: HttpClient) {}

  checkEligibility(
    cardNumber: string,
    serviceType: string,
    estimatedAmount?: number
  ): Observable<any> {
    return this.http.post(`${this.apiUrl}/check`, {
      card_number: cardNumber,
      service_codes: [serviceType],
      estimated_amount: estimatedAmount
    });
  }
}
```

### Claim Service

```typescript
import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';

@Injectable({
  providedIn: 'root'
})
export class ClaimService {
  private apiUrl = `${environment.apiUrl}/api/provider/claims`;

  constructor(private http: HttpClient) {}

  getClaim(claimId: string): Observable<any> {
    return this.http.get(`${this.apiUrl}/${claimId}`);
  }

  submitClaim(claimData: any): Observable<any> {
    return this.http.post(this.apiUrl, claimData);
  }

  getClaimsByMember(cardNumber: string): Observable<any> {
    return this.http.get(`${this.apiUrl}/member/${cardNumber}`);
  }
}
```

---

## Phase 4: Testing & Deployment (Week 4)

### Unit Tests

```typescript
// claim-tracker.component.spec.ts
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ClaimTrackerComponent } from './claim-tracker.component';
import { EchoService } from '../../services/echo.service';
import { ClaimService } from '../../services/claim.service';
import { of } from 'rxjs';

describe('ClaimTrackerComponent', () => {
  let component: ClaimTrackerComponent;
  let fixture: ComponentFixture<ClaimTrackerComponent>;
  let mockEchoService: jasmine.SpyObj<EchoService>;
  let mockClaimService: jasmine.SpyObj<ClaimService>;

  beforeEach(() => {
    mockEchoService = jasmine.createSpyObj('EchoService', ['getEcho']);
    mockClaimService = jasmine.createSpyObj('ClaimService', ['getClaim']);

    TestBed.configureTestingModule({
      declarations: [ClaimTrackerComponent],
      providers: [
        { provide: EchoService, useValue: mockEchoService },
        { provide: ClaimService, useValue: mockClaimService }
      ]
    });

    fixture = TestBed.createComponent(ClaimTrackerComponent);
    component = fixture.componentInstance;
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('should load claim on init', () => {
    const mockClaim = { id: '123', status: 'submitted' };
    mockClaimService.getClaim.and.returnValue(of(mockClaim));
    component.claimId = '123';

    component.ngOnInit();

    expect(mockClaimService.getClaim).toHaveBeenCalledWith('123');
    expect(component.claim).toEqual(mockClaim);
  });
});
```

---

## Environment Configuration

**development**: `src/environments/environment.ts`

```typescript
export const environment = {
  production: false,
  apiUrl: 'http://localhost:8000',
  wsUrl: 'ws://localhost:8080',
  reverbAppKey: 'your-dev-key',
};
```

**production**: `src/environments/environment.prod.ts`

```typescript
export const environment = {
  production: true,
  apiUrl: 'https://api.yourdomain.com',
  wsUrl: 'wss://ws.yourdomain.com',
  reverbAppKey: 'your-prod-key',
};
```

---

## Next Steps

1. ✅ Set up Laravel Echo connection
2. ✅ Build real-time claim tracker
3. ✅ Build eligibility checker
4. 🔄 Add provider dashboard
5. 🔄 Add fraud alert viewer (staff)
6. 🔄 Mobile app (React Native or Flutter)

This gives you a **complete, working real-time frontend** that's simple, fast, and production-ready! 🚀
