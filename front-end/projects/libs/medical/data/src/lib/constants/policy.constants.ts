// libs/medical/data/src/lib/constants/policy.constants.ts

/**
 * Policy-related constants for the medical module
 * Centralizes configuration values to avoid hardcoding throughout components
 */

// ============================================================================
// BUSINESS RULES
// ============================================================================

export const POLICY_BUSINESS_RULES = {
  /** Minimum coverage period in days when adding members mid-term */
  MIN_COVERAGE_PERIOD_DAYS: 30,

  /** Days before expiry to show in renewal alerts */
  RENEWAL_ALERT_DAYS: 30,

  /** Days before expiry to show as critical renewal */
  RENEWAL_CRITICAL_DAYS: 7,
} as const;

// ============================================================================
// UI CONFIGURATION
// ============================================================================

export const POLICY_UI_CONFIG = {
  /** Page size options for policy list table */
  PAGE_SIZE_OPTIONS: [10, 25, 50, 100],

  /** Default page size for policy list */
  DEFAULT_PAGE_SIZE: 25,

  /** Default page size for members table */
  MEMBERS_DEFAULT_PAGE_SIZE: 10,

  /** Member table page size options */
  MEMBERS_PAGE_SIZE_OPTIONS: [10, 25, 50],

  /** Detail drawer width in pixels */
  DRAWER_WIDTH: 520,

  /** Member detail drawer width in pixels */
  MEMBER_DRAWER_WIDTH: 480,
} as const;

// ============================================================================
// TITLE OPTIONS
// ============================================================================

export const TITLE_OPTIONS = [
  'Mr',
  'Mrs',
  'Miss',
  'Ms',
  'Dr',
  'Prof',
  'Rev',
] as const;

// ============================================================================
// MEMBER TYPE STYLING
// ============================================================================

export const MEMBER_TYPE_STYLES: Record<string, { bg: string; text: string; label: string }> = {
  principal: {
    bg: 'bg-blue-100',
    text: 'text-blue-700',
    label: 'Principal',
  },
  spouse: {
    bg: 'bg-purple-100',
    text: 'text-purple-700',
    label: 'Spouse',
  },
  child: {
    bg: 'bg-green-100',
    text: 'text-green-700',
    label: 'Child',
  },
  parent: {
    bg: 'bg-amber-100',
    text: 'text-amber-700',
    label: 'Parent',
  },
  dependent: {
    bg: 'bg-gray-100',
    text: 'text-gray-700',
    label: 'Dependent',
  },
} as const;

// ============================================================================
// POLICY STATUS STYLING
// ============================================================================

export const POLICY_STATUS_STYLES: Record<
  string,
  { bg: string; text: string; dot: string }
> = {
  active: {
    bg: 'bg-green-100',
    text: 'text-green-700',
    dot: 'bg-green-500',
  },
  draft: {
    bg: 'bg-gray-100',
    text: 'text-gray-700',
    dot: 'bg-gray-500',
  },
  pending_payment: {
    bg: 'bg-yellow-100',
    text: 'text-yellow-700',
    dot: 'bg-yellow-500',
  },
  suspended: {
    bg: 'bg-orange-100',
    text: 'text-orange-700',
    dot: 'bg-orange-500',
  },
  cancelled: {
    bg: 'bg-red-100',
    text: 'text-red-700',
    dot: 'bg-red-500',
  },
  expired: {
    bg: 'bg-gray-100',
    text: 'text-gray-600',
    dot: 'bg-gray-400',
  },
  renewed: {
    bg: 'bg-blue-100',
    text: 'text-blue-700',
    dot: 'bg-blue-500',
  },
} as const;

// ============================================================================
// MEMBER STATUS STYLING
// ============================================================================

export const MEMBER_STATUS_STYLES: Record<
  string,
  { bg: string; text: string; dot: string }
