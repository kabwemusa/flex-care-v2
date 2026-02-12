# WebSocket Real-Time Notifications Integration Guide

## Overview

The Medical Claims Processing System provides real-time notifications via WebSocket using Laravel Reverb (Laravel Echo Server). This allows instant updates for claims, pre-authorizations, fraud alerts, and benefit balances.

## Technology Stack

- **Backend**: Laravel Reverb (WebSocket server)
- **Protocol**: WebSocket over HTTP/HTTPS
- **Authentication**: Laravel Sanctum tokens or session-based
- **Frontend Library**: Laravel Echo (JavaScript)

---

## Quick Start

### 1. Backend Setup

#### Install Laravel Reverb
```bash
composer require laravel/reverb
php artisan reverb:install
```

#### Configure `.env`
```env
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_HOST="0.0.0.0"
REVERB_PORT=8080
REVERB_SCHEME=http

# For production with SSL
REVERB_SCHEME=https
```

#### Start Reverb Server
```bash
php artisan reverb:start
```

#### Run Queue Workers
```bash
# Separate workers for different queues
php artisan queue:work redis --queue=medical-claims-urgent,medical-claims-standard &
php artisan queue:work redis --queue=medical-preauth &
php artisan queue:work redis --queue=medical-fraud-analysis &
php artisan queue:work redis --queue=medical-notifications &
```

---

### 2. Frontend Setup

#### Install Laravel Echo & Pusher
```bash
npm install --save laravel-echo pusher-js
```

#### Configure Echo (TypeScript)
```typescript
// echo.config.ts
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

export const echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: '/broadcasting/auth',
    auth: {
        headers: {
            Authorization: `Bearer ${getAuthToken()}`,
            Accept: 'application/json',
        },
    },
});

function getAuthToken(): string {
    // Get token from localStorage or your auth system
    return localStorage.getItem('auth_token') || '';
}
```

#### Environment Variables (`.env`)
```env
VITE_REVERB_APP_KEY=your-app-key
VITE_REVERB_HOST=localhost
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=http
```

---

## Available Channels & Events

### 1. Claim Status Updates

**Channel**: `private-claim.{claimId}`
**Event**: `claim.status.updated`

```typescript
interface ClaimStatusUpdate {
    claim_id: string;
    claim_number: string;
    status: string;
    previous_status: string;
    details: {
        approved_amount?: number;
        payable_amount?: number;
        reason?: string;
        [key: string]: any;
    };
    timestamp: string;
}

// Subscribe to claim updates
echo.private(`claim.${claimId}`)
    .listen('.claim.status.updated', (event: ClaimStatusUpdate) => {
        console.log('Claim status updated:', event);

        // Update UI
        updateClaimUI(event);

        // Show notification
        if (event.status === 'approved') {
            showSuccessNotification(`Claim ${event.claim_number} approved!`);
        } else if (event.status === 'rejected') {
            showErrorNotification(`Claim ${event.claim_number} rejected`);
        }
    });
```

---

### 2. Pre-Authorization Updates

**Channel**: `private-preauth.{preauthId}`
**Event**: `preauth.status.updated`

```typescript
interface PreAuthStatusUpdate {
    preauth_id: string;
    preauth_number: string;
    status: string;
    details: {
        approved_amount?: number;
        expires_at?: string;
        [key: string]: any;
    };
    timestamp: string;
}

// Subscribe to pre-auth updates
echo.private(`preauth.${preauthId}`)
    .listen('.preauth.status.updated', (event: PreAuthStatusUpdate) => {
        console.log('PreAuth updated:', event);
        updatePreAuthUI(event);
    });
```

---

### 3. Member Updates

**Channel**: `private-member.{memberId}`
**Events**:
- `claim.status.updated`
- `preauth.status.updated`
- `benefit.balance.updated`
- `eligibility.checked`

