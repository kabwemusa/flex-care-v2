import { Component, inject, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatTabsModule } from '@angular/material/tabs';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { ReportStore } from '../../stores/report.store';
import { KpiCardComponent } from '../shared/kpi-card/kpi-card';
import { DateRangePickerComponent, DateRange } from '../shared/date-range-picker/date-range-picker';
import { ApprovalMetricsComponent } from '../approval-metrics/approval-metrics';
import { PolicyAnalyticsComponent } from '../policy-analytics/policy-analytics';
import { BillingReportsComponent } from '../billing-reports/billing-reports';

@Component({
  selector: 'lib-report-dashboard',
  standalone: true,
  imports: [
    CommonModule,
    MatTabsModule,
    MatButtonModule,
    MatIconModule,
    KpiCardComponent,
    DateRangePickerComponent,
    ApprovalMetricsComponent,
    PolicyAnalyticsComponent,
    BillingReportsComponent,
  ],
  templateUrl: './report-dashboard.html',
})
export class ReportDashboardComponent implements OnInit {
  readonly store = inject(ReportStore);
  selectedTab = signal(0);

  ngOnInit() {
    this.loadData();
  }

  loadData() {
    this.store.loadSummary().subscribe();
  }

  refreshAll() {
    this.store.loadAllReports();
  }

  onDateRangeChange(range: DateRange) {
    this.store.setFilters(range);
    this.store.loadAllReports();
  }

  onTabChange(index: number) {
    this.selectedTab.set(index);

    // Load data for the selected tab if not already loaded
    switch (index) {
      case 0:
        if (!this.store.approvalMetrics()) {
          this.store.loadApprovalMetrics().subscribe();
        }
        break;
      case 1:
        if (!this.store.policyAnalytics()) {
          this.store.loadPolicyAnalytics().subscribe();
        }
        break;
      case 2:
        if (!this.store.billingReports()) {
          this.store.loadBillingReports().subscribe();
        }
        break;
    }
  }
}
