// Report Constants and Chart Configurations

// =========================================================================
// CHART COLORS
// =========================================================================

export const CHART_COLORS = {
  // Primary palette
  primary: '#3b82f6',
  secondary: '#64748b',
  success: '#22c55e',
  warning: '#f59e0b',
  danger: '#ef4444',
  info: '#06b6d4',

  // Approval statuses
  approved: '#22c55e',
  pending: '#f59e0b',
  rejected: '#ef4444',
  returned: '#f97316',
  cancelled: '#94a3b8',

  // Policy statuses
  active: '#22c55e',
  suspended: '#f97316',
  expired: '#94a3b8',
  draft: '#9ca3af',
  lapsed: '#dc2626',
  renewed: '#3b82f6',
  pending_payment: '#eab308',

  // Invoice statuses
  paid: '#22c55e',
  overdue: '#ef4444',
  partially_paid: '#f59e0b',
  sent: '#3b82f6',
  written_off: '#6b7280',

  // Payment methods
  bank_transfer: '#3b82f6',
  eft: '#8b5cf6',
  cheque: '#f59e0b',
  mobile_money: '#22c55e',
  cash: '#64748b',
  direct_debit: '#06b6d4',
  card: '#ec4899',

  // Aging buckets
  current: '#22c55e',
  days_1_30: '#84cc16',
  days_31_60: '#f59e0b',
  days_61_90: '#f97316',
  days_90_plus: '#ef4444',
} as const;

export const STATUS_COLORS: Record<string, string> = {
  // Approval
  approved: CHART_COLORS.approved,
  pending: CHART_COLORS.pending,
  rejected: CHART_COLORS.rejected,
  returned: CHART_COLORS.returned,
  cancelled: CHART_COLORS.cancelled,

  // Policy
  active: CHART_COLORS.active,
  suspended: CHART_COLORS.suspended,
  expired: CHART_COLORS.expired,
  draft: CHART_COLORS.draft,
  lapsed: CHART_COLORS.lapsed,
  renewed: CHART_COLORS.renewed,
  pending_payment: CHART_COLORS.pending_payment,

  // Invoice
  paid: CHART_COLORS.paid,
  overdue: CHART_COLORS.overdue,
  partially_paid: CHART_COLORS.partially_paid,
  sent: CHART_COLORS.sent,
  written_off: CHART_COLORS.written_off,
};

export const CHART_COLOR_PALETTE = [
  '#3b82f6', // blue
  '#22c55e', // green
  '#f59e0b', // amber
  '#ef4444', // red
  '#8b5cf6', // violet
  '#06b6d4', // cyan
  '#ec4899', // pink
  '#f97316', // orange
  '#84cc16', // lime
  '#64748b', // slate
];

// =========================================================================
// DEFAULT CHART OPTIONS (ApexCharts)
// =========================================================================

export const DEFAULT_CHART_OPTIONS = {
  chart: {
    fontFamily: 'Inter, system-ui, sans-serif',
    toolbar: {
      show: true,
      tools: {
        download: true,
        selection: false,
        zoom: true,
        zoomin: true,
        zoomout: true,
        pan: false,
        reset: true,
      },
      export: {
        csv: {
          filename: 'report-data',
          headerCategory: 'Category',
          headerValue: 'Value',
        },
        svg: {
          filename: 'report-chart',
        },
        png: {
          filename: 'report-chart',
        },
      },
    },
    animations: {
      enabled: true,
      speed: 500,
      animateGradually: {
        enabled: true,
        delay: 150,
      },
    },
  },
  dataLabels: {
    enabled: false,
  },
  stroke: {
    curve: 'smooth' as const,
    width: 2,
  },
  grid: {
    borderColor: '#e2e8f0',
    strokeDashArray: 4,
    xaxis: {
      lines: {
        show: false,
      },
    },
  },
  tooltip: {
    theme: 'light',
    y: {
      formatter: (val: number) => val.toLocaleString(),
    },
  },
  legend: {
    position: 'bottom' as const,
    horizontalAlign: 'center' as const,
    fontSize: '13px',
    fontWeight: 500,
    markers: {
      size: 10,
      strokeWidth: 0,
    },
  },
  responsive: [
    {
      breakpoint: 768,
      options: {
        chart: {
          toolbar: {
            show: false,
          },
        },
        legend: {
          position: 'bottom',
        },
      },
    },
  ],
};

// =========================================================================
// SPECIFIC CHART TYPE OPTIONS
// =========================================================================

export const DONUT_CHART_OPTIONS = {
  ...DEFAULT_CHART_OPTIONS,
  chart: {
    ...DEFAULT_CHART_OPTIONS.chart,
    type: 'donut' as const,
  },
  plotOptions: {
    pie: {
      donut: {
        size: '70%',
        labels: {
          show: true,
          name: {
            show: true,
            fontSize: '14px',
            fontWeight: 600,
          },
          value: {
            show: true,
            fontSize: '20px',
            fontWeight: 700,
            formatter: (val: string) => parseInt(val).toLocaleString(),
          },
          total: {
            show: true,
            label: 'Total',
            fontSize: '14px',
            fontWeight: 600,
            formatter: (w: { globals: { seriesTotals: number[] } }) =>
              w.globals.seriesTotals.reduce((a: number, b: number) => a + b, 0).toLocaleString(),
          },
        },
      },
    },
  },
  dataLabels: {
    enabled: false,
  },
};