> = {
  active: {
    bg: 'bg-green-100',
    text: 'text-green-700',
    dot: 'bg-green-500',
  },
  suspended: {
    bg: 'bg-orange-100',
    text: 'text-orange-700',
    dot: 'bg-orange-500',
  },
  terminated: {
    bg: 'bg-red-100',
    text: 'text-red-700',
    dot: 'bg-red-500',
  },
  deceased: {
    bg: 'bg-gray-100',
    text: 'text-gray-600',
    dot: 'bg-gray-400',
  },
} as const;

// ============================================================================
// DEFAULT REASONS
// ============================================================================

export const DEFAULT_SUSPENSION_REASONS = [
  'Administrative suspension',
  'Non-payment',
  'Pending investigation',
  'At client request',
  'Policy review',
] as const;

export const DEFAULT_CANCELLATION_REASONS = [
  'Client request',
  'Non-payment',
  'Duplicate policy',
  'Replaced by renewal',
  'Coverage no longer needed',
  'Moved to different insurer',
] as const;

export const DEFAULT_MEMBER_TERMINATION_REASONS = [
  'Resignation',
  'End of contract',
  'Voluntary withdrawal',
  'Age limit exceeded',
  'No longer eligible',
  'Deceased',
] as const;

// ============================================================================
// CSV EXPORT CONFIGURATION
// ============================================================================

export const POLICY_CSV_CONFIG = {
  headers: [
    'Policy #',
    'Holder',
    'Scheme',
    'Plan',
    'Status',
    'Type',
    'Inception Date',
    'Expiry Date',
    'Premium',
    'Billing Frequency',
    'Members Count',
  ],
  filename: (date: string) => `policies-export-${date}.csv`,
} as const;

export const MEMBER_CSV_CONFIG = {
  headers: [
    'Member #',
    'Full Name',
    'Type',
    'DOB',
    'Age',
    'Gender',
    'Status',
    'Cover Start',
    'Cover End',
    'Premium',
    'Loadings',
    'Exclusions',
  ],
  filename: (policyNumber: string, date: string) =>
    `policy-${policyNumber}-members-${date}.csv`,
} as const;

// ============================================================================
// FILTER OPTIONS
// ============================================================================

export const MEMBER_FILTER_OPTIONS = {
  types: [
    { value: 'all', label: 'All Types' },
    { value: 'principal', label: 'Principal' },
    { value: 'spouse', label: 'Spouse' },
    { value: 'child', label: 'Child' },
    { value: 'parent', label: 'Parent' },
    { value: 'dependent', label: 'Other Dependents' },
  ],
  statuses: [
    { value: 'all', label: 'All Statuses' },
    { value: 'active', label: 'Active' },
    { value: 'suspended', label: 'Suspended' },
    { value: 'terminated', label: 'Terminated' },
    { value: 'deceased', label: 'Deceased' },
  ],
} as const;

// ============================================================================
// PREMIUM INDICATORS
// ============================================================================

export const PREMIUM_INDICATORS = {
  prorated: {
    icon: 'schedule',
    tooltip: 'Pro-rated premium (mid-term addition)',
    color: 'text-blue-600',
  },
  loaded: {
    icon: 'add_circle',
    tooltip: 'Premium includes medical loadings',
    color: 'text-orange-600',
  },
  discounted: {
    icon: 'local_offer',
    tooltip: 'Discounted premium applied',
    color: 'text-green-600',
  },
} as const;

// ============================================================================
// MEMBER TABLE COLUMNS
// ============================================================================

export const MEMBER_TABLE_COLUMNS = {
  /** Columns displayed in the members list table */
  list: ['name', 'type', 'age', 'gender', 'status', 'premium', 'actions'],

  /** Columns displayed in the policy detail members tab */
  detail: ['name', 'type', 'age', 'gender', 'status', 'premium', 'actions'],
} as const;

// ============================================================================
// MEMBER DETAIL DRAWER
// ============================================================================

