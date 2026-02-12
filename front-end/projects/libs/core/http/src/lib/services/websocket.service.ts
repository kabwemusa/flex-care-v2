import { Injectable, signal, computed, inject, DestroyRef } from '@angular/core';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
  interface Window {
    Pusher: any;
    Echo: Echo<any>;
  }
}

export interface WebSocketConfig {
  key: string;
  wsHost: string;
  wsPort: number;
  forceTLS: boolean;
  authEndpoint: string;
}

export interface ClaimStatusUpdate {
  claim_id: string;
  claim_number: string;
  status: string;
  previous_status: string;
  details: any;
  timestamp: string;
}

export interface FraudAlert {
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

export interface BenefitBalanceUpdate {
  member_id: string;
  benefit_id: string;
  benefit_name: string;
  previous_balance: number;
  new_balance: number;
  change_amount: number;
  change_type: 'used' | 'reserved' | 'released' | 'adjusted';
  timestamp: string;
}

@Injectable({
  providedIn: 'root'
})
export class WebSocketService {
  private readonly destroyRef = inject(DestroyRef);

  private echo: Echo<any> | null = null;
  private readonly connectionState = signal<'connected' | 'connecting' | 'disconnected'>('disconnected');
  private readonly subscribedChannels = signal<Set<string>>(new Set());

  // Public observables
  readonly isConnected = computed(() => this.connectionState() === 'connected');
  readonly isConnecting = computed(() => this.connectionState() === 'connecting');
  readonly connectionStatus = computed(() => this.connectionState());

  constructor() {
    // Auto-cleanup on service destroy
    this.destroyRef.onDestroy(() => {
      this.disconnect();
    });
  }

  /**
   * Initialize WebSocket connection
   */
  initialize(config: Partial<WebSocketConfig> = {}, authToken?: string): void {
    if (this.echo) {
      console.warn('WebSocket already initialized');
      return;
    }

    window.Pusher = Pusher;

    const defaultConfig: WebSocketConfig = {
      key: 'your-reverb-app-key',
      wsHost: 'localhost',
      wsPort: 8080,
      forceTLS: false,
      authEndpoint: 'http://localhost:8000/broadcasting/auth',
      ...config
    };

    this.echo = new Echo({
      broadcaster: 'reverb',
      key: defaultConfig.key,
      wsHost: defaultConfig.wsHost,
      wsPort: defaultConfig.wsPort,
      wssPort: defaultConfig.wsPort,
      forceTLS: defaultConfig.forceTLS,
      enabledTransports: ['ws', 'wss'],
      authEndpoint: defaultConfig.authEndpoint,
      auth: {
        headers: {
          Authorization: `Bearer ${authToken || this.getStoredToken()}`,
          Accept: 'application/json',
        },
      },
    });

    this.setupConnectionListeners();
    this.connectionState.set('connecting');

    console.log('✅ WebSocket service initialized');
  }

  /**
   * Get stored auth token from localStorage
   */
  private getStoredToken(): string {
    return localStorage.getItem('auth_token') || '';
  }

  /**
   * Setup connection event listeners
   */
  private setupConnectionListeners(): void {
    if (!this.echo) return;

    this.echo.connector.pusher.connection.bind('connected', () => {
      console.log('✅ WebSocket connected');
      this.connectionState.set('connected');
    });

    this.echo.connector.pusher.connection.bind('disconnected', () => {
      console.log('❌ WebSocket disconnected');
      this.connectionState.set('disconnected');
    });

    this.echo.connector.pusher.connection.bind('connecting', () => {
      console.log('🔄 WebSocket connecting...');
      this.connectionState.set('connecting');
    });

    this.echo.connector.pusher.connection.bind('error', (error: any) => {
      console.error('❌ WebSocket error:', error);
    });

    this.echo.connector.pusher.connection.bind('unavailable', () => {
      console.warn('⚠️ WebSocket unavailable, will retry...');
    });
  }

  /**
   * Get Echo instance
   */
  getEcho(): Echo<any> {
    if (!this.echo) {
      console.warn('WebSocket not initialized, initializing with defaults...');
      this.initialize();
    }
    return this.echo!;
  }

  /**
   * Disconnect from WebSocket
   */
  disconnect(): void {
    if (this.echo) {
      // Leave all subscribed channels
      this.subscribedChannels().forEach(channel => {
        this.echo?.leave(channel);
      });

      this.echo.disconnect();
      this.echo = null;
      this.connectionState.set('disconnected');
      this.subscribedChannels.set(new Set());
      console.log('🔌 WebSocket disconnected');
    }
  }