export const BAR_CHART_OPTIONS = {
  ...DEFAULT_CHART_OPTIONS,
  chart: {
    ...DEFAULT_CHART_OPTIONS.chart,
    type: 'bar' as const,
  },
  plotOptions: {
    bar: {
      horizontal: false,
      borderRadius: 4,
      columnWidth: '60%',
      dataLabels: {
        position: 'top',
      },
    },
  },
  xaxis: {
    labels: {
      style: {
        fontSize: '12px',
      },
    },
  },
  yaxis: {
    labels: {
      formatter: (val: number) => val.toLocaleString(),
    },
  },
};

export const HORIZONTAL_BAR_OPTIONS = {
  ...BAR_CHART_OPTIONS,
  plotOptions: {
    bar: {
      horizontal: true,
      borderRadius: 4,
      barHeight: '60%',
    },
  },
};

export const LINE_CHART_OPTIONS = {
  ...DEFAULT_CHART_OPTIONS,
  chart: {
    ...DEFAULT_CHART_OPTIONS.chart,
    type: 'line' as const,
  },
  stroke: {
    curve: 'smooth' as const,
    width: 3,
  },
  markers: {
    size: 4,
    strokeWidth: 0,
  },
  xaxis: {
    labels: {
      style: {
        fontSize: '12px',
      },
    },
  },
  yaxis: {
    labels: {
      formatter: (val: number) => val.toLocaleString(),
    },
  },
};

export const AREA_CHART_OPTIONS = {
  ...LINE_CHART_OPTIONS,
  chart: {
    ...DEFAULT_CHART_OPTIONS.chart,
    type: 'area' as const,
  },
  fill: {
    type: 'gradient',
    gradient: {
      shadeIntensity: 1,
      opacityFrom: 0.4,
      opacityTo: 0.1,
      stops: [0, 90, 100],
    },
  },
};

export const STACKED_BAR_OPTIONS = {
  ...BAR_CHART_OPTIONS,
  chart: {
    ...BAR_CHART_OPTIONS.chart,
    stacked: true,
  },
};

export const COMBO_CHART_OPTIONS = {
  ...DEFAULT_CHART_OPTIONS,
  chart: {
    ...DEFAULT_CHART_OPTIONS.chart,
    type: 'line' as const,
  },
  stroke: {
    width: [0, 3],
    curve: 'smooth' as const,
  },
};

// =========================================================================
// STATUS LABELS
// =========================================================================

export const APPROVAL_STATUS_LABELS: Record<string, string> = {
  pending: 'Pending',
  approved: 'Approved',
  rejected: 'Rejected',
  returned: 'Returned',
  cancelled: 'Cancelled',
};

export const POLICY_STATUS_LABELS: Record<string, string> = {
  draft: 'Draft',
  active: 'Active',
  suspended: 'Suspended',
  cancelled: 'Cancelled',
  expired: 'Expired',
  lapsed: 'Lapsed',
  renewed: 'Renewed',
  pending_payment: 'Pending Payment',
};

export const POLICY_TYPE_LABELS: Record<string, string> = {
  corporate: 'Corporate',
  individual: 'Individual',
  family: 'Family',
  sme: 'SME',
};

export const INVOICE_STATUS_LABELS: Record<string, string> = {
  draft: 'Draft',
  sent: 'Sent',
  partially_paid: 'Partially Paid',
  paid: 'Paid',
  overdue: 'Overdue',
  cancelled: 'Cancelled',
  written_off: 'Written Off',
};

export const PAYMENT_METHOD_LABELS: Record<string, string> = {
  bank_transfer: 'Bank Transfer',
  eft: 'EFT',
  cheque: 'Cheque',
  mobile_money: 'Mobile Money',
  cash: 'Cash',
  direct_debit: 'Direct Debit',
  card: 'Card',
};

export const ENTITY_TYPE_LABELS: Record<string, string> = {
  application: 'Application',
  claim: 'Claim',
  payment: 'Payment',
  invoice: 'Invoice',
  policy_activation: 'Policy Activation',
  scheme: 'Scheme',
  endorsement: 'Endorsement',
  refund: 'Refund',
};

export const AGING_BUCKET_LABELS: Record<string, string> = {
  current: 'Current',
  '1-30': '1-30 Days',
  '31-60': '31-60 Days',
  '61-90': '61-90 Days',
  '90+': '90+ Days',
};

// =========================================================================
// REPORT UI CONFIG
// =========================================================================

export const REPORT_UI_CONFIG = {
  PAGE_SIZE_OPTIONS: [10, 25, 50, 100],
  DEFAULT_PAGE_SIZE: 25,
  REFRESH_INTERVAL_MS: 300000, // 5 minutes
  EXPORT_FORMATS: ['csv', 'pdf'] as const,
  DEFAULT_DATE_RANGE_DAYS: 90,
  MAX_TOP_ITEMS: 10,
} as const;

// =========================================================================
// CURRENCY FORMATTER
// =========================================================================

export const formatCurrency = (value: number, currency = 'ZMW'): string => {
  return new Intl.NumberFormat('en-ZM', {
    style: 'currency',
    currency,
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value);
};

export const formatNumber = (value: number): string => {
  return new Intl.NumberFormat('en-ZM').format(value);
};

export const formatPercent = (value: number, decimals = 1): string => {
  return `${value.toFixed(decimals)}%`;
};

export const formatCompactNumber = (value: number): string => {
  if (value >= 1_000_000) {
    return `${(value / 1_000_000).toFixed(1)}M`;
  }
  if (value >= 1_000) {
    return `${(value / 1_000).toFixed(1)}K`;
  }
  return value.toLocaleString();
};