export const MEMBER_DETAIL_SECTIONS = {
  personal: {
    title: 'Personal Information',
    icon: 'person',
    fields: ['full_name', 'date_of_birth', 'gender', 'national_id', 'email', 'phone'],
  },
  coverage: {
    title: 'Coverage Details',
    icon: 'verified_user',
    fields: ['cover_start_date', 'cover_end_date', 'waiting_period_end_date', 'card_number'],
  },
  premium: {
    title: 'Premium Information',
    icon: 'payments',
    fields: ['premium', 'loading_amount', 'total_premium'],
  },
  loadings: {
    title: 'Medical Loadings',
    icon: 'trending_up',
    emptyText: 'No medical loadings applied',
  },
  exclusions: {
    title: 'Exclusions',
    icon: 'block',
    emptyText: 'No exclusions applied',
  },
} as const;

// ============================================================================
// LOADING TYPE STYLING
// ============================================================================

export const LOADING_TYPE_STYLES: Record<string, { bg: string; text: string; label: string }> = {
  percentage: {
    bg: 'bg-amber-100',
    text: 'text-amber-700',
    label: 'Percentage',
  },
  fixed: {
    bg: 'bg-orange-100',
    text: 'text-orange-700',
    label: 'Fixed Amount',
  },
} as const;

// ============================================================================
// EXCLUSION TYPE STYLING
// ============================================================================

export const EXCLUSION_TYPE_STYLES: Record<string, { bg: string; text: string; label: string }> = {
  pre_existing: {
    bg: 'bg-red-100',
    text: 'text-red-700',
    label: 'Pre-existing',
  },
  specific: {
    bg: 'bg-orange-100',
    text: 'text-orange-700',
    label: 'Specific Condition',
  },
  benefit: {
    bg: 'bg-purple-100',
    text: 'text-purple-700',
    label: 'Benefit Exclusion',
  },
  waiting_period: {
    bg: 'bg-blue-100',
    text: 'text-blue-700',
    label: 'Waiting Period',
  },
} as const;

// ============================================================================
// DURATION TYPE STYLING
// ============================================================================

export const DURATION_TYPE_STYLES: Record<string, { bg: string; text: string; label: string }> = {
  permanent: {
    bg: 'bg-red-100',
    text: 'text-red-700',
    label: 'Permanent',
  },
  time_limited: {
    bg: 'bg-amber-100',
    text: 'text-amber-700',
    label: 'Time Limited',
  },
  reviewable: {
    bg: 'bg-blue-100',
    text: 'text-blue-700',
    label: 'Reviewable',
  },
} as const;

// ============================================================================
// ENDORSEMENT TYPE STYLING
// ============================================================================

export const ENDORSEMENT_TYPE_STYLES: Record<
  string,
  { bg: string; text: string; icon: string; label: string }
> = {
  add_member: {
    bg: 'bg-green-100',
    text: 'text-green-700',
    icon: 'person_add',
    label: 'Add Member',
  },
  remove_member: {
    bg: 'bg-red-100',
    text: 'text-red-700',
    icon: 'person_remove',
    label: 'Remove Member',
  },
  upgrade_plan: {
    bg: 'bg-blue-100',
    text: 'text-blue-700',
    icon: 'upgrade',
    label: 'Upgrade Plan',
  },
  downgrade_plan: {
    bg: 'bg-orange-100',
    text: 'text-orange-700',
    icon: 'download',
    label: 'Downgrade Plan',
  },
  add_addon: {
    bg: 'bg-purple-100',
    text: 'text-purple-700',
    icon: 'add_circle',
    label: 'Add Add-on',
  },
  remove_addon: {
    bg: 'bg-amber-100',
    text: 'text-amber-700',
    icon: 'remove_circle',
    label: 'Remove Add-on',
  },
  change_details: {
    bg: 'bg-slate-100',
    text: 'text-slate-700',
    icon: 'edit',
    label: 'Change Details',
  },
  correction: {
    bg: 'bg-cyan-100',
    text: 'text-cyan-700',
    icon: 'edit_note',
    label: 'Correction',
  },
  cancellation: {
    bg: 'bg-red-100',
    text: 'text-red-700',
    icon: 'cancel',
    label: 'Cancellation',
  },
  reinstatement: {
    bg: 'bg-green-100',
    text: 'text-green-700',
    icon: 'restart_alt',
    label: 'Reinstatement',
  },
} as const;

