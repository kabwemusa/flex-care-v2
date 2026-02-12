# Frontend User Experience Plan
## Real-Time Hospital Claims Processing System

---

## User Personas

### 1. **Member/Patient** (Primary End User)
- Wants to check coverage before visiting hospital
- Needs to track claim status in real-time
- Wants transparency on approvals/rejections
- Expects mobile-friendly experience

### 2. **Hospital/Clinic Staff** (Provider)
- Needs quick eligibility verification
- Submits claims through API or portal
- Tracks pre-authorizations
- Monitors claim approval rates

### 3. **Insurance Staff** (Internal Users)
- Claims processors reviewing flagged claims
- Fraud investigators handling alerts
- Customer service viewing member info
- Management viewing dashboards

---

## Core User Journeys

### Journey 1: Member Checking Eligibility (Before Hospital Visit)

**User Flow:**
```
Member Portal Login
    ↓
Dashboard (shows active policy, benefit balances)
    ↓
"Check Eligibility" button
    ↓
Select Service Type (e.g., consultation, surgery)
    ↓
Real-time eligibility check (<500ms)
    ↓
Results showing:
    - Coverage status ✅/❌
    - Available benefit balance
    - Estimated copay
    - Recommended hospitals
```

**Screen Design: Eligibility Check**
```
┌─────────────────────────────────────────┐
│  🏥 Check Eligibility                   │
├─────────────────────────────────────────┤
│                                         │
│  Member: John Doe                       │
│  Card #: MED-2024-001234               │
│  Policy: Premium Health Plan            │
│                                         │
│  ┌───────────────────────────────────┐ │
│  │ Select Service Type                │ │
│  │ ▼ Doctor Consultation              │ │
│  └───────────────────────────────────┘ │
│                                         │
│  Estimated Amount (optional)            │
│  ┌───────────────────────────────────┐ │
│  │ K 500.00                           │ │
│  └───────────────────────────────────┘ │
│                                         │
│  [Check Eligibility Now]                │
│                                         │
└─────────────────────────────────────────┘

After check (animated transition):

┌─────────────────────────────────────────┐
│  ✅ You're Covered!                     │
├─────────────────────────────────────────┤
│                                         │
│  Service: Doctor Consultation           │
│  Status: Active & Eligible              │
│                                         │
│  💰 Benefit Balance: K 4,500 / K 5,000 │
│  📊 Utilization: 90%                    │
│                                         │
│  Estimated Copay: K 50.00               │
│  Covered Amount: K 450.00               │
│                                         │
│  ⚠️ Low Balance Warning:                │
│  You have K 500 remaining for this      │
│  benefit. Consider upgrading your plan. │
│                                         │
│  [Find Network Hospital]                │
│  [Request Pre-Authorization]            │
│                                         │
└─────────────────────────────────────────┘
```

---

### Journey 2: Hospital Submitting a Claim

**User Flow:**
```
Hospital Portal Login (API Key Auth)
    ↓
Quick Actions Dashboard
    ↓
Option 1: Check Patient Eligibility
    - Scan card / Enter card number
    - Real-time result (<500ms)
    ↓
Option 2: Submit Claim
    - Auto-filled from eligibility check
    - Add diagnosis codes
    - Upload supporting documents
    - Submit
    ↓
Real-time Processing Updates (WebSocket)
    - Processing started... ⏳
    - Fraud check passed ✅
    - Auto-adjudication in progress...
    - Approved! ✅ (or Pending Review 📋)
```

**Screen Design: Hospital Quick Submit**
```
┌─────────────────────────────────────────┐
│  🏥 University Teaching Hospital        │
│  Provider ID: UTH-001                   │
├─────────────────────────────────────────┤
│                                         │
│  Quick Actions:                         │
│  ┌─────────────┐  ┌─────────────┐     │
│  │   🔍        │  │   📝        │     │
│  │  Check      │  │  Submit     │     │
│  │ Eligibility │  │  Claim      │     │
│  └─────────────┘  └─────────────┘     │
│                                         │
│  Recent Claims (Live Updates 🟢)        │
│  ┌───────────────────────────────────┐ │
│  │ CLM-2026-000123                    │ │
│  │ John Doe • K 2,500                 │ │
│  │ 🟢 Processing...                   │ │
│  │ Just now                           │ │
│  ├───────────────────────────────────┤ │
│  │ CLM-2026-000122                    │ │
│  │ Jane Smith • K 5,000               │ │
│  │ ✅ Approved - K 4,750 payable     │ │
│  │ 2 mins ago                         │ │
│  └───────────────────────────────────┘ │
│                                         │
│  Daily Summary:                         │
│  Claims Submitted: 45                   │
│  Auto-Approved: 38 (84%)                │
│  Pending Review: 7                      │
│  Avg Processing Time: 1.2 mins          │
│                                         │
└─────────────────────────────────────────┘
```

