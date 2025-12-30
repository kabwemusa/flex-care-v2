// libs/medical/data/src/lib/constants/medical.constants.ts

export const MARKET_SEGMENTS = [
  { value: 'corporate', label: 'Corporate', icon: 'business' },
  { value: 'sme', label: 'SME', icon: 'store' },
  { value: 'individual', label: 'Individual', icon: 'person' },
  { value: 'family', label: 'Family', icon: 'family_restroom' },
  { value: 'senior', label: 'Senior', icon: 'elderly' },
] as const;

export const VALUE_TYPES = [
  { value: 'percentage', label: 'Percentage', suffix: '%' },
  { value: 'fixed', label: 'Fixed Amount', suffix: 'ZMW' },
];

export const APPLIES_TO = [
  { value: 'base_premium', label: 'Base Premium Only' },
  { value: 'total_premium', label: 'Total Premium' },
  { value: 'addon', label: 'Addon Only' },
];

export const PLAN_TYPES = [
  // { value: 'comprehensive', label: 'Comprehensive', description: 'Full medical coverage' },
  // { value: 'hospital_only', label: 'Hospital Only', description: 'In-patient coverage only' },
  // { value: 'outpatient_only', label: 'Outpatient Only', description: 'Out-patient coverage only' },
  // { value: 'supplementary', label: 'Supplementary', description: 'Top-up coverage' },
  // { value: 'maternity', label: 'Maternity', description: 'Pregnancy and childbirth' },
  // { value: 'dental', label: 'Dental', description: 'Dental care only' },
  // { value: 'optical', label: 'Optical', description: 'Eye care only' },
  { value: 'individual', label: 'Individual', description: 'Individual' },
  { value: 'family', label: 'Family', description: 'Family' },
  { value: 'group', label: 'Group/Corporate', description: 'Group/Corporate' },
] as const;

export const NETWORK_TYPES = [
  { value: 'open', label: 'Open Network', description: 'Any provider' },
  { value: 'closed', label: 'Closed Network', description: 'Panel providers only' },
  { value: 'tiered', label: 'Tiered Network', description: 'Different copays by tier' },
  { value: 'hybrid', label: 'Hybrid', description: 'Mix of open and closed' },
] as const;

// export const MEMBER_TYPES = [
//   { value: 'principal', label: 'Principal Member', factor: 1.0 },
//   { value: 'spouse', label: 'Spouse', factor: 1.0 },
//   { value: 'child', label: 'Child', factor: 0.5 },
//   { value: 'parent', label: 'Parent', factor: 1.5 },
// ] as const;

export const BENEFIT_TYPES = [
  { value: 'in_patient', label: 'In-Patient', icon: 'local_hospital' },
  { value: 'out_patient', label: 'Out-Patient', icon: 'medical_services' },
  { value: 'dental', label: 'Dental', icon: 'dentistry' },
  { value: 'optical', label: 'Optical', icon: 'visibility' },
  { value: 'maternity', label: 'Maternity', icon: 'pregnant_woman' },
  { value: 'chronic', label: 'Chronic', icon: 'medication' },
  { value: 'wellness', label: 'Wellness', icon: 'spa' },
  { value: 'emergency', label: 'Emergency', icon: 'emergency' },
] as const;

export const LIMIT_TYPES = [
  { value: 'unlimited', label: 'Unlimited', description: 'No limit applies' },
  { value: 'monetary', label: 'Monetary Limit', description: 'Fixed amount limit' },
  { value: 'count', label: 'Visit/Count Limit', description: 'Number of visits/uses' },
  { value: 'days', label: 'Days Limit', description: 'Number of days covered' },
  { value: 'combined', label: 'Combined Limit', description: 'Multiple limit types' },
] as const;

export const LIMIT_FREQUENCIES = [
  { value: 'per_annum', label: 'Per Year' },
  { value: 'per_claim', label: 'Per Claim' },
  { value: 'per_visit', label: 'Per Visit' },
  { value: 'per_condition', label: 'Per Condition' },
  { value: 'lifetime', label: 'Lifetime' },
] as const;