// ============================================================================
// ENDORSEMENT STATUS STYLING
// ============================================================================

export const ENDORSEMENT_STATUS_STYLES: Record<
  string,
  { bg: string; text: string; dot: string; label: string }
> = {
  pending: {
    bg: 'bg-yellow-100',
    text: 'text-yellow-700',
    dot: 'bg-yellow-500',
    label: 'Pending',
  },
  approved: {
    bg: 'bg-blue-100',
    text: 'text-blue-700',
    dot: 'bg-blue-500',
    label: 'Approved',
  },
  rejected: {
    bg: 'bg-red-100',
    text: 'text-red-700',
    dot: 'bg-red-500',
    label: 'Rejected',
  },
  processed: {
    bg: 'bg-green-100',
    text: 'text-green-700',
    dot: 'bg-green-500',
    label: 'Processed',
  },
  cancelled: {
    bg: 'bg-slate-100',
    text: 'text-slate-600',
    dot: 'bg-slate-400',
    label: 'Cancelled',
  },
} as const;

// ============================================================================
// ENDORSEMENT FILTER OPTIONS
// ============================================================================

export const ENDORSEMENT_FILTER_OPTIONS = {
  types: [
    { value: 'all', label: 'All Types' },
    { value: 'add_member', label: 'Add Member' },
    { value: 'remove_member', label: 'Remove Member' },
    { value: 'upgrade_plan', label: 'Upgrade Plan' },
    { value: 'downgrade_plan', label: 'Downgrade Plan' },
    { value: 'add_addon', label: 'Add Add-on' },
    { value: 'remove_addon', label: 'Remove Add-on' },
    { value: 'change_details', label: 'Change Details' },
    { value: 'correction', label: 'Correction' },
    { value: 'cancellation', label: 'Cancellation' },
    { value: 'reinstatement', label: 'Reinstatement' },
  ],
  statuses: [
    { value: 'all', label: 'All Statuses' },
    { value: 'pending', label: 'Pending' },
    { value: 'approved', label: 'Approved' },
    { value: 'processed', label: 'Processed' },
    { value: 'rejected', label: 'Rejected' },
    { value: 'cancelled', label: 'Cancelled' },
  ],
} as const;

// ============================================================================
// ENDORSEMENT UI CONFIG
// ============================================================================

export const ENDORSEMENT_UI_CONFIG = {
  /** Page size options for endorsement table */
  PAGE_SIZE_OPTIONS: [10, 25, 50],

  /** Default page size */
  DEFAULT_PAGE_SIZE: 10,

  /** Table columns */
  TABLE_COLUMNS: ['number', 'type', 'effective_date', 'premium_adjustment', 'status', 'actions'],
} as const;

// ============================================================================
// BILLING - INVOICE CONSTANTS
// ============================================================================

export const INVOICE_TYPES = [
  { value: 'premium', label: 'Premium Invoice' },
  { value: 'endorsement', label: 'Endorsement Invoice' },
  { value: 'debit_note', label: 'Debit Note' },
  { value: 'credit_note', label: 'Credit Note' },
] as const;

export const INVOICE_STATUSES = [
  { value: 'draft', label: 'Draft' },
  { value: 'sent', label: 'Sent' },
  { value: 'partially_paid', label: 'Partially Paid' },
  { value: 'paid', label: 'Paid' },
  { value: 'overdue', label: 'Overdue' },
  { value: 'cancelled', label: 'Cancelled' },
  { value: 'void', label: 'Void' },
] as const;