---

### Journey 3: Member Tracking Claim Status

**User Flow:**
```
Member receives claim submission notification
    ↓
Opens app/portal
    ↓
Real-time claim tracker (like food delivery tracking)
    ↓
Status updates via WebSocket:
    1. Claim Submitted ✅
    2. Processing Started 🔄
    3. Fraud Check Complete ✅
    4. Under Review 📋 (or Auto-Approved ✅)
    5. Payment Processed 💰
```

**Screen Design: Claim Tracker (Real-Time)**
```
┌─────────────────────────────────────────┐
│  📋 Claim Status Tracker                │
│  CLM-2026-000123                        │
├─────────────────────────────────────────┤
│                                         │
│  🟢 Live Updates Active                 │
│                                         │
│  ━━━━━━━━●━━━━━━━━━━━━━━━━━━          │
│  │      │                                │
│  ✅     🔄 Processing                    │
│  Submitted  ↑ You are here              │
│                                         │
│  Timeline:                              │
│  ┌───────────────────────────────────┐ │
│  │ ✅ Submitted                       │ │
│  │    Today at 10:30 AM               │ │
│  │    Hospital: UTH                   │ │
│  │                                    │ │
│  │ ✅ Fraud Check Passed              │ │
│  │    Today at 10:31 AM               │ │
│  │    Score: 15/100 (Low Risk)        │ │
│  │                                    │ │
│  │ 🔄 Under Review                    │ │
│  │    Today at 10:32 AM               │ │
│  │    Reason: High amount             │ │
│  │    Expected: Within 24 hours       │ │
│  └───────────────────────────────────┘ │
│                                         │
│  Claim Details:                         │
│  Amount Claimed: K 8,500                │
│  Service Date: Jan 30, 2026             │
│  Diagnosis: Appendicitis                │
│  Hospital: UTH                          │
│                                         │
│  [View Details] [Contact Support]      │
│                                         │
└─────────────────────────────────────────┘

(Real-time update notification pops up):
┌─────────────────────────────────────────┐
│  🎉 Great News!                         │
│  Your claim has been approved           │
│                                         │
│  Approved Amount: K 8,250               │
│  Copay (Your Part): K 250               │
│  Paid to Hospital: K 8,000              │
│                                         │
│  [View Updated Status]                  │
└─────────────────────────────────────────┘
```

---

### Journey 4: Staff Handling Fraud Alert

**User Flow:**
```
Staff Dashboard
    ↓
🔴 Critical Fraud Alert! (WebSocket notification)
    ↓
Alert Detail View
    - Fraud score & flags
    - Member history
    - Provider history
    - Claim details
    ↓
Investigation Actions:
    - Assign to self
    - Add investigation notes
    - Request documents
    - Approve/Reject/Blacklist
```

**Screen Design: Fraud Alert Dashboard**
```
┌─────────────────────────────────────────┐
│  🚨 Fraud Detection Dashboard           │
│  🟢 Live Monitoring Active              │
├─────────────────────────────────────────┤
│                                         │
│  Active Alerts (12)                     │
│  ┌─────────────────────────────────────│
│  │ Filter: ● All  ○ Critical  ○ High   │
│  └─────────────────────────────────────│
│                                         │
│  ┌───────────────────────────────────┐ │
│  │ 🔴 CRITICAL • FRA-2026-000045     │ │
│  │ Score: 95/100                      │ │
│  │ Flags: DUPLICATE_CLAIM,            │ │
│  │        HIGH_FREQUENCY              │ │
│  │                                    │ │
│  │ Member: John Doe (MED-001234)      │ │
│  │ Amount: K 15,000                   │ │
│  │ Provider: UTH                      │ │
│  │                                    │ │
│  │ 🕐 Just now • Unassigned           │ │
│  │                                    │ │
│  │ [Investigate Now] [Dismiss]        │ │
│  ├───────────────────────────────────┤ │
│  │ 🟠 HIGH • FRA-2026-000044         │ │
│  │ Score: 78/100                      │ │
│  │ ...                                │ │
│  └───────────────────────────────────┘ │
│                                         │
│  Today's Summary:                       │
│  New Alerts: 12                         │
│  Investigated: 8                        │
│  Confirmed Fraud: 2                     │
│  False Positives: 6                     │
│  Detection Accuracy: 25%                │
│                                         │
└─────────────────────────────────────────┘
```

