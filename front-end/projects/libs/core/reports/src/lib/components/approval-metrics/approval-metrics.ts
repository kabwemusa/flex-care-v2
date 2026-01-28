import { Component, computed, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { NgApexchartsModule } from 'ng-apexcharts';
import { ReportStore } from '../../stores/report.store';
import { KpiCardComponent } from '../shared/kpi-card/kpi-card';
import { ChartContainerComponent } from '../shared/chart-container/chart-container';
import {
  CHART_COLORS,
  DONUT_CHART_OPTIONS,
  AREA_CHART_OPTIONS,
  BAR_CHART_OPTIONS,
  HORIZONTAL_BAR_OPTIONS,
  APPROVAL_STATUS_LABELS,
  ENTITY_TYPE_LABELS,
} from '../../constants/report.constants';

@Component({
  selector: 'lib-approval-metrics',
  standalone: true,
  imports: [
    CommonModule,
    NgApexchartsModule,
    KpiCardComponent,
    ChartContainerComponent,
  ],
  templateUrl: './approval-metrics.html',
})
export class ApprovalMetricsComponent implements OnInit {
  readonly store = inject(ReportStore);
  readonly chartColors = CHART_COLORS;

  // Chart options
  readonly donutChartOptions = DONUT_CHART_OPTIONS;
  readonly areaChartOptions = AREA_CHART_OPTIONS;
  readonly barChartOptions = BAR_CHART_OPTIONS;
  readonly horizontalBarOptions = HORIZONTAL_BAR_OPTIONS;

  readonly trendChartColors = [CHART_COLORS.approved, CHART_COLORS.rejected, CHART_COLORS.returned];

  // Custom data labels with formatters
  readonly daysDataLabels = {
    enabled: true,
    formatter: (val: number) => val.toFixed(1) + ' days',
  };

  // Computed data
  readonly metrics = computed(() => this.store.approvalMetrics());

  // Status Distribution Chart
  readonly statusChartSeries = computed(() => {
    const data = this.metrics()?.status_distribution ?? [];
    return data.map((d) => d.count);
  });

  readonly statusChartLabels = computed(() => {
    const data = this.metrics()?.status_distribution ?? [];
    return data.map((d) => APPROVAL_STATUS_LABELS[d.status] ?? d.status);
  });

  readonly statusChartColors = computed(() => {
    const data = this.metrics()?.status_distribution ?? [];
    return data.map((d) => (CHART_COLORS as Record<string, string>)[d.status] ?? CHART_COLORS.secondary);
  });

  // Trend Chart
  readonly trendChartSeries = computed(() => {
    const data = this.metrics()?.trend ?? [];
    if (!data.length) return [];

    return [
      { name: 'Approved', data: data.map((d) => d.approved) },
      { name: 'Rejected', data: data.map((d) => d.rejected) },
      { name: 'Returned', data: data.map((d) => d.returned) },
    ];
  });

  readonly trendChartXAxis = computed(() => {
    const data = this.metrics()?.trend ?? [];
    return {
      categories: data.map((d) => d.date),
      labels: {
        style: { fontSize: '12px' },
      },
    };
  });

  // Entity Type Chart
  readonly entityTypeChartSeries = computed(() => {
    const data = this.metrics()?.by_entity_type ?? [];
    if (!data.length) return [];

    return [{ name: 'Avg Days', data: data.map((d) => d.avg_days) }];
  });

  readonly entityTypeXAxis = computed(() => {
    const data = this.metrics()?.by_entity_type ?? [];
    return {
      categories: data.map((d) => ENTITY_TYPE_LABELS[d.entity_type] ?? d.entity_type),
      labels: {
        style: { fontSize: '12px' },
      },
    };
  });

  // Approver Performance Chart
  readonly approverChartSeries = computed(() => {
    const data = this.metrics()?.approver_performance?.slice(0, 10) ?? [];
    if (!data.length) return [];

    return [
      { name: 'Approved', data: data.map((d) => d.approved_count) },
      { name: 'Rejected', data: data.map((d) => d.rejected_count) },
    ];
  });

  readonly approverXAxis = computed(() => ({
    categories: [],
  }));

  readonly approverYAxis = computed(() => {
    const data = this.metrics()?.approver_performance?.slice(0, 10) ?? [];
    return {
      categories: data.map((d) => d.user_name),
      labels: {
        style: { fontSize: '12px' },
      },
    };
  });

  ngOnInit() {
    if (!this.metrics()) {
      this.store.loadApprovalMetrics().subscribe();
    }
  }

  refresh() {
    this.store.loadApprovalMetrics().subscribe();
  }
}
