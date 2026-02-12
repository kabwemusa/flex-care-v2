# Frontend Real-Time Integration Setup

## Step 1: Install Dependencies

Run this command in the `front-end` directory:

```bash
npm install laravel-echo pusher-js --save
```

## Step 2: Files Created

✅ **WebSocketService** - `projects/libs/core/http/src/lib/services/websocket.service.ts`
- Handles all WebSocket connections
- Provides methods to subscribe to claims, fraud alerts, member updates
- Auto-reconnects on disconnect
- Manages connection state with Angular signals

## Step 3: Next Steps

After installing dependencies, the WebSocketService will be ready to use. The integration plan continues with:

1. ✅ WebSocketService created
2. 🔄 Install `laravel-echo` and `pusher-js`
3. ⏳ Update ClaimStore for real-time updates
4. ⏳ Add environment configuration
5. ⏳ Create fraud alerts component
6. ⏳ Create eligibility checker widget

## Usage Example

```typescript
import { WebSocketService } from 'core-http';

// In your component
constructor(private wsService: WebSocketService) {
  // Initialize with auth token
  this.wsService.initialize({
    key: 'your-reverb-key',
    wsHost: 'localhost',
    wsPort: 8080,
  }, authToken);
}

// Subscribe to claim updates
this.wsService.subscribeToClaimUpdates(claimId, (event) => {
  console.log('Claim updated:', event);
  // Update UI
});

// Check connection status
if (this.wsService.isConnected()) {
  console.log('WebSocket is connected');
}
```

## Connection Status Signal

The service provides a reactive signal for connection status:

```typescript
// In your template
<div *ngIf="wsService.isConnected()">
  🟢 Live Updates Active
</div>

<div *ngIf="wsService.isConnecting()">
  🟡 Connecting...
</div>

<div *ngIf="!wsService.isConnected() && !wsService.isConnecting()">
  🔴 Offline
</div>
```

## Run This Command Now

```bash
cd front-end
npm install laravel-echo pusher-js --save
```

Once complete, we'll continue with updating the ClaimStore!