```typescript
// Listen to all member-related events
echo.private(`member.${memberId}`)
    .listen('.claim.status.updated', handleClaimUpdate)
    .listen('.preauth.status.updated', handlePreAuthUpdate)
    .listen('.benefit.balance.updated', handleBenefitUpdate)
    .listen('.eligibility.checked', handleEligibilityCheck);

interface BenefitBalanceUpdate {
    member_id: string;
    benefit_id: string;
    benefit_name: string;
    previous_balance: number;
    new_balance: number;
    change_amount: number;
    change_type: 'used' | 'reserved' | 'released' | 'adjusted';
    claim_id?: string;
    preauth_id?: string;
    timestamp: string;
}

function handleBenefitUpdate(event: BenefitBalanceUpdate) {
    console.log(`Benefit ${event.benefit_name} balance: ${event.new_balance}`);

    // Show low balance warning
    if (event.new_balance < 1000 && event.change_type === 'used') {
        showWarning(`Your ${event.benefit_name} benefit is running low`);
    }
}
```

---

### 4. Provider Updates (Hospital/Clinic)

**Channel**: `private-provider.{providerId}`
**Events**: All claim/preauth events for their submissions

```typescript
// Provider listening to their submitted claims
echo.private(`provider.${providerId}`)
    .listen('.claim.status.updated', (event: ClaimStatusUpdate) => {
        console.log('Provider claim update:', event);

        // Update provider dashboard
        updateProviderDashboard(event);
    })
    .listen('.eligibility.checked', (event) => {
        // Real-time eligibility results
        displayEligibilityResult(event);
    });
```

---

### 5. Fraud Alerts (Staff Only)

**Channel**: `private-fraud-alerts`
**Event**: `fraud.alert.created`

```typescript
interface FraudAlertCreated {
    alert_id: string;
    alert_number: string;
    entity_type: 'claim' | 'preauth';
    entity_id: string;
    fraud_score: number;
    severity: 'low' | 'medium' | 'high' | 'critical';
    flags: string[];
    member_id?: string;
    provider_id?: string;
    flagged_amount?: number;
    timestamp: string;
}

// Staff/Admin only - listen to fraud alerts
echo.private('fraud-alerts')
    .listen('.fraud.alert.created', (event: FraudAlertCreated) => {
        console.warn('Fraud alert created:', event);

        // Show urgent notification for critical alerts
        if (event.severity === 'critical') {
            showCriticalAlert(`Critical fraud alert: ${event.alert_number}`);
            playAlertSound();
        }

        // Update fraud dashboard
        addAlertToDashboard(event);
    });

// Listen to specific severity level
echo.private('fraud-alerts.critical')
    .listen('.fraud.alert.created', handleCriticalAlert);
```

---

## Complete Integration Example

### React Component Example

```typescript
import { useEffect, useState } from 'react';
import { echo } from './echo.config';

interface Claim {
    id: string;
    claim_number: string;
    status: string;
    claimed_amount: number;
}

export function ClaimTracker({ claimId }: { claimId: string }) {
    const [claim, setClaim] = useState<Claim | null>(null);
    const [updates, setUpdates] = useState<string[]>([]);

    useEffect(() => {
        // Join the claim channel
        const channel = echo.private(`claim.${claimId}`)
            .listen('.claim.status.updated', (event: ClaimStatusUpdate) => {
                console.log('Real-time update:', event);

                // Update claim state
                setClaim(prev => prev ? { ...prev, status: event.status } : null);

                // Add to activity log
                setUpdates(prev => [
                    `${event.timestamp}: Status changed to ${event.status}`,
                    ...prev
                ]);

                // Show notification
                showNotification(event);
            });

        // Cleanup on unmount
        return () => {
            echo.leave(`claim.${claimId}`);
        };
    }, [claimId]);

    return (
        <div>
            <h2>Claim {claim?.claim_number}</h2>
            <p>Status: <span className="status-badge">{claim?.status}</span></p>

            <div className="live-indicator">
                🟢 Live updates active
            </div>

            <h3>Activity Log</h3>
            <ul>
                {updates.map((update, i) => (
                    <li key={i}>{update}</li>
                ))}
            </ul>
        </div>
    );
}
```

### Angular Service Example

