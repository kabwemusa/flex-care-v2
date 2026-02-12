# Backend-Frontend Integration Plan
## Connecting Angular Frontend to Real-Time Claims Processing Backend

---

## Executive Summary

Your existing Angular 21 frontend (`front-end/`) has a comprehensive claims management system. The new backend provides **real-time processing**, **fraud detection**, **pre-authorization**, and **provider APIs**. This plan outlines specific changes needed to integrate them.

---

## Current State Analysis

### ✅ What You Already Have (Frontend)

| Component | Status | Location |
|-----------|--------|----------|
| Claims List & Detail | ✅ Complete | `libs/medical-feature/medical-claims-*` |
| ClaimStore (State) | ✅ Complete | `libs/medical-data/src/lib/stores/claim.store.ts` |
| API Integration | ✅ Complete | HTTP interceptors + services |
| Permission System | ✅ Complete | Role-based access control |
| Material UI | ✅ Complete | Angular Material 21 |

### 🔄 What Needs Integration (Backend Features)

| Feature | Backend Ready | Frontend Needed |
|---------|---------------|-----------------|
| Real-Time Updates | ✅ WebSocket | 🔄 Echo integration |
| Fraud Detection | ✅ API + Events | 🔄 Fraud dashboard |
| Pre-Authorization | ✅ API | 🔄 PreAuth components |
| Provider Portal | ✅ API | 🔄 Separate app/module |
| Eligibility Check | ✅ API | 🔄 Quick checker widget |

---

## Phase 1: Backend API Endpoint Mapping

### Your Current Frontend Expects:

```typescript
// From ClaimStore analysis
GET    /api/v1/medical/claims             → List claims
POST   /api/v1/medical/claims             → Create claim
GET    /api/v1/medical/claims/:id         → Get claim
PUT    /api/v1/medical/claims/:id         → Update claim
POST   /api/v1/medical/claims/:id/approve → Approve claim
POST   /api/v1/medical/claims/:id/reject  → Reject claim
...
```

### Backend Provides (from Phase 1-5):

```php
// Provider API (for hospitals)
POST   /api/provider/claims                    → Submit claim (real-time)
GET    /api/provider/claims/:number            → Get claim status
POST   /api/provider/eligibility/check         → Check eligibility
POST   /api/provider/preauth                   → Request pre-auth

// Staff API (for your frontend)
GET    /api/medical/claims                     → List claims
GET    /api/medical/claims/:id                 → Get claim detail
POST   /api/medical/claims/:id/approve         → Approve claim
POST   /api/medical/claims/:id/reject          → Reject claim
GET    /api/medical/fraud-alerts               → List fraud alerts
GET    /api/medical/preauth                    → List pre-authorizations
```

### 🎯 Action Required: Update API Base Path

**File**: `front-end/projects/libs/core/config/src/lib/services/config.service.ts`

```typescript
// CURRENT
private readonly API_BASE_URL = 'http://localhost:8000';

// UPDATE TO
private readonly API_BASE_URL = 'http://localhost:8000/api';
// Note: Your proxy.conf.json already handles /api prefix
```

**Alternative**: Keep current config, update ClaimStore to use correct endpoints.

---

## Phase 2: Add WebSocket Real-Time Updates

### Step 1: Install Dependencies

```bash
cd front-end
npm install laravel-echo pusher-js --save
```

### Step 2: Create WebSocket Service

**File**: `front-end/projects/libs/core/http/src/lib/services/websocket.service.ts`