  /**
   * Subscribe to claim updates
   */
  subscribeToClaimUpdates(claimId: string, callback: (event: ClaimStatusUpdate) => void): void {
    const channelName = `claim.${claimId}`;

    this.getEcho()
      .private(channelName)
      .listen('.claim.status.updated', callback);

    this.subscribedChannels.update(channels => new Set(channels).add(channelName));
    console.log(`📡 Subscribed to claim updates: ${claimId}`);
  }

  /**
   * Unsubscribe from claim updates
   */
  unsubscribeFromClaim(claimId: string): void {
    const channelName = `claim.${claimId}`;
    this.getEcho().leave(channelName);

    this.subscribedChannels.update(channels => {
      const newChannels = new Set(channels);
      newChannels.delete(channelName);
      return newChannels;
    });

    console.log(`📡 Unsubscribed from claim: ${claimId}`);
  }

  /**
   * Subscribe to member updates (all events for a member)
   */
  subscribeToMemberUpdates(
    memberId: string,
    callbacks: {
      onClaimUpdate?: (event: ClaimStatusUpdate) => void;
      onBenefitUpdate?: (event: BenefitBalanceUpdate) => void;
      onPreauthUpdate?: (event: any) => void;
    }
  ): void {
    const channelName = `member.${memberId}`;
    const channel = this.getEcho().private(channelName);

    if (callbacks.onClaimUpdate) {
      channel.listen('.claim.status.updated', callbacks.onClaimUpdate);
    }

    if (callbacks.onBenefitUpdate) {
      channel.listen('.benefit.balance.updated', callbacks.onBenefitUpdate);
    }

    if (callbacks.onPreauthUpdate) {
      channel.listen('.preauth.status.updated', callbacks.onPreauthUpdate);
    }

    this.subscribedChannels.update(channels => new Set(channels).add(channelName));
    console.log(`📡 Subscribed to member updates: ${memberId}`);
  }

  /**
   * Unsubscribe from member updates
   */
  unsubscribeFromMember(memberId: string): void {
    const channelName = `member.${memberId}`;
    this.getEcho().leave(channelName);

    this.subscribedChannels.update(channels => {
      const newChannels = new Set(channels);
      newChannels.delete(channelName);
      return newChannels;
    });

    console.log(`📡 Unsubscribed from member: ${memberId}`);
  }

  /**
   * Subscribe to fraud alerts (staff only)
   */
  subscribeToFraudAlerts(callback: (event: FraudAlert) => void): void {
    const channelName = 'fraud-alerts';

    this.getEcho()
      .private(channelName)
      .listen('.fraud.alert.created', callback);

    this.subscribedChannels.update(channels => new Set(channels).add(channelName));
    console.log('🚨 Subscribed to fraud alerts');
  }

  /**
   * Subscribe to fraud alerts by severity
   */
  subscribeToFraudAlertsBySeverity(severity: 'critical' | 'high' | 'medium' | 'low', callback: (event: FraudAlert) => void): void {
    const channelName = `fraud-alerts.${severity}`;

    this.getEcho()
      .private(channelName)
      .listen('.fraud.alert.created', callback);

    this.subscribedChannels.update(channels => new Set(channels).add(channelName));
    console.log(`🚨 Subscribed to ${severity} fraud alerts`);
  }

  /**
   * Unsubscribe from fraud alerts
   */
  unsubscribeFromFraudAlerts(): void {
    const channelName = 'fraud-alerts';
    this.getEcho().leave(channelName);

    this.subscribedChannels.update(channels => {
      const newChannels = new Set(channels);
      newChannels.delete(channelName);
      return newChannels;
    });

    console.log('🚨 Unsubscribed from fraud alerts');
  }

  /**
   * Subscribe to provider updates (for provider portal)
   */
  subscribeToProviderUpdates(providerId: string, callback: (event: any) => void): void {
    const channelName = `provider.${providerId}`;

    this.getEcho()
      .private(channelName)
      .listen('.claim.status.updated', callback)
      .listen('.eligibility.checked', callback);

    this.subscribedChannels.update(channels => new Set(channels).add(channelName));
    console.log(`📡 Subscribed to provider updates: ${providerId}`);
  }

  /**
   * Get list of currently subscribed channels
   */
  getSubscribedChannels(): string[] {
    return Array.from(this.subscribedChannels());
  }

  /**
   * Check if connected
   */
  isWebSocketConnected(): boolean {
    return this.isConnected();
  }
}