---

## Key Interface Components

### 1. **Real-Time Status Badge**
```
🟢 Live        - Connected, receiving updates
🟡 Connecting  - Trying to connect
🔴 Offline     - No real-time updates
```

### 2. **Smart Notifications**
```
Position: Top-right corner
Auto-dismiss: 5 seconds (for info)
Manual dismiss: Required for critical alerts

Types:
  ℹ️  Info (blue)     - "Eligibility check complete"
  ✅ Success (green)  - "Claim approved!"
  ⚠️  Warning (yellow)- "Low benefit balance"
  ❌ Error (red)      - "Claim rejected"
```

### 3. **Progress Indicators**
```
Linear Progress (for known steps):
  ━━━━━━━●━━━━━━━━
  Step 3 of 5

Circular Progress (for unknown duration):
  ⏳ Processing...

Animated Checkmarks (for completion):
  ✅ → Expand with green pulse
```

### 4. **Benefit Balance Widget**
```
┌─────────────────────────────────────┐
│  Doctor Consultations               │
│  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ │
│  K 4,500 / K 5,000 (90%)            │
│  🟢 Good                            │
├─────────────────────────────────────┤
│  In-Patient (Hospitalization)       │
│  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ │
│  K 8,000 / K 50,000 (16%)           │
│  🟢 Excellent                       │
└─────────────────────────────────────┘
```

---

## Mobile-First Design Principles

### Bottom Navigation (Mobile)
```
┌─────────────────────────────────────┐
│                                     │
│  [Main Content Area]                │
│                                     │
│                                     │
└─────────────────────────────────────┘
┌─────────────────────────────────────┐
│  🏠        📋        💰        👤   │
│  Home    Claims  Benefits  Profile  │
└─────────────────────────────────────┘
```

### Quick Actions (FAB - Floating Action Button)
```
Main screen has a big + button (bottom-right):
  Tap to reveal:
    - Check Eligibility
    - Submit Claim
    - Request Pre-Auth
```

---

## Implementation Recommendations

### Tech Stack Suggestions

#### Option 1: Angular (Your Current Frontend)
```typescript
// Real-time service integration
import { RealtimeClaimService } from '@/services/realtime-claim.service';

export class ClaimTrackerComponent {
    claim$ = this.realtimeService.subscribeToClaimUpdates(this.claimId);

    constructor(private realtimeService: RealtimeClaimService) {}
}
```

**UI Library**: Angular Material
- Clean, professional design
- Excellent accessibility
- Robust form handling

#### Option 2: React (Alternative)
```typescript
// Real-time hook
import { useRealtimeClaim } from '@/hooks/useRealtimeClaim';

export function ClaimTracker({ claimId }) {
    const { claim, isLive } = useRealtimeClaim(claimId);

    return (
        <div>
            {isLive && <LiveIndicator />}
            <ClaimStatus claim={claim} />
        </div>
    );
}
```

**UI Library**: Shadcn/ui or Chakra UI
- Modern, customizable
- Excellent TypeScript support

### State Management

**For Angular**:
```typescript
// NgRx for global state
interface AppState {
    claims: ClaimsState;
    benefits: BenefitsState;
    realtime: RealtimeState;
}

// Actions triggered by WebSocket events
@Effect()
claimStatusUpdated$ = this.actions$.pipe(
    ofType('[WebSocket] Claim Status Updated'),
    map(action => new UpdateClaimStatus(action.payload))
);
```

**For React**:
```typescript
// Zustand or Redux Toolkit
const useClaimStore = create((set) => ({
    claims: [],
    updateClaimStatus: (id, status) =>
        set((state) => ({
            claims: state.claims.map(c =>
                c.id === id ? { ...c, status } : c
            ),
        })),
}));
```

---

## Screen-by-Screen Breakdown

### Member Portal Screens

| Screen | Purpose | Key Features |
|--------|---------|--------------|
| **Dashboard** | Overview | Active policy, benefit summary, recent claims |
| **Eligibility Checker** | Pre-visit verification | Real-time check, copay estimator |
| **Claims List** | View all claims | Filter by status, search, export |
| **Claim Tracker** | Real-time status | WebSocket updates, timeline view |
| **Benefits Overview** | Balance tracking | Visual progress bars, utilization % |
| **Profile** | Member details | Personal info, dependents, documents |

