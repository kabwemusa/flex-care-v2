import { Injectable } from '@angular/core';

export interface WebSocketConfig {
  enabled: boolean;
  reverbKey: string;
  wsHost: string;
  wsPort: number;
  forceTLS: boolean;
}

export interface AppConfig {
  apiUrl: string;
  production: boolean;
  appName: string;
  version: string;
  websocket: WebSocketConfig;
}

@Injectable({
  providedIn: 'root',
})
export class ConfigService {
  private config: AppConfig = {
    // Empty for development (uses Angular proxy), set full URL for production
    // apiUrl: '',
    // Production URL (set via environment or app initialization):
    apiUrl: 'https://flex-care-v2-production-022b.up.railway.app',
    production: false,
    appName: 'FlexCare',
    version: '1.0.0',
    websocket: {
      enabled: true,
      reverbKey: 'your-reverb-app-key',
      wsHost: 'localhost',
      wsPort: 8080,
      forceTLS: false,
    },
  };

  /**
   * Set application configuration
   */
  setConfig(config: Partial<AppConfig>): void {
    this.config = { ...this.config, ...config };
  }

  /**
   * Get full configuration
   */
  getConfig(): AppConfig {
    return this.config;
  }

  /**
   * Get API base URL
   */
  getApiUrl(): string {
    return this.config.apiUrl;
  }

  /**
   * Check if running in production
   */
  isProduction(): boolean {
    return this.config.production;
  }

  /**
   * Get application name
   */
  getAppName(): string {
    return this.config.appName;
  }

  /**
   * Get application version
   */
  getVersion(): string {
    return this.config.version;
  }

  /**
   * Get WebSocket configuration
   */
  getWebSocketConfig(): WebSocketConfig {
    return this.config.websocket;
  }

  /**
   * Check if WebSocket is enabled
   */
  isWebSocketEnabled(): boolean {
    return this.config.websocket.enabled;
  }
}
