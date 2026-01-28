import { Component, computed, inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { NgApexchartsModule } from 'ng-apexcharts';
import { MatTableModule } from '@angular/material/table';
import { MatIconModule } from '@angular/material/icon';
import { ReportStore } from '../../stores/report.store';
import { KpiCardComponent } from '../shared/kpi-card/kpi-card';
import { ChartContainerComponent } from '../shared/chart-container/chart-container';
import {
  CHART_COLORS,
  DONUT_CHART_OPTIONS,
  COMBO_CHART_OPTIONS,
  STACKED_BAR_OPTIONS,
  HORIZONTAL_BAR_OPTIONS,
  INVOICE_STATUS_LABELS,
  PAYMENT_METHOD_LABELS,
  AGING_BUCKET_LABELS,
  formatCurrency,
} from '../../constants/report.constants';

@Component({
  selector: 'lib-billing-reports',
  standalone: true,
  imports: [
    CommonModule,
    NgApexchartsModule,
    MatTableModule,
    MatIconModule,
    KpiCardComponent,
    ChartContainerComponent,
  ],
  templateUrl: './billing-reports.html',
})
export class BillingReportsComponent implements OnInit {
  readonly store = inject(ReportStore);
  readonly chartColors = CHART_COLORS;
  readonly formatCurrency = formatCurrency;

  // Chart options
  readonly donutChartOptions = DONUT_CHART_OPTIONS;
  readonly comboChartOptions = COMBO_CHART_OPTIONS;
  readonly stackedBarOptions = STACKED_BAR_OPTIONS;
  readonly horizontalBarOptions = HORIZONTAL_BAR_OPTIONS;

  readonly agingChartColors = [
    CHART_COLORS.current,
    CHART_COLORS.days_1_30,
    CHART_COLORS.days_31_60,
    CHART_COLORS.days_61_90,
    CHART_COLORS.days_90_plus,
  ];

  readonly paymentMethodColors = [
    CHART_COLORS.bank_transfer,
    CHART_COLORS.eft,
    CHART_COLORS.cheque,
    CHART_COLORS.mobile_money,
    CHART_COLORS.cash,
    CHART_COLORS.direct_debit,
    CHART_COLORS.card,
  ];

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

  readonly currencyDataLabels = {
    enabled: true,
    formatter: (val: number) => 'K' + (val / 1000).toFixed(0),
  };

  // Table columns
  readonly displayedColumns = ['policy_number', 'holder_name', 'outstanding', 'days_overdue'];

  // Computed data
  readonly reports = computed(() => this.store.billingReports());

  // Invoice Status Chart
  readonly invoiceStatusSeries = computed(() => {
    const data = this.reports()?.invoice_status_distribution ?? [];
    return data.map((d) => d.count);
  });

  readonly invoiceStatusLabels = computed(() => {
    const data = this.reports()?.invoice_status_distribution ?? [];
    return data.map((d) => INVOICE_STATUS_LABELS[d.status] ?? d.status);
  });

  readonly invoiceStatusColors = computed(() => {
    const data = this.reports()?.invoice_status_distribution ?? [];
    return data.map((d) => (CHART_COLORS as Record<string, string>)[d.status] ?? CHART_COLORS.secondary);
  });

  // Collection Trend Chart (Combo: Bars for invoiced, Line for collected)
  readonly collectionTrendSeries = computed(() => {
    const data = this.reports()?.collection_trend ?? [];
    if (!data.length) return [];

    return [
      { name: 'Invoiced', type: 'column', data: data.map((d) => d.invoiced) },
      { name: 'Collected', type: 'line', data: data.map((d) => d.collected) },
    ];
  });

  readonly collectionTrendXAxis = computed(() => {
    const data = this.reports()?.collection_trend ?? [];
    return {
      categories: data.map((d) => d.month),
      labels: { style: { fontSize: '12px' } },
    };
  });

  // Aging Analysis Chart
  readonly agingChartSeries = computed(() => {
    const data = this.reports()?.aging_analysis ?? [];
    if (!data.length) return [];

    return [{ name: 'Outstanding', data: data.map((d) => d.amount) }];
  });

  readonly agingXAxis = computed(() => {
    const data = this.reports()?.aging_analysis ?? [];
    return {
      categories: data.map((d) => AGING_BUCKET_LABELS[d.bucket] ?? d.bucket),
      labels: { style: { fontSize: '12px' } },
    };
  });

  // Payment Methods Chart
  readonly paymentMethodsSeries = computed(() => {
    const data = this.reports()?.payment_methods ?? [];
    return data.map((d) => d.amount);
  });

  readonly paymentMethodsLabels = computed(() => {
    const data = this.reports()?.payment_methods ?? [];
    return data.map((d) => PAYMENT_METHOD_LABELS[d.method] ?? d.method);
  });

  // Top Outstanding
  readonly topOutstanding = computed(() => this.reports()?.top_outstanding ?? []);

  // Daily Collections Chart
  readonly dailyCollectionsSeries = computed(() => {
    const data = this.reports()?.daily_collections ?? [];
    if (!data.length) return [];

    return [{ name: 'Collections', data: data.map((d) => d.amount) }];
  });

  readonly dailyCollectionsXAxis = computed(() => {
    const data = this.reports()?.daily_collections ?? [];
    return {
      categories: data.map((d) => d.date),
      labels: {
        style: { fontSize: '10px' },
        rotate: -45,
        rotateAlways: true,
      },
    };
  });

  ngOnInit() {
    if (!this.reports()) {
      this.store.loadBillingReports().subscribe();
    }
  }

  refresh() {
    this.store.loadBillingReports().subscribe();
  }
}