```typescript
import { Injectable, inject, signal, computed } from '@angular/core';
import { AuthService } from 'core-auth';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
  interface Window {
    Pusher: any;
    Echo: Echo;
  }
}

export interface WebSocketConfig {
  key: string;
  wsHost: string;
  wsPort: number;
  forceTLS: boolean;
}

@Injectable({
  providedIn: 'root'
})
export class WebSocketService {
  private readonly authService = inject(AuthService);

  private echo: Echo | null = null;
  private readonly connectionState = signal<'connected' | 'connecting' | 'disconnected'>('disconnected');

  readonly isConnected = computed(() => this.connectionState() === 'connected');

  constructor() {
    this.initialize();
  }

  private initialize(): void {
    window.Pusher = Pusher;

    this.echo = new Echo({
      broadcaster: 'reverb',
      key: 'your-reverb-app-key', // TODO: Move to environment
      wsHost: 'localhost',
      wsPort: 8080,
      wssPort: 8080,
      forceTLS: false,
      enabledTransports: ['ws', 'wss'],
      authEndpoint: 'http://localhost:8000/broadcasting/auth',
      auth: {
        headers: {
          Authorization: `Bearer ${this.authService.user()?.token}`,
          Accept: 'application/json',
        },
      },
    });

    // Connection event listeners
    this.echo.connector.pusher.connection.bind('connected', () => {
      console.log('✅ WebSocket connected');
      this.connectionState.set('connected');
    });

    this.echo.connector.pusher.connection.bind('disconnected', () => {
      console.log('❌ WebSocket disconnected');
      this.connectionState.set('disconnected');
    });

    this.echo.connector.pusher.connection.bind('connecting', () => {
      this.connectionState.set('connecting');
    });
  }

  getEcho(): Echo {
    if (!this.echo) {
      this.initialize();
    }
    return this.echo!;
  }

  disconnect(): void {
    if (this.echo) {
      this.echo.disconnect();
      this.connectionState.set('disconnected');
    }
  }

  // Helper method to subscribe to claim updates
  subscribeToClaimUpdates(claimId: string, callback: (event: any) => void): void {
    this.getEcho()
      .private(`claim.${claimId}`)
      .listen('.claim.status.updated', callback);
  }

  // Helper method to unsubscribe
  unsubscribeFromClaim(claimId: string): void {
    this.getEcho().leave(`claim.${claimId}`);
  }

  // Subscribe to fraud alerts (staff only)
  subscribeToFraudAlerts(callback: (event: any) => void): void {
    this.getEcho()
      .private('fraud-alerts')
      .listen('.fraud.alert.created', callback);
  }

  // Subscribe to member updates
  subscribeToMemberUpdates(memberId: string, callback: (event: any) => void): void {
    this.getEcho()
      .private(`member.${memberId}`)
      .listen('.claim.status.updated', callback)
      .listen('.benefit.balance.updated', callback)
      .listen('.preauth.status.updated', callback);
  }
}
```

**Export it**: Update `front-end/projects/libs/core/http/src/index.ts`:

```typescript
export * from './lib/services/websocket.service';
```

---

### Step 3: Update ClaimStore for Real-Time Updates

**File**: `front-end/projects/libs/medical/data/src/lib/stores/claim.store.ts`

Add WebSocket integration:

