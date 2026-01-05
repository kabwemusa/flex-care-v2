import { Injectable } from '@angular/core';

export interface AppConfig {
  apiUrl: string;
  production: boolean;
  appName: string;
  version: string;
}

@Injectable({
  providedIn: 'root',
})
export class ConfigService {
  private config: AppConfig = {
    apiUrl: 'http://localhost:8000',
    production: false,
    appName: 'FlexCare',
    version: '1.0.0',
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
}