export const INVOICE_STATUS_STYLES: Record<
  string,
  { bg: string; text: string; dot: string; label: string }
> = {
  draft: {
    bg: 'bg-gray-100',
    text: 'text-gray-700',
    dot: 'bg-gray-500',
    label: 'Draft',
  },
  sent: {
    bg: 'bg-blue-100',
    text: 'text-blue-700',
    dot: 'bg-blue-500',
    label: 'Sent',
  },
  partially_paid: {
    bg: 'bg-amber-100',
    text: 'text-amber-700',
    dot: 'bg-amber-500',
    label: 'Partially Paid',
  },
  paid: {
    bg: 'bg-green-100',
    text: 'text-green-700',
    dot: 'bg-green-500',
    label: 'Paid',
  },
  overdue: {
    bg: 'bg-red-100',
    text: 'text-red-700',
    dot: 'bg-red-500',
    label: 'Overdue',
  },
  cancelled: {
    bg: 'bg-slate-100',
    text: 'text-slate-600',
    dot: 'bg-slate-400',
    label: 'Cancelled',
  },
  void: {
    bg: 'bg-slate-100',
    text: 'text-slate-500',
    dot: 'bg-slate-400',
    label: 'Void',
  },
} as const;

export const INVOICE_ITEM_TYPES = [
  { value: 'member_premium', label: 'Member Premium' },
  { value: 'addon_premium', label: 'Add-on Premium' },
  { value: 'loading', label: 'Medical Loading' },
  { value: 'discount', label: 'Discount' },
  { value: 'tax', label: 'Tax' },
  { value: 'adjustment', label: 'Adjustment' },
  { value: 'late_fee', label: 'Late Fee' },
] as const;

// ============================================================================
// BILLING - PAYMENT CONSTANTS
// ============================================================================

export const PAYMENT_STATUSES = [
  { value: 'received', label: 'Received' },
  { value: 'allocated', label: 'Allocated' },
  { value: 'partially_allocated', label: 'Partially Allocated' },
  { value: 'reversed', label: 'Reversed' },
  { value: 'returned', label: 'Returned' },
] as const;

export const PAYMENT_STATUS_STYLES: Record<
  string,
  { bg: string; text: string; dot: string; label: string }
> = {
  received: {
    bg: 'bg-blue-100',
    text: 'text-blue-700',
    dot: 'bg-blue-500',
    label: 'Received',
  },
  allocated: {
    bg: 'bg-green-100',
    text: 'text-green-700',
    dot: 'bg-green-500',
    label: 'Allocated',
  },
  partially_allocated: {
    bg: 'bg-amber-100',
    text: 'text-amber-700',
    dot: 'bg-amber-500',
    label: 'Partially Allocated',
  },
  reversed: {
    bg: 'bg-red-100',
    text: 'text-red-700',
    dot: 'bg-red-500',
    label: 'Reversed',
  },
  returned: {
    bg: 'bg-orange-100',
    text: 'text-orange-700',
    dot: 'bg-orange-500',
    label: 'Returned',
  },
} as const;

export const BILLING_PAYMENT_METHODS = [
  { value: 'bank_transfer', label: 'Bank Transfer' },
  { value: 'cheque', label: 'Cheque' },
  { value: 'cash', label: 'Cash' },
  { value: 'mobile_money', label: 'Mobile Money' },
  { value: 'card', label: 'Card Payment' },
  { value: 'direct_debit', label: 'Direct Debit' },
  { value: 'offset', label: 'Offset/Adjustment' },
] as const;

// ============================================================================
// BILLING UI CONFIGURATION
// ============================================================================