```typescript
import { Injectable, inject, signal, computed, effect } from '@angular/core';
import { WebSocketService } from 'core-http'; // Import WebSocket service

@Injectable({ providedIn: 'root' })
export class ClaimStore {
  private readonly http = inject(HttpClient);
  private readonly wsService = inject(WebSocketService); // Inject WebSocket

  // Add real-time status signal
  private readonly realtimeUpdates = signal<Map<string, any>>(new Map());

  constructor() {
    // Subscribe to global claim updates for all claims in view
    this.setupRealtimeUpdates();
  }

  private setupRealtimeUpdates(): void {
    // Effect to resubscribe when claims list changes
    effect(() => {
      const claims = this.claims();
      claims.forEach(claim => {
        // Subscribe to each visible claim
        this.wsService.subscribeToClaimUpdates(claim.id, (event) => {
          console.log('Real-time claim update:', event);
          this.handleRealtimeUpdate(event);
        });
      });
    });
  }

  private handleRealtimeUpdate(event: any): void {
    const { claim_id, status, details } = event;

    // Update the claim in the state
    this.state.update(s => ({
      ...s,
      items: s.items.map(claim =>
        claim.id === claim_id
          ? { ...claim, status, ...details }
          : claim
      ),
    }));

    // Store the update for UI indicators
    this.realtimeUpdates.update(map => {
      const newMap = new Map(map);
      newMap.set(claim_id, event);
      return newMap;
    });

    // Show notification
    this.showNotification(status, details);
  }

  private showNotification(status: string, details: any): void {
    // Integrate with your FeedbackService
    const messages = {
      'approved': `Claim approved - ${details.approved_amount} payable`,
      'rejected': `Claim rejected - ${details.reason}`,
      'pending': 'Claim under manual review',
    };

    const message = messages[status as keyof typeof messages] || `Claim status: ${status}`;
    // this.feedbackService.success(message);
    console.log('📢 Notification:', message);
  }

  // Public method to check if claim has real-time updates
  hasRealtimeUpdate(claimId: string): boolean {
    return this.realtimeUpdates().has(claimId);
  }

  // Cleanup when component is destroyed
  ngOnDestroy(): void {
    const claims = this.claims();
    claims.forEach(claim => {
      this.wsService.unsubscribeFromClaim(claim.id);
    });
  }
}
```

---

### Step 4: Update Claim Detail Component

**File**: `front-end/projects/libs/medical/feature/medical-claim-detail/src/lib/medical-claim-detail.component.ts`

Add live indicator:

```typescript
import { Component, OnInit, OnDestroy, inject } from '@angular/core';
import { WebSocketService } from 'core-http';

@Component({
  selector: 'app-medical-claim-detail',
  template: `
    <div class="claim-detail">
      <!-- Live Indicator -->
      <div class="live-indicator" [class.active]="wsService.isConnected()">
        <span class="live-dot"></span>
        {{ wsService.isConnected() ? 'Live Updates Active' : 'Connecting...' }}
      </div>

      <!-- Rest of your existing template -->
      ...
    </div>
  `,
  styles: [`
    .live-indicator {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 8px 12px;
      background: #f5f5f5;
      border-radius: 4px;
      margin-bottom: 16px;

      &.active {
        background: #e8f5e9;
        color: #2e7d32;
      }

      .live-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #999;
      }

      &.active .live-dot {
        background: #4caf50;
        animation: pulse 2s infinite;
      }
    }

    @keyframes pulse {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.5; }
    }
  `]
})
export class MedicalClaimDetailComponent implements OnInit, OnDestroy {
  readonly wsService = inject(WebSocketService);
  readonly claimStore = inject(ClaimStore);

  claimId = input.required<string>();

  ngOnInit(): void {
    // Subscribe to this specific claim's updates
    this.wsService.subscribeToClaimUpdates(
      this.claimId(),
      (event) => {
        console.log('Claim updated in real-time:', event);
        // ClaimStore will handle the update
      }
    );
  }

  ngOnDestroy(): void {
    this.wsService.unsubscribeFromClaim(this.claimId());
  }
}
```

---

## Phase 3: Add Fraud Detection Dashboard

### Create Fraud Alert Feature Module

**File**: `front-end/projects/libs/medical/feature/medical-fraud-alerts/src/lib/medical-fraud-alerts.component.ts`