export const LIMIT_BASES = [
  { value: 'per_member', label: 'Per Member' },
  { value: 'per_family', label: 'Per Family' },
  { value: 'shared_pool', label: 'Shared Pool' },
] as const;

export const PREMIUM_FREQUENCIES = [
  { value: 'monthly', label: 'Monthly', factor: 1 },
  { value: 'quarterly', label: 'Quarterly', factor: 3 },
  { value: 'semi_annual', label: 'Semi-Annual', factor: 6 },
  { value: 'annual', label: 'Annual', factor: 12 },
] as const;

export const PREMIUM_BASES = [
  { value: 'per_member', label: 'Per Member', description: 'Each member pays separately' },
  { value: 'tiered', label: 'Tiered/Family', description: 'Flat rate by family size' },
] as const;

export const ADDON_TYPES = [
  { value: 'optional', label: 'Optional', description: 'Additional coverage' },
  { value: 'mandatory', label: 'Mandatory', description: 'Increase existing limits' },
  { value: 'conditional', label: 'Conditional', description: 'Independent benefit' },
] as const;

export const ADDON_AVAILABILITY = [
  { value: 'optional', label: 'Optional', description: 'Member can choose' },
  { value: 'mandatory', label: 'Mandatory', description: 'Required with plan' },
  { value: 'included', label: 'Included', description: 'Part of plan, no extra cost' },
  { value: 'conditional', label: 'Conditional', description: 'Based on criteria' },
] as const;

export const ADDON_PRICING_TYPES = [
  { value: 'fixed', label: 'Fixed Amount' },
  { value: 'per_member', label: 'Per Member' },
  { value: 'percentage', label: 'Percentage of Premium' },
  { value: 'age_rated', label: 'Age Rated' },
] as const;

export const DISCOUNT_TYPES = [
  { value: 'discount', label: 'Discount', icon: 'discount' },
  { value: 'loading', label: 'Loading', icon: 'trending_up' },
] as const;

export const DISCOUNT_APPLICATION = [
  { value: 'automatic', label: 'Automatic', description: 'Applied when rules match' },
  { value: 'manual', label: 'Manual', description: 'Applied by underwriter' },
  { value: 'promo_code', label: 'Promo Code', description: 'Requires code entry' },
] as const;

// export const LOADING_TYPES = [
//   { value: 'percentage', label: 'Percentage' },
//   { value: 'fixed', label: 'Fixed Amount' },
//   { value: 'exclusion', label: 'Exclusion Only' },
// ] as const;

// export const DURATION_TYPES = [
//   { value: 'permanent', label: 'Permanent' },
//   { value: 'time_limited', label: 'Time Limited' },
//   { value: 'reviewable', label: 'Subject to Review' },
// ] as const;

export const CONDITION_CATEGORIES = [
  { value: 'cardiovascular', label: 'Cardiovascular' },
  { value: 'respiratory', label: 'Respiratory' },
  { value: 'diabetes', label: 'Diabetes & Metabolic' },
  { value: 'musculoskeletal', label: 'Musculoskeletal' },
  { value: 'mental_health', label: 'Mental Health' },
  { value: 'oncology', label: 'Oncology' },
  { value: 'renal', label: 'Renal' },
  { value: 'other', label: 'Other' },
] as const;

// export const EXCLUSION_TYPES = [
//   { value: 'absolute', label: 'Absolute', description: 'Never covered' },
//   { value: 'time_limited', label: 'Time Limited', description: 'Excluded for period' },
//   { value: 'conditional', label: 'Conditional', description: 'Excluded if conditions met' },
// ] as const;

export const WAITING_PERIOD_TYPES = [
  { value: 'general', label: 'General', defaultDays: 30 },
  { value: 'maternity', label: 'Maternity', defaultDays: 270 },
  { value: 'pre_existing', label: 'Pre-existing Conditions', defaultDays: 365 },
  { value: 'chronic', label: 'Chronic Conditions', defaultDays: 180 },
  { value: 'dental', label: 'Dental', defaultDays: 90 },
  { value: 'optical', label: 'Optical', defaultDays: 90 },
] as const;

