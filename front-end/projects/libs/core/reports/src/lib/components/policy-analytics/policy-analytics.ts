import { Component, computed, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { NgApexchartsModule } from 'ng-apexcharts';
import { ReportStore } from '../../stores/report.store';
import { KpiCardComponent } from '../shared/kpi-card/kpi-card';
import { ChartContainerComponent } from '../shared/chart-container/chart-container';
import {
  CHART_COLORS,
  DONUT_CHART_OPTIONS,
  LINE_CHART_OPTIONS,
  STACKED_BAR_OPTIONS,
  HORIZONTAL_BAR_OPTIONS,
  POLICY_STATUS_LABELS,
  POLICY_TYPE_LABELS,
} from '../../constants/report.constants';

@Component({
  selector: 'lib-policy-analytics',
  standalone: true,
  imports: [
    CommonModule,
    NgApexchartsModule,
    KpiCardComponent,
    ChartContainerComponent,
  ],
  templateUrl: './policy-analytics.html',
})
export class PolicyAnalyticsComponent implements OnInit {
  readonly store = inject(ReportStore);
  readonly chartColors = CHART_COLORS;
  readonly policyTypeLabels = POLICY_TYPE_LABELS;

  // Chart options
  readonly donutChartOptions = DONUT_CHART_OPTIONS;
  readonly lineChartOptions = LINE_CHART_OPTIONS;
  readonly stackedBarOptions = STACKED_BAR_OPTIONS;
  readonly horizontalBarOptions = HORIZONTAL_BAR_OPTIONS;

  readonly typeChartColors = [CHART_COLORS.primary, CHART_COLORS.success, CHART_COLORS.warning, CHART_COLORS.info];
  readonly premiumChartColors = [CHART_COLORS.primary, CHART_COLORS.success, CHART_COLORS.warning];
  readonly trendChartColors = [CHART_COLORS.success, CHART_COLORS.danger, CHART_COLORS.info];

  // Custom formatters for charts
  readonly percentDataLabels = {
    enabled: true,
    formatter: (val: number) => val.toFixed(1) + '%',
  };

  readonly currencyYAxis = {
    labels: {
      formatter: (val: number) => 'K' + (val / 1000).toFixed(0),
    },
  };

  // Computed data
  readonly analytics = computed(() => this.store.policyAnalytics());

  // Status Distribution Chart
  readonly statusChartSeries = computed(() => {
    const data = this.analytics()?.status_distribution ?? [];
    return data.map((d) => d.count);
  });

  readonly statusChartLabels = computed(() => {
    const data = this.analytics()?.status_distribution ?? [];
    return data.map((d) => POLICY_STATUS_LABELS[d.status] ?? d.status);
  });

  readonly statusChartColors = computed(() => {
    const data = this.analytics()?.status_distribution ?? [];
    return data.map((d) => (CHART_COLORS as Record<string, string>)[d.status] ?? CHART_COLORS.secondary);
  });

  // Type Distribution Chart
  readonly typeChartSeries = computed(() => {
    const data = this.analytics()?.type_distribution ?? [];
    return data.map((d) => d.count);
  });

  readonly typeChartLabels = computed(() => {
    const data = this.analytics()?.type_distribution ?? [];
    return data.map((d) => POLICY_TYPE_LABELS[d.policy_type] ?? d.policy_type);
  });

  // Premium Breakdown Chart
  readonly premiumChartSeries = computed(() => {
    const data = this.analytics()?.premium_breakdown ?? [];
    if (!data.length) return [];

    return [
      { name: 'Base Premium', data: data.map((d) => d.base_premium) },
      { name: 'Addon Premium', data: data.map((d) => d.addon_premium) },
      { name: 'Loading', data: data.map((d) => d.loading_amount) },
    ];
  });

  readonly premiumXAxis = computed(() => {
    const data = this.analytics()?.premium_breakdown ?? [];
    return {
      categories: data.map((d) => POLICY_TYPE_LABELS[d.policy_type] ?? d.policy_type),
      labels: { style: { fontSize: '12px' } },
    };
  });

  // Trend Chart
  readonly trendChartSeries = computed(() => {
    const data = this.analytics()?.monthly_trend ?? [];
    if (!data.length) return [];

    return [
      { name: 'New Policies', data: data.map((d) => d.new_policies) },
      { name: 'Cancelled', data: data.map((d) => d.cancelled_policies) },
      { name: 'Net Growth', data: data.map((d) => d.net_growth) },
    ];
  });

  readonly trendXAxis = computed(() => {
    const data = this.analytics()?.monthly_trend ?? [];
    return {
      categories: data.map((d) => d.month),
      labels: { style: { fontSize: '12px' } },
    };
  });

  // Renewal Pipeline
  readonly renewalData = computed(() => this.analytics()?.renewal_pipeline);

  readonly renewalTotal = computed(() => {
    const data = this.renewalData();
    if (!data) return 0;
    return data.expiring_30_days + data.expiring_60_days + data.expiring_90_days;
  });

  // Top Plans Chart
  readonly topPlansChartSeries = computed(() => {
    const data = this.analytics()?.top_plans?.slice(0, 10) ?? [];
    if (!data.length) return [];

    return [{ name: 'Policies', data: data.map((d) => d.policy_count) }];
  });

  readonly topPlansXAxis = computed(() => ({
    categories: [],
  }));

  readonly topPlansYAxis = computed(() => {
    const data = this.analytics()?.top_plans?.slice(0, 10) ?? [];
    return {
      categories: data.map((d) => d.plan_name),
      labels: { style: { fontSize: '12px' } },
    };
  });

  ngOnInit() {
    if (!this.analytics()) {
      this.store.loadPolicyAnalytics().subscribe();
    }
  }

  refresh() {
    this.store.loadPolicyAnalytics().subscribe();
  }

  getPercentage(value: number): number {
    const total = this.renewalTotal();
    return total > 0 ? (value / total) * 100 : 0;
  }
}