```typescript
import { Component, OnInit, inject, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatTableModule } from '@angular/material/table';
import { MatButtonModule } from '@angular/material/button';
import { MatChipsModule } from '@angular/material/chips';
import { WebSocketService } from 'core-http';
import { HttpClient } from '@angular/common/http';

interface FraudAlert {
  id: string;
  alert_number: string;
  entity_type: 'claim' | 'preauth';
  entity_id: string;
  fraud_score: number;
  severity: 'low' | 'medium' | 'high' | 'critical';
  triggered_rules: string[];
  status: 'open' | 'investigating' | 'confirmed_fraud' | 'false_positive';
  member_id?: string;
  provider_id?: string;
  flagged_amount?: number;
  created_at: string;
}

@Component({
  selector: 'app-medical-fraud-alerts',
  standalone: true,
  imports: [CommonModule, MatTableModule, MatButtonModule, MatChipsModule],
  template: `
    <div class="fraud-alerts-container">
      <div class="header">
        <h2>🚨 Fraud Detection Dashboard</h2>
        <div class="live-status" [class.active]="wsService.isConnected()">
          <span class="dot"></span>
          {{ wsService.isConnected() ? 'Live Monitoring' : 'Connecting...' }}
        </div>
      </div>

      <!-- Stats -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-value">{{ openAlerts() }}</div>
          <div class="stat-label">Open Alerts</div>
        </div>
        <div class="stat-card critical">
          <div class="stat-value">{{ criticalAlerts() }}</div>
          <div class="stat-label">Critical</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">{{ avgScore() }}</div>
          <div class="stat-label">Avg Score</div>
        </div>
      </div>

      <!-- Alerts Table -->
      <table mat-table [dataSource]="alerts()" class="alerts-table">
        <ng-container matColumnDef="severity">
          <th mat-header-cell *matHeaderCellDef>Severity</th>
          <td mat-cell *matCellDef="let alert">
            <mat-chip [class]="'severity-' + alert.severity">
              {{ getSeverityIcon(alert.severity) }} {{ alert.severity }}
            </mat-chip>
          </td>
        </ng-container>

        <ng-container matColumnDef="alert_number">
          <th mat-header-cell *matHeaderCellDef>Alert #</th>
          <td mat-cell *matCellDef="let alert">{{ alert.alert_number }}</td>
        </ng-container>

        <ng-container matColumnDef="score">
          <th mat-header-cell *matHeaderCellDef>Score</th>
          <td mat-cell *matCellDef="let alert">
            <span [class]="'score-' + getScoreClass(alert.fraud_score)">
              {{ alert.fraud_score }}/100
            </span>
          </td>
        </ng-container>

        <ng-container matColumnDef="flags">
          <th mat-header-cell *matHeaderCellDef>Flags</th>
          <td mat-cell *matCellDef="let alert">
            <mat-chip *ngFor="let flag of alert.triggered_rules.slice(0, 2)" class="flag-chip">
              {{ flag }}
            </mat-chip>
            <span *ngIf="alert.triggered_rules.length > 2">
              +{{ alert.triggered_rules.length - 2 }}
            </span>
          </td>
        </ng-container>

        <ng-container matColumnDef="amount">
          <th mat-header-cell *matHeaderCellDef>Amount</th>
          <td mat-cell *matCellDef="let alert">
            {{ alert.flagged_amount | currency:'ZMW' }}
          </td>
        </ng-container>

        <ng-container matColumnDef="created">
          <th mat-header-cell *matHeaderCellDef>Created</th>
          <td mat-cell *matCellDef="let alert">
            {{ alert.created_at | date:'short' }}
          </td>
        </ng-container>

        <ng-container matColumnDef="actions">
          <th mat-header-cell *matHeaderCellDef>Actions</th>
          <td mat-cell *matCellDef="let alert">
            <button mat-raised-button color="primary" (click)="investigate(alert)">
              Investigate
            </button>
          </td>
        </ng-container>

        <tr mat-header-row *matHeaderRowDef="displayedColumns"></tr>
        <tr mat-row *matRowDef="let row; columns: displayedColumns;"></tr>
      </table>
    </div>
  `,
  styles: [`
    .fraud-alerts-container {
      padding: 24px;
    }

    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 24px;
    }

    .live-status {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 8px 16px;
      background: #f5f5f5;
      border-radius: 16px;

      &.active {
        background: #e8f5e9;
        color: #2e7d32;

        .dot {
          background: #4caf50;
          animation: pulse 2s infinite;
        }
      }

      .dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: #999;
      }
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 16px;
      margin-bottom: 24px;
    }

    .stat-card {
      background: white;
      padding: 20px;
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);

      &.critical {
        border-left: 4px solid #f44336;
      }

      .stat-value {
        font-size: 32px;
        font-weight: bold;
        margin-bottom: 8px;
      }

      .stat-label {
        color: #666;
        font-size: 14px;
      }
    }

    .severity-critical {
      background: #ffebee !important;
      color: #c62828 !important;
    }

    .severity-high {
      background: #fff3e0 !important;
      color: #e65100 !important;
    }

    .score-high {
      color: #f44336;
      font-weight: bold;
    }
  `]
})
export class MedicalFraudAlertsComponent implements OnInit {
  readonly http = inject(HttpClient);
  readonly wsService = inject(WebSocketService);

  readonly alerts = signal<FraudAlert[]>([]);
  readonly openAlerts = computed(() =>
    this.alerts().filter(a => a.status === 'open').length
  );
  readonly criticalAlerts = computed(() =>
    this.alerts().filter(a => a.severity === 'critical').length
  );
  readonly avgScore = computed(() => {
    const scores = this.alerts().map(a => a.fraud_score);
    return scores.length > 0
      ? Math.round(scores.reduce((a, b) => a + b, 0) / scores.length)
      : 0;
  });

  displayedColumns = ['severity', 'alert_number', 'score', 'flags', 'amount', 'created', 'actions'];

  ngOnInit(): void {
    this.loadAlerts();
    this.subscribeToRealtimeAlerts();
  }

  private loadAlerts(): void {
    this.http.get<{ data: FraudAlert[] }>('/api/medical/fraud-alerts')
      .subscribe({
        next: (response) => {
          this.alerts.set(response.data);
        },
        error: (error) => {
          console.error('Failed to load fraud alerts:', error);
        }
      });
  }

  private subscribeToRealtimeAlerts(): void {
    this.wsService.subscribeToFraudAlerts((event: any) => {
      console.log('🚨 New fraud alert:', event);

      // Add new alert to the list
      this.alerts.update(alerts => [event, ...alerts]);

      // Show critical alert notification
      if (event.severity === 'critical') {
        this.showCriticalAlertNotification(event);
      }
    });
  }

  private showCriticalAlertNotification(alert: any): void {
    // Play sound, show desktop notification, etc.
    console.log('🔴 CRITICAL FRAUD ALERT:', alert.alert_number);

    if ('Notification' in window && Notification.permission === 'granted') {
      new Notification('Critical Fraud Alert', {
        body: `Alert ${alert.alert_number} - Score: ${alert.fraud_score}`,
        icon: '/assets/alert-icon.png',
        requireInteraction: true,
      });
    }
  }

  getSeverityIcon(severity: string): string {
    const icons = {
      'critical': '🔴',
      'high': '🟠',
      'medium': '🟡',
      'low': '🟢'
    };
    return icons[severity as keyof typeof icons] || '•';
  }

  getScoreClass(score: number): string {
    if (score >= 75) return 'high';
    if (score >= 40) return 'medium';
    return 'low';
  }

  investigate(alert: FraudAlert): void {
    // Navigate to investigation page or open dialog
    console.log('Investigating alert:', alert);
  }
}
```