export const COMPANY_SIZES = [
  { value: 'micro', label: 'Micro (1-10 employees)', minEmployees: 1, maxEmployees: 10 },
  { value: 'sme', label: 'SME (11-50 employees)', minEmployees: 11, maxEmployees: 50 },
  { value: 'medium', label: 'Medium (51-250 employees)', minEmployees: 51, maxEmployees: 250 },
  { value: 'large', label: 'Large (251-1000 employees)', minEmployees: 251, maxEmployees: 1000 },
  {
    value: 'enterprise',
    label: 'Enterprise (1000+ employees)',
    minEmployees: 1001,
    maxEmployees: null,
  },
] as const;

export const INDUSTRIES = [
  { value: 'agriculture', label: 'Agriculture & Farming' },
  { value: 'banking', label: 'Banking & Finance' },
  { value: 'construction', label: 'Construction' },
  { value: 'education', label: 'Education' },
  { value: 'energy', label: 'Energy & Utilities' },
  { value: 'government', label: 'Government' },
  { value: 'healthcare', label: 'Healthcare' },
  { value: 'hospitality', label: 'Hospitality & Tourism' },
  { value: 'it', label: 'Information Technology' },
  { value: 'legal', label: 'Legal Services' },
  { value: 'manufacturing', label: 'Manufacturing' },
  { value: 'mining', label: 'Mining' },
  { value: 'ngo', label: 'NGO / Non-Profit' },
  { value: 'real_estate', label: 'Real Estate' },
  { value: 'retail', label: 'Retail & Wholesale' },
  { value: 'telecom', label: 'Telecommunications' },
  { value: 'transport', label: 'Transport & Logistics' },
  { value: 'other', label: 'Other' },
] as const;

export const GROUP_STATUSES = [
  { value: 'prospect', label: 'Prospect', color: 'text-gray-500', bgColor: 'bg-gray-100' },
  { value: 'active', label: 'Active', color: 'text-green-600', bgColor: 'bg-green-100' },
  { value: 'suspended', label: 'Suspended', color: 'text-amber-600', bgColor: 'bg-amber-100' },
  { value: 'terminated', label: 'Terminated', color: 'text-red-600', bgColor: 'bg-red-100' },
] as const;

export const PAYMENT_TERMS = [
  { value: 'immediate', label: 'Immediate', days: 0 },
  { value: '15_days', label: 'Net 15 Days', days: 15 },
  { value: '30_days', label: 'Net 30 Days', days: 30 },
  { value: '60_days', label: 'Net 60 Days', days: 60 },
] as const;

export const PAYMENT_METHODS = [
  { value: 'bank_transfer', label: 'Bank Transfer' },
  { value: 'direct_debit', label: 'Direct Debit' },
  { value: 'cheque', label: 'Cheque' },
  { value: 'mobile_money', label: 'Mobile Money' },
  { value: 'cash', label: 'Cash' },
] as const;

export const CONTACT_TYPES = [
  { value: 'primary', label: 'Primary Contact' },
  { value: 'hr', label: 'HR Contact' },
  { value: 'finance', label: 'Finance/Billing' },
  { value: 'broker', label: 'Broker/Agent' },
  { value: 'administrator', label: 'Scheme Administrator' },
  { value: 'emergency', label: 'Emergency Contact' },
] as const;

// =============================================================================
// POLICIES
// =============================================================================

export const POLICY_TYPES = [
  {
    value: 'individual',
    label: 'Individual',
    icon: 'person',
    description: 'Single person coverage',
  },
  {
    value: 'family',
    label: 'Family',
    icon: 'family_restroom',
    description: 'Family coverage with dependents',
  },
  {
    value: 'corporate',
    label: 'Corporate',
    icon: 'business',
    description: 'Group coverage for organizations',
  },
  { value: 'sme', label: 'SME', icon: 'store', description: 'Small & medium enterprise' },
] as const;