### Provider Portal Screens

| Screen | Purpose | Key Features |
|--------|---------|--------------|
| **Quick Submit** | Fast claim entry | Barcode scanner, eligibility check |
| **Claims Dashboard** | Monitor submissions | Real-time status, approval rate |
| **Pre-Auth Requests** | Authorization workflow | Track approvals, expirations |
| **Analytics** | Performance metrics | Rejection rate, avg processing time |
| **API Keys** | Integration settings | Generate keys, view logs |

### Staff Portal Screens

| Screen | Purpose | Key Features |
|--------|---------|--------------|
| **Work Queue** | Pending claims | Prioritized list, auto-refresh |
| **Fraud Alerts** | Investigate alerts | Real-time alerts, investigation tools |
| **Member 360** | Complete member view | Claims history, risk score, notes |
| **Reports** | Analytics dashboard | KPIs, trends, fraud detection stats |

---

## Accessibility & UX Best Practices

### Accessibility (WCAG 2.1 AA Compliance)
- ✅ Keyboard navigation for all actions
- ✅ Screen reader support (ARIA labels)
- ✅ High contrast mode option
- ✅ Text scaling (up to 200%)
- ✅ Focus indicators on interactive elements

### Loading States
```
Skeleton screens (not spinners):
┌─────────────────────────────────────┐
│  ▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅▅                 │
│  ▅▅▅▅▅▅▅▅▅                          │
│  ▅▅▅▅▅▅▅▅▅▅▅▅▅▅                    │
└─────────────────────────────────────┘
```

### Error Handling
```
User-friendly error messages:

❌ Bad:  "Error 500: Internal Server Error"

✅ Good: "Oops! We couldn't process your claim
         right now. Please try again in a
         moment, or contact support if the
         issue persists."

         [Retry] [Contact Support]
```

### Empty States
```
Instead of blank screen:

┌─────────────────────────────────────┐
│           📋                        │
│     No claims yet                   │
│                                     │
│  You haven't submitted any claims.  │
│  When you do, they'll appear here.  │
│                                     │
│     [Submit Your First Claim]       │
└─────────────────────────────────────┘
```

---

## Performance Optimization

### Frontend Caching Strategy
```typescript
// Cache eligibility results for 5 minutes
const ELIGIBILITY_CACHE_TTL = 5 * 60 * 1000;

// Service Worker for offline support
self.addEventListener('fetch', (event) => {
    if (event.request.url.includes('/api/benefits')) {
        event.respondWith(cacheFirst(event.request));
    }
});
```

### Lazy Loading
```typescript
// Route-based code splitting
const ClaimTracker = lazy(() => import('./pages/ClaimTracker'));
const FraudDashboard = lazy(() => import('./pages/FraudDashboard'));
```

### WebSocket Reconnection
```typescript
// Auto-reconnect with exponential backoff
const reconnectWebSocket = (attempt = 1) => {
    const delay = Math.min(1000 * Math.pow(2, attempt), 30000);
    setTimeout(() => {
        echo.connector.connect();
    }, delay);
};

echo.connector.pusher.connection.bind('disconnected', () => {
    reconnectWebSocket();
});
```

---

## Progressive Disclosure

Show only what's needed:

### Simple View (Default)
```
Claim Status: Approved ✅
Amount: K 5,000
```

### Detailed View (On Click)
```
Claim Status: Approved ✅
Claim Number: CLM-2026-000123
Amount Claimed: K 5,000
Approved Amount: K 4,750
Copay: K 250
Service Date: Jan 30, 2026
Processed Date: Jan 30, 2026
Fraud Score: 15/100 (Low Risk)
Auto-Adjudicated: Yes
Processing Time: 45 seconds
```

---

## Summary

**Key UX Principles**:
1. **Real-time First** - Show live updates, not static data
2. **Mobile Optimized** - Touch-friendly, bottom navigation
3. **Progressive Disclosure** - Simple by default, detailed on demand
4. **Accessible** - WCAG 2.1 AA compliant
5. **Fast** - <500ms eligibility, <2s claim processing feedback
6. **Clear Status** - Always show current state
7. **Proactive Help** - Contextual tips and warnings

**Implementation Priority**:
1. Member Portal (Claim Tracker, Eligibility Checker)
2. Provider Portal (Quick Submit)
3. Staff Portal (Fraud Alerts)
4. Mobile Apps (iOS/Android)

This UX plan ensures users have a **simple, intuitive, real-time experience** that matches modern expectations for digital health services! 🎯
