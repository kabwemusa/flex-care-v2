// Report Models and Interfaces

// =========================================================================
// FILTER INTERFACES
// =========================================================================

export interface ReportFilters {
  from_date?: string;
  to_date?: string;
  scheme_id?: string;
  plan_id?: string;
  policy_type?: string;
  group_id?: string;
  entity_type?: string;
  workflow_id?: string;
}

export interface DatePreset {
  label: string;
  value: number | 'ytd' | 'mtd' | 'qtd' | 'custom';
}

export const DATE_PRESETS: DatePreset[] = [
  { label: 'Last 7 Days', value: 7 },
  { label: 'Last 30 Days', value: 30 },
  { label: 'Last 90 Days', value: 90 },
  { label: 'Year to Date', value: 'ytd' },
  { label: 'Custom', value: 'custom' },
];

// =========================================================================
// DASHBOARD SUMMARY
// =========================================================================

export interface DashboardSummary {
  approvals: {
    pending_count: number;
    approved_this_week: number;
    avg_processing_days: number;
    approval_rate: number;
  };
  policies: {
    total_policies: number;
    active_count: number;
    total_premium: number;
    total_members: number;
    for_renewal_count: number;
  };
  billing: {
    total_outstanding: number;
    collection_rate: number;
    overdue_amount: number;
    overdue_count: number;
    unallocated_payments: number;
  };
}

// =========================================================================
// APPROVAL METRICS
// =========================================================================

export interface ApprovalMetrics {
  summary: {
    pending: number;
    approved: number;
    rejected: number;
    returned: number;
    cancelled: number;
    avg_processing_days: number;
    approval_rate: number;
  };
  status_distribution: Array<{
    status: string;
    count: number;
  }>;
  trend: Array<{
    date: string;
    approved: number;
    rejected: number;
    returned: number;
  }>;
  by_entity_type: Array<{
    entity_type: string;
    count: number;
    avg_days: number;
  }>;
  approver_performance: Array<{
    user_id: string;
    user_name: string;
    action_count: number;
    approved_count: number;
    rejected_count: number;
    avg_response_days: number;
  }>;
  bottlenecks: Array<{
    workflow_name: string;
    step_name: string;
    avg_days_at_step: number;
    pending_count: number;
  }>;
}

// =========================================================================
// POLICY ANALYTICS
// =========================================================================

export interface PolicyAnalytics {
  summary: {
    total_policies: number;
    active_policies: number;
    total_premium: number;
    total_members: number;
    avg_premium: number;
  };
  status_distribution: Array<{
    status: string;
    count: number;
  }>;
  type_distribution: Array<{
    policy_type: string;
    count: number;
    premium: number;
  }>;
  premium_breakdown: Array<{
    policy_type: string;
    base_premium: number;
    addon_premium: number;
    loading_amount: number;
    discount_amount: number;
  }>;
  monthly_trend: Array<{
    month: string;
    new_policies: number;
    cancelled_policies: number;
    net_growth: number;
  }>;
  renewal_pipeline: {
    expiring_90_days: number;
    expiring_60_days: number;
    expiring_30_days: number;
    renewed: number;
  };
  top_plans: Array<{
    plan_id: string;
    plan_name: string;
    policy_count: number;
    member_count: number;
    total_premium: number;
  }>;
  member_distribution: Array<{
    policy_type: string;
    principal: number;
    spouse: number;
    child: number;
    parent: number;
  }>;
}

// =========================================================================
// BILLING REPORTS
// =========================================================================

export interface BillingReports {
  summary: {
    total_invoiced: number;
    total_collected: number;
    total_outstanding: number;
    collection_rate: number;
    overdue_amount: number;
    overdue_count: number;
    unallocated_payments: number;
  };
  invoice_status_distribution: Array<{
    status: string;
    count: number;
    amount: number;
  }>;
  collection_trend: Array<{
    month: string;
    invoiced: number;
    collected: number;
    collection_rate: number;
  }>;
  aging_analysis: Array<{
    bucket: string;
    count: number;
    amount: number;
  }>;
  payment_methods: Array<{
    method: string;
    count: number;
    amount: number;
    percentage: number;
  }>;
  top_outstanding: Array<{
    policy_id: string;
    policy_number: string;
    holder_name: string;
    outstanding: number;
    days_overdue: number;
  }>;
  daily_collections: Array<{
    date: string;
    amount: number;
    count: number;
  }>;
}

// =========================================================================
// EXPORT TYPES
// =========================================================================

export type ExportFormat = 'csv' | 'pdf';
export type ReportType = 'approvals' | 'policies' | 'billing';