export const POLICY_STATUSES = [
  {
    value: 'draft',
    label: 'Draft',
    color: 'text-gray-500',
    bgColor: 'bg-gray-100',
    icon: 'edit_note',
  },
  {
    value: 'pending_payment',
    label: 'Pending Payment',
    color: 'text-amber-600',
    bgColor: 'bg-amber-100',
    icon: 'hourglass_empty',
  },
  {
    value: 'active',
    label: 'Active',
    color: 'text-green-600',
    bgColor: 'bg-green-100',
    icon: 'check_circle',
  },
  {
    value: 'suspended',
    label: 'Suspended',
    color: 'text-orange-600',
    bgColor: 'bg-orange-100',
    icon: 'pause_circle',
  },
  {
    value: 'cancelled',
    label: 'Cancelled',
    color: 'text-red-600',
    bgColor: 'bg-red-100',
    icon: 'cancel',
  },
  {
    value: 'expired',
    label: 'Expired',
    color: 'text-slate-500',
    bgColor: 'bg-slate-100',
    icon: 'event_busy',
  },
  {
    value: 'renewed',
    label: 'Renewed',
    color: 'text-blue-600',
    bgColor: 'bg-blue-100',
    icon: 'autorenew',
  },
] as const;

export const POLICY_TERMS = [
  { value: 12, label: '12 Months (Annual)' },
  { value: 6, label: '6 Months (Semi-Annual)' },
  { value: 3, label: '3 Months (Quarterly)' },
  { value: 1, label: '1 Month (Monthly)' },
] as const;

export const BILLING_FREQUENCIES = [
  { value: 'monthly', label: 'Monthly', months: 1 },
  { value: 'quarterly', label: 'Quarterly', months: 3 },
  { value: 'semi_annual', label: 'Semi-Annual', months: 6 },
  { value: 'annual', label: 'Annual', months: 12 },
] as const;

export const CANCELLATION_REASONS = [
  { value: 'non_payment', label: 'Non-Payment of Premium' },
  { value: 'customer_request', label: 'Customer Request' },
  { value: 'fraud', label: 'Fraudulent Activity' },
  { value: 'death', label: 'Death of Policyholder' },
  { value: 'replaced', label: 'Replaced by New Policy' },
  { value: 'group_exit', label: 'Exit from Corporate Group' },
  { value: 'other', label: 'Other' },
] as const;

// =============================================================================
// MEMBERS
// =============================================================================

export const MEMBER_TYPES = [
  {
    value: 'principal',
    label: 'Principal Member',
    icon: 'person',
    description: 'Main insured person',
  },
  { value: 'spouse', label: 'Spouse', icon: 'favorite', description: 'Spouse/Partner' },
  { value: 'child', label: 'Child', icon: 'child_care', description: 'Dependent child' },
  { value: 'parent', label: 'Parent', icon: 'elderly', description: 'Parent/In-law' },
] as const;

export const MEMBER_STATUSES = [
  { value: 'pending', label: 'Pending', color: 'text-amber-600', bgColor: 'bg-amber-100' },
  { value: 'active', label: 'Active', color: 'text-green-600', bgColor: 'bg-green-100' },
  { value: 'suspended', label: 'Suspended', color: 'text-orange-600', bgColor: 'bg-orange-100' },
  { value: 'terminated', label: 'Terminated', color: 'text-red-600', bgColor: 'bg-red-100' },
  { value: 'deceased', label: 'Deceased', color: 'text-slate-500', bgColor: 'bg-slate-100' },
] as const;

export const CARD_STATUSES = [
  { value: 'pending', label: 'Pending Issue', color: 'text-gray-500' },
  { value: 'issued', label: 'Issued', color: 'text-blue-600' },
  { value: 'active', label: 'Active', color: 'text-green-600' },
  { value: 'blocked', label: 'Blocked', color: 'text-red-600' },
  { value: 'expired', label: 'Expired', color: 'text-slate-500' },
] as const;

export const GENDERS = [
  { value: 'male', label: 'Male' },
  { value: 'female', label: 'Female' },
] as const;

export const MARITAL_STATUSES = [
  { value: 'single', label: 'Single' },
  { value: 'married', label: 'Married' },
  { value: 'divorced', label: 'Divorced' },
  { value: 'widowed', label: 'Widowed' },
] as const;