export const BILLING_UI_CONFIG = {
  /** Page size options for billing tables */
  PAGE_SIZE_OPTIONS: [10, 25, 50, 100],

  /** Default page size for invoice list */
  DEFAULT_PAGE_SIZE: 25,

  /** Invoice table columns */
  INVOICE_TABLE_COLUMNS: [
    'status',
    'invoice_number',
    'policy',
    'type',
    'period',
    'amount',
    'balance',
    'due_date',
    'actions',
  ],

  /** Payment table columns */
  PAYMENT_TABLE_COLUMNS: [
    'status',
    'payment_number',
    'policy',
    'method',
    'amount',
    'allocated',
    'unallocated',
    'date',
    'actions',
  ],

  /** Days before due date to show warning */
  DUE_SOON_DAYS: 7,

  /** Grace period after due date (days) */
  GRACE_PERIOD_DAYS: 30,

  /** Days overdue before suspension warning */
  SUSPENSION_WARNING_DAYS: 60,

  /** Days overdue before lapse warning */
  LAPSE_WARNING_DAYS: 90,
} as const;

// ============================================================================
// BILLING FILTER OPTIONS
// ============================================================================

export const INVOICE_FILTER_OPTIONS = {
  statuses: [
    { value: '', label: 'All Statuses' },
    { value: 'draft', label: 'Draft' },
    { value: 'sent', label: 'Sent' },
    { value: 'partially_paid', label: 'Partially Paid' },
    { value: 'paid', label: 'Paid' },
    { value: 'overdue', label: 'Overdue' },
    { value: 'cancelled', label: 'Cancelled' },
  ],
  types: [
    { value: '', label: 'All Types' },
    { value: 'premium', label: 'Premium' },
    { value: 'endorsement', label: 'Endorsement' },
    { value: 'debit_note', label: 'Debit Note' },
    { value: 'credit_note', label: 'Credit Note' },
  ],
} as const;

export const PAYMENT_FILTER_OPTIONS = {
  statuses: [
    { value: '', label: 'All Statuses' },
    { value: 'received', label: 'Received' },
    { value: 'allocated', label: 'Allocated' },
    { value: 'partially_allocated', label: 'Partially Allocated' },
    { value: 'reversed', label: 'Reversed' },
  ],
  methods: [
    { value: '', label: 'All Methods' },
    { value: 'bank_transfer', label: 'Bank Transfer' },
    { value: 'cheque', label: 'Cheque' },
    { value: 'cash', label: 'Cash' },
    { value: 'mobile_money', label: 'Mobile Money' },
    { value: 'card', label: 'Card Payment' },
    { value: 'direct_debit', label: 'Direct Debit' },
  ],
} as const;

// ============================================================================
// BILLING CSV EXPORT CONFIGURATION
// ============================================================================

export const INVOICE_CSV_CONFIG = {
  headers: [
    'Invoice #',
    'Policy #',
    'Holder',
    'Type',
    'Status',
    'Issue Date',
    'Due Date',
    'Period Start',
    'Period End',
    'Subtotal',
    'Tax',
    'Total',
    'Paid',
    'Balance',
  ],
  filename: (date: string) => `invoices-export-${date}.csv`,
} as const;

export const PAYMENT_CSV_CONFIG = {
  headers: [
    'Payment #',
    'Policy #',
    'Holder',
    'Method',
    'Status',
    'Amount',
    'Allocated',
    'Unallocated',
    'Payment Date',
    'Reference',
    'Received By',
  ],
  filename: (date: string) => `payments-export-${date}.csv`,
} as const;

// ============================================================================
// BILLING BUSINESS RULES
// ============================================================================

export const BILLING_BUSINESS_RULES = {
  /** Minimum payment amount */
  MIN_PAYMENT_AMOUNT: 1,

  /** Auto-allocate payments below this threshold to single invoice */
  AUTO_ALLOCATE_THRESHOLD: 0.01,

  /** Number of days after due date to mark as overdue */
  OVERDUE_THRESHOLD_DAYS: 1,

  /** Payment receipt validity (days) */
  RECEIPT_VALIDITY_DAYS: 365,
} as const;