**Add Route**: Update `front-end/projects/admin/src/app/app.routes.ts`:

```typescript
{
  path: 'fraud-alerts',
  loadComponent: () => import('medical-fraud-alerts'),
  canActivate: [authGuard, moduleGuard, anyPermissionGuard],
  data: {
    permissions: ['medical.fraud.view'],
  },
}
```

---

## Phase 4: Add Eligibility Quick Checker

### Create Widget Component

**File**: `front-end/projects/libs/medical/feature/medical-eligibility-checker/src/lib/medical-eligibility-checker.component.ts`

```typescript
import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { MatSelectModule } from '@angular/material/select';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';
import { HttpClient } from '@angular/common/http';

interface EligibilityResult {
  is_eligible: boolean;
  member_name: string;
  policy_status: string;
  benefit_balance: number;
  benefit_limit: number;
  estimated_copay: number;
  message?: string;
}

@Component({
  selector: 'app-medical-eligibility-checker',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    MatSelectModule,
    MatProgressSpinnerModule,
  ],
  template: `
    <div class="eligibility-checker">
      <h3>🏥 Quick Eligibility Check</h3>

      <form [formGroup]="form" (ngSubmit)="checkEligibility()" *ngIf="!result()">
        <mat-form-field appearance="outline">
          <mat-label>Member Card Number</mat-label>
          <input matInput formControlName="cardNumber" placeholder="MED-2024-001234">
        </mat-form-field>

        <mat-form-field appearance="outline">
          <mat-label>Service Type</mat-label>
          <mat-select formControlName="serviceType">
            <mat-option value="consultation">Doctor Consultation</mat-option>
            <mat-option value="surgery">Surgery</mat-option>
            <mat-option value="diagnostic">Diagnostic Tests</mat-option>
            <mat-option value="dental">Dental Services</mat-option>
          </mat-select>
        </mat-form-field>

        <mat-form-field appearance="outline">
          <mat-label>Estimated Amount (Optional)</mat-label>
          <input matInput type="number" formControlName="estimatedAmount" placeholder="500">
          <span matPrefix>K </span>
        </mat-form-field>

        <button mat-raised-button color="primary" type="submit" [disabled]="form.invalid || loading()">
          {{ loading() ? 'Checking...' : 'Check Eligibility' }}
        </button>
      </form>

      <!-- Results -->
      <div class="results" *ngIf="result()">
        <div class="result-header" [class.eligible]="result()!.is_eligible">
          <div class="icon">{{ result()!.is_eligible ? '✅' : '❌' }}</div>
          <h4>{{ result()!.is_eligible ? "Eligible for Coverage" : "Not Eligible" }}</h4>
        </div>

        <div class="details" *ngIf="result()!.is_eligible">
          <div class="detail-row">
            <span>Member:</span>
            <strong>{{ result()!.member_name }}</strong>
          </div>
          <div class="detail-row">
            <span>Benefit Balance:</span>
            <strong>K {{ result()!.benefit_balance | number:'1.2-2' }} / K {{ result()!.benefit_limit | number:'1.2-2' }}</strong>
          </div>
          <div class="detail-row">
            <span>Estimated Copay:</span>
            <strong class="copay">K {{ result()!.estimated_copay | number:'1.2-2' }}</strong>
          </div>
        </div>

        <div class="message" *ngIf="!result()!.is_eligible">
          {{ result()!.message }}
        </div>

        <button mat-button (click)="reset()">Check Another</button>
      </div>
    </div>
  `,
  styles: [`
    .eligibility-checker {
      background: white;
      padding: 24px;
      border-radius: 8px;
      max-width: 500px;
    }

    form {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .result-header {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 16px;
      border-radius: 8px;
      margin-bottom: 16px;

      &.eligible {
        background: #e8f5e9;
        color: #2e7d32;
      }

      &:not(.eligible) {
        background: #ffebee;
        color: #c62828;
      }

      .icon {
        font-size: 32px;
      }
    }

    .details {
      .detail-row {
        display: flex;
        justify-content: space-between;
        padding: 12px 0;
        border-bottom: 1px solid #eee;

        .copay {
          color: #1976d2;
        }
      }
    }
  `]
})
export class MedicalEligibilityCheckerComponent {
  private readonly http = inject(HttpClient);
  private readonly fb = inject(FormBuilder);

  readonly form = this.fb.group({
    cardNumber: ['', Validators.required],
    serviceType: ['consultation', Validators.required],
    estimatedAmount: [null as number | null],
  });

  readonly loading = signal(false);
  readonly result = signal<EligibilityResult | null>(null);

  checkEligibility(): void {
    if (this.form.invalid) return;

    this.loading.set(true);
    const formData = this.form.value;

    this.http.post<EligibilityResult>('/api/provider/eligibility/check', {
      card_number: formData.cardNumber,
      service_codes: [formData.serviceType],
      estimated_amount: formData.estimatedAmount,
    }).subscribe({
      next: (result) => {
        this.result.set(result);
        this.loading.set(false);
      },
      error: (error) => {
        console.error('Eligibility check failed:', error);
        this.loading.set(false);
      }
    });
  }

  reset(): void {
    this.form.reset({ serviceType: 'consultation' });
    this.result.set(null);
  }
}
```

---

## Phase 5: Environment Configuration

### Update Environment Files

**File**: `front-end/projects/admin/src/environments/environment.ts`

```typescript
export const environment = {
  production: false,
  apiUrl: 'http://localhost:8000',
  wsUrl: 'ws://localhost:8080',
  reverb: {
    key: 'your-reverb-app-key',
    wsHost: 'localhost',
    wsPort: 8080,
    forceTLS: false,
  },
};
```

**File**: `front-end/projects/admin/src/environments/environment.prod.ts`

```typescript
export const environment = {
  production: true,
  apiUrl: 'https://api.flexcare.com',
  wsUrl: 'wss://ws.flexcare.com',
  reverb: {
    key: process.env['REVERB_APP_KEY'] || '',
    wsHost: 'ws.flexcare.com',
    wsPort: 8080,
    forceTLS: true,
  },
};
```

---

## Implementation Checklist

### Week 1: Core Integration
- [ ] Install Laravel Echo & Pusher dependencies
- [ ] Create WebSocketService in `core-http`
- [ ] Update ClaimStore with real-time updates
- [ ] Add live indicator to claim detail component
- [ ] Test WebSocket connection

### Week 2: Fraud Detection
- [ ] Create FraudAlertsComponent
- [ ] Add fraud alerts route
- [ ] Subscribe to real-time fraud alerts
- [ ] Add desktop notifications for critical alerts
- [ ] Test with backend fraud detection

### Week 3: Eligibility & PreAuth
- [ ] Create EligibilityCheckerComponent
- [ ] Add widget to dashboard
- [ ] Create PreAuthStore (similar to ClaimStore)
- [ ] Add pre-auth management components
- [ ] Test eligibility API integration

### Week 4: Testing & Polish
- [ ] End-to-end testing
- [ ] Performance optimization
- [ ] Error handling
- [ ] User documentation
- [ ] Deploy to staging

---

## Quick Win: Test Real-Time Updates Now

**1. Start Backend Services**:
```bash
cd backend
php artisan reverb:start
php artisan queue:work redis --queue=medical-claims-urgent
```

**2. In Browser Console** (while on claims page):
```javascript
// Test WebSocket connection
const echo = new Echo({
  broadcaster: 'reverb',
  key: 'your-key',
  wsHost: 'localhost',
  wsPort: 8080,
  forceTLS: false,
});

echo.private('claim.claim-uuid-here')
  .listen('.claim.status.updated', (e) => {
    console.log('Real-time update!', e);
  });
```

**3. Trigger Update from Backend**:
```bash
php artisan tinker

# Broadcast test event
event(new \Modules\Medical\Events\ClaimStatusUpdated(
    claimId: 'your-claim-id',
    claimNumber: 'CLM-2026-000001',
    status: 'approved',
    previousStatus: 'pending',
    details: ['approved_amount' => 5000],
    memberId: 'member-id',
    providerId: null
));
```

You should see the event in browser console! 🎉

---

## Next Steps

Start with **Week 1** to get real-time updates working, then progressively add fraud detection and eligibility features. Your existing Angular architecture is solid and ready for this integration! 🚀