export const RELATIONSHIPS = [
  { value: 'self', label: 'Self (Principal)' },
  { value: 'spouse', label: 'Spouse' },
  { value: 'child', label: 'Child' },
  { value: 'adopted_child', label: 'Adopted Child' },
  { value: 'step_child', label: 'Step Child' },
  { value: 'parent', label: 'Parent' },
  { value: 'parent_in_law', label: 'Parent-in-Law' },
] as const;

export const ID_TYPES = [
  { value: 'nrc', label: 'NRC (National Registration Card)' },
  { value: 'passport', label: 'Passport' },
  { value: 'driving_license', label: 'Driving License' },
  { value: 'birth_certificate', label: 'Birth Certificate' },
] as const;

export const TERMINATION_REASONS = [
  { value: 'voluntary', label: 'Voluntary Exit' },
  { value: 'employment_ended', label: 'Employment Ended' },
  { value: 'policy_cancelled', label: 'Policy Cancelled' },
  { value: 'age_limit', label: 'Age Limit Reached' },
  { value: 'non_payment', label: 'Non-Payment' },
  { value: 'fraud', label: 'Fraud' },
  { value: 'death', label: 'Death' },
  { value: 'other', label: 'Other' },
] as const;

// =============================================================================
// LOADINGS & EXCLUSIONS
// =============================================================================

export const LOADING_TYPES = [
  { value: 'percentage', label: 'Percentage', suffix: '%' },
  { value: 'fixed', label: 'Fixed Amount', suffix: 'ZMW' },
  { value: 'exclusion', label: 'Exclusion (No coverage)' },
] as const;

export const DURATION_TYPES = [
  { value: 'permanent', label: 'Permanent' },
  { value: 'time_limited', label: 'Time Limited' },
  { value: 'reviewable', label: 'Reviewable' },
] as const;

export const EXCLUSION_TYPES = [
  { value: 'pre_existing', label: 'Pre-existing Condition' },
  { value: 'specific', label: 'Specific Condition' },
  { value: 'benefit', label: 'Benefit Exclusion' },
  { value: 'waiting_period', label: 'Extended Waiting Period' },
] as const;

// =============================================================================
// PROVINCES (ZAMBIA)
// =============================================================================

export const PROVINCES = [
  { value: 'central', label: 'Central Province' },
  { value: 'copperbelt', label: 'Copperbelt Province' },
  { value: 'eastern', label: 'Eastern Province' },
  { value: 'luapula', label: 'Luapula Province' },
  { value: 'lusaka', label: 'Lusaka Province' },
  { value: 'muchinga', label: 'Muchinga Province' },
  { value: 'northern', label: 'Northern Province' },
  { value: 'northwestern', label: 'North-Western Province' },
  { value: 'southern', label: 'Southern Province' },
  { value: 'western', label: 'Western Province' },
] as const;

// =============================================================================
// HELPER FUNCTIONS
// =============================================================================

export function getLabelByValue<T extends { value: string; label: string }>(
  list: readonly T[],
  value: string | undefined | null
): string {
  return list.find((item) => item.value === value)?.label ?? value ?? '-';
}

export function getStatusConfig<T extends { value: string; color: string; bgColor: string }>(
  list: readonly T[],
  value: string | undefined | null
): T | undefined {
  return list.find((item) => item.value === value);
}

export function calculateAge(dateOfBirth: string | Date): number {
  const today = new Date();
  const birthDate = new Date(dateOfBirth);
  let age = today.getFullYear() - birthDate.getFullYear();
  const monthDiff = today.getMonth() - birthDate.getMonth();
  if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
    age--;
  }
  return age;
}

export function formatCurrency(amount: number | null | undefined, currency = 'ZMW'): string {
  if (amount === null || amount === undefined) return '-';
  return new Intl.NumberFormat('en-ZM', {
    style: 'currency',
    currency,
    minimumFractionDigits: 2,
  }).format(amount);
}

// Helper functions
// export function getLabelByValue<T extends readonly { value: string; label: string }[]>(
//   list: T,
//   value: string
// ): string {
//   return list.find((item) => item.value === value)?.label ?? value;
// }

export function getConstantOptions<T extends readonly { value: string; label: string }[]>(
  list: T
): { value: string; label: string }[] {
  return [...list];
}