```typescript
import { Injectable } from '@angular/core';
import { echo } from './echo.config';
import { Subject, Observable } from 'rxjs';

interface ClaimUpdate {
    claim_id: string;
    status: string;
    details: any;
}

@Injectable({ providedIn: 'root' })
export class RealtimeClaimService {
    private claimUpdates$ = new Subject<ClaimUpdate>();

    subscribeToClaimUpdates(claimId: string): Observable<ClaimUpdate> {
        echo.private(`claim.${claimId}`)
            .listen('.claim.status.updated', (event: ClaimUpdate) => {
                this.claimUpdates$.next(event);
            });

        return this.claimUpdates$.asObservable();
    }

    unsubscribeFromClaim(claimId: string): void {
        echo.leave(`claim.${claimId}`);
    }
}

// Component usage
export class ClaimDetailsComponent implements OnInit, OnDestroy {
    constructor(private realtimeService: RealtimeClaimService) {}

    ngOnInit() {
        this.realtimeService.subscribeToClaimUpdates(this.claimId)
            .subscribe(update => {
                console.log('Claim updated:', update);
                this.updateUI(update);
            });
    }

    ngOnDestroy() {
        this.realtimeService.unsubscribeFromClaim(this.claimId);
    }
}
```

---

## Authorization & Security

### Backend Channel Authorization

Channels are automatically authorized based on user permissions (see `Modules/Medical/Routes/channels.php`):

- **Member channels**: Only accessible by the member or authorized staff
- **Provider channels**: Only accessible by provider users or staff
- **Fraud alerts**: Only accessible by staff with fraud permissions

### Frontend Authentication

The frontend must pass authentication token in Echo configuration:

```typescript
auth: {
    headers: {
        Authorization: `Bearer ${token}`,
        Accept: 'application/json',
    },
}
```

Backend validates this token via the `/broadcasting/auth` endpoint.

---

## Testing WebSocket Connection

### Test Connection
```javascript
// Check if Echo is connected
if (echo.connector.pusher.connection.state === 'connected') {
    console.log('✅ WebSocket connected');
} else {
    console.log('❌ WebSocket disconnected');
}

// Listen to connection events
echo.connector.pusher.connection.bind('connected', () => {
    console.log('WebSocket connected!');
});

echo.connector.pusher.connection.bind('disconnected', () => {
    console.log('WebSocket disconnected!');
});

echo.connector.pusher.connection.bind('error', (error) => {
    console.error('WebSocket error:', error);
});
```

### Test Event Broadcasting
```bash
# Use Tinker to test broadcasting
php artisan tinker

# Broadcast a test claim update
event(new \Modules\Medical\Events\ClaimStatusUpdated(
    claimId: 'claim-uuid-here',
    claimNumber: 'CLM-2026-000001',
    status: 'approved',
    previousStatus: 'pending',
    details: ['test' => true],
    memberId: 'member-uuid',
    providerId: null
));
```

---

## Production Deployment

### 1. Use SSL/TLS
```env
REVERB_SCHEME=https
```

### 2. Run Reverb with Supervisor
```ini
[program:reverb]
command=php /path/to/artisan reverb:start
directory=/path/to/project
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/log/reverb.log
```

### 3. Use Nginx Proxy
```nginx
location /reverb {
    proxy_pass http://localhost:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_set_header Host $host;
}
```

### 4. Monitor Queue Workers
Ensure queue workers are running for real-time processing:
```bash
php artisan queue:work --queue=medical-claims-urgent --tries=3 --daemon
```

---

## Troubleshooting

### Connection Issues
1. Check Reverb is running: `ps aux | grep reverb`
2. Check ports are open: `netstat -tlnp | grep 8080`
3. Check firewall rules
4. Verify `.env` configuration matches frontend

### Events Not Received
1. Check channel authorization in `channels.php`
2. Verify user has proper permissions
3. Check event implements `ShouldBroadcast`
4. Check queue workers are running
5. Review Laravel logs: `tail -f storage/logs/laravel.log`

### Performance Issues
1. Use Redis for queue/cache
2. Scale queue workers horizontally
3. Enable Reverb clustering for high traffic
4. Monitor with Laravel Horizon

---

## Summary

The WebSocket integration provides real-time updates for:
- ✅ Claim status changes (submitted → processing → approved/rejected)
- ✅ Pre-authorization approvals/rejections
- ✅ Benefit balance updates
- ✅ Fraud alerts (staff only)
- ✅ Eligibility check results

All events are authenticated, authorized, and broadcast only to permitted users.
