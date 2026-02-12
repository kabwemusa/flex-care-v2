// libs/medical/data/src/lib/models/medical.models.ts

// ============================================================================
// COMMON & API RESPONSES
// ============================================================================

// export interface PaginatedResponse<T> {
//   status: 'success' | 'error';
//   message: string;
//   data: T[];
//   meta: {
//     current_page: number;
//     last_page: number;
//     per_page: number;
//     total: number;
//   };
// }

export interface DropdownOption {
  id: string;
  code?: string;
  name: string;
  [key: string]: unknown;
}

// ============================================================================
// SCHEME (Product Definition)
// ============================================================================
export interface MedicalScheme {
  id: string;
  code: string;
  name: string;
  slug: string;
  market_segment: string;
  market_segment_label?: string;
  description?: string;
  eligibility_rules?: EligibilityRules;
  underwriting_rules?: UnderwritingRules;
  effective_from: string;
  effective_to?: string;
  is_active: boolean;
  is_effective?: boolean;
  plans_count?: number;
  plans?: MedicalPlan[];
  created_at?: string;
  updated_at?: string;
}

export interface EligibilityRules {
  min_age?: number;
  max_age?: number;
  min_group_size?: number;
  max_group_size?: number;
  allowed_regions?: string[];
  required_documents?: string[];
}

export interface UnderwritingRules {
  require_medical_exam?: boolean;
  medical_exam_age_threshold?: number;
  require_declaration?: boolean;
  auto_accept_age_limit?: number;
}

// ============================================================================
// PLAN (Product Definition)
// ============================================================================
export interface MedicalPlan {
  id: string;
  scheme_id: string;
  code: string;
  name: string;
  tier_level?: number;
  plan_type: string;
  plan_type_label?: string;
  network_type?: string;
  network_type_label?: string;
  member_config?: MemberConfig;
  default_waiting_periods?: WaitingPeriodConfig;
  default_cost_sharing?: CostSharingConfig;
  description?: string;
  effective_from?: string;
  effective_to?: string;
  is_active: boolean;
  is_visible: boolean;
  plan_benefits_count?: number;
  plan_addons_count?: number;
  scheme?: MedicalScheme;
  plan_benefits?: PlanBenefit[];
  plan_addons?: PlanAddon[];
  rate_cards?: RateCard[];
  active_rate_card?: RateCard;
  created_at?: string;
}

export interface MemberConfig {
  max_dependents?: number;
  allowed_member_types?: string[];
  child_age_limit?: number;
  child_student_age_limit?: number;
  parent_age_limit?: number;
}

export interface WaitingPeriodConfig {
  general?: number;
  maternity?: number;
  pre_existing?: number;
  chronic?: number;
  dental?: number;
  optical?: number;
}

export interface CostSharingConfig {
  copay_type?: 'fixed' | 'percentage';
  copay_amount?: number;
  copay_percentage?: number;
  deductible?: number;
  out_of_pocket_max?: number;
}

// ============================================================================
// BENEFIT
// ============================================================================
export interface Benefit {
  id: string;
  parent_id?: string;
  code: string;
  name: string;
  display_name?: string;
  description?: string;
  benefit_type: string;
  benefit_type_label?: string;
  default_limit_type?: string;
  default_limit_frequency?: string;
  default_limit_basis?: string;
  applicable_member_types?: string[];
  requires_preauth: boolean;
  requires_referral: boolean;
  sort_order: number;
  is_active: boolean;
  is_root?: boolean;
  has_children?: boolean;
  full_path?: string;
  parent?: Benefit;
  children?: Benefit[];
}

export interface PlanBenefit {
  id: string;
  plan_id: string;
  benefit_id: string;
  parent_plan_benefit_id?: string;
  limit_type?: string;
  limit_frequency?: string;
  limit_basis?: string;
  limit_amount?: number;
  limit_count?: number;
  limit_days?: number;
  per_claim_limit?: number;
  per_day_limit?: number;
  max_claims_per_year?: number;
  waiting_period_days?: number;
  cost_sharing?: CostSharingConfig;
  is_covered: boolean;
  is_visible: boolean;
  display_value?: string;
  notes?: string;
  sort_order: number;
  is_sub_limit?: boolean;
  has_sub_limits?: boolean;
  benefit?: Benefit;
  member_limits?: PlanBenefitLimit[];
  child_plan_benefits?: PlanBenefit[];
}

export interface PlanBenefitLimit {
  id: string;
  plan_benefit_id: string;
  member_type: string;
  member_type_label?: string;
  min_age?: number;
  max_age?: number;
  age_band_label?: string;
  limit_amount?: number;
  limit_count?: number;
  limit_days?: number;
  display_value?: string;
}

// ============================================================================
// RATE CARD
// ============================================================================
export interface RateCard {
  id: string;
  plan_id: string;
  code: string;
  name: string;
  version: string;
  currency: string;
  premium_frequency: string;
  premium_frequency_label?: string;
  premium_basis: string;
  premium_basis_label?: string;
  member_type_factors?: Record<string, number>;
  effective_from: string;
  effective_to?: string;
  is_active: boolean;
  is_draft: boolean;
  is_approved: boolean;
  is_effective?: boolean;
  is_tiered?: boolean;
  approved_at?: string;
  approved_by?: string;
  notes?: string;
  entries_count?: number;
  tiers_count?: number;
  plan?: MedicalPlan;
  entries?: RateCardEntry[];
  tiers?: RateCardTier[];
}

export interface RateCardEntry {
  id: string;
  rate_card_id: string;
  min_age: number;
  max_age: number;
  age_band_label?: string;
  gender?: 'M' | 'F';
  gender_label?: string;
  region_code?: string;
  base_premium: number;
  formatted_premium?: string;
  is_unisex?: boolean;
  is_national?: boolean;
}

export interface RateCardTier {
  id: string;
  rate_card_id: string;
  tier_name: string;
  tier_description?: string;
  min_members: number;
  max_members?: number;
  member_range_label?: string;
  tier_premium: number;
  extra_member_premium?: number;
  formatted_premium?: string;
  has_extra_member_premium?: boolean;
  sort_order: number;
}

// ============================================================================
// ADDON
// ============================================================================
export interface Addon {
  id: string;
  code: string;
  name: string;
  description?: string;
  addon_type: string;
  addon_type_label?: string;
  pricing_type: string;
  pricing_type_label?: string;
  currency?: string;
  amount?: number;
  percentage?: number;
  percentage_basis?: string;
  effective_from?: string;
  effective_to?: string;
  is_active: boolean;
  is_effective?: boolean;
  sort_order: number;
  plan_addons_count?: number;
}

export interface PlanAddon {
  id: string;
  plan_id: string;
  addon_id: string;
  availability: string;
  availability_label?: string;
  is_mandatory?: boolean;
  is_optional?: boolean;
  is_included?: boolean;
  is_conditional?: boolean;
  requires_additional_premium?: boolean;
  conditions?: Record<string, unknown>;
  benefit_overrides?: Record<string, unknown>;
  is_active: boolean;
  sort_order: number;
  addon?: Addon;
}

// ============================================================================
// DISCOUNT & PROMO
// ============================================================================
export interface DiscountRule {
  id: string;
  scheme_id?: string;
  plan_id?: string;
  code: string;
  name: string;
  description?: string;
  adjustment_type: 'discount' | 'loading';
  adjustment_type_label?: string;
  value_type: 'percentage' | 'fixed';
  value: number;
  formatted_value?: string;
  applies_to?: 'base_premium' | 'total_premium';
  applies_to_label?: string;
  application_method: 'automatic' | 'manual' | 'promo_code';
  application_method_label?: string;
  trigger_rules?: TriggerRules;
  can_stack: boolean;
  max_total_discount?: number;
  priority: number;
  max_uses?: number;
  current_uses?: number;
  has_usage_limit?: boolean;
  is_usage_limit_reached?: boolean;
  terms_conditions?: string;
  effective_from?: string;
  effective_to?: string;
  is_active: boolean;
  is_discount?: boolean;
  is_loading?: boolean;
  is_automatic?: boolean;
  is_global?: boolean;
  scheme?: { id: string; name: string };
  plan?: { id: string; name: string };
  promo_codes_count?: number;
  promo_codes?: PromoCode[];
}

export interface TriggerRules {
  min_group_size?: number;
  billing_frequency?: string;
  min_members?: number;
  min_premium?: number;
  member_types?: string[];
}

export interface PromoCode {
  id: string;
  discount_rule_id: string;
  code: string;
  name?: string;
  description?: string;
  max_uses?: number;
  current_uses: number;
  remaining_uses?: number;
  has_max_uses?: boolean;
  is_exhausted?: boolean;
  valid_from: string;
  valid_to: string;
  is_valid?: boolean;
  is_expired?: boolean;
  is_usable?: boolean;
  days_until_expiry?: number;
  eligible_schemes?: string[];
  eligible_plans?: string[];
  eligible_groups?: string[];
  is_active: boolean;
  discount_rule?: DiscountRule;
}

// ============================================================================
// LOADING RULES
// ============================================================================
export interface LoadingRule {
  id: string;
  code: string;
  condition_name: string;
  condition_category: string;
  condition_category_label?: string;
  icd10_code?: string;
  related_icd_codes?: string[];
  loading_type: string;
  loading_type_label?: string;
  loading_value?: number;
  formatted_loading_value?: string;
  duration_type: string;
  duration_type_label?: string;
  duration_months?: number;
  duration_label?: string;
  is_permanent?: boolean;
  is_time_limited?: boolean;
  is_reviewable?: boolean;
  exclusion_available: boolean;
  exclusion_terms?: string;
  exclusion_benefit_id?: string;
  required_documents?: string[];
  underwriting_notes?: string;
  is_active: boolean;
  min_loading?: number;
  max_loading?: number;
}

// ============================================================================
// EXCLUSIONS & WAITING PERIODS
// ============================================================================
export interface PlanExclusion {
  id: string;
  plan_id: string;
  benefit_id?: string;
  code: string;
  name: string;
  description?: string;
  exclusion_type: 'absolute' | 'conditional' | 'time_limited' | 'pre_existing';
  exclusion_type_label?: string;
  conditions?: Record<string, unknown>;
  exclusion_period_days?: number;
  is_general?: boolean;
  is_benefit_specific?: boolean;
  is_time_limited?: boolean;
  sort_order: number;
  is_active: boolean;
  plan?: {
    id: string;
    code: string;
    name: string;
  };
  benefit?: {
    id: string;
    code: string;
    name: string;
  };
  created_at?: string;
  updated_at?: string;
}

export interface PlanWaitingPeriod {
  id: string;
  plan_id: string;
  benefit_id?: string;
  waiting_type: string;
  waiting_type_label?: string;
  days: number;
  applies_to?: string[];
  can_be_waived?: boolean;
  waiver_conditions?: string;
  is_active: boolean;
  benefit?: Benefit;
}

// ============================================================================
// CORPORATE GROUP
// ============================================================================
export interface CorporateGroup {
  id: string;
  code: string;
  name: string;
  trading_name?: string;
  registration_number?: string;
  tax_number?: string;
  industry?: string;
  industry_label?: string;
  company_size?: string;
  company_size_label?: string;
  employee_count?: number;
  address?: string;
  physical_address?: string; // Merged
  city?: string;
  province?: string; // Merged
  country?: string;
  postal_code?: string; // Merged
  email?: string;
  phone?: string;
  website?: string;

  // Billing
  payment_terms?: string; // Merged
  payment_terms_days?: number;
  preferred_payment_method?: string; // Merged
  billing_contact_name?: string;
  billing_email?: string;
  billing_address?: string; // Merged
  credit_limit?: number;

  // Status
  status: string;
  status_label?: string;
  is_active?: boolean;
  is_prospect?: boolean;
  is_suspended?: boolean;

  // Broker
  broker_id?: string; // Merged
  broker_commission_rate?: number; // Merged

  notes?: string;
  contacts_count?: number;
  policies_count?: number;

  contacts?: GroupContact[];
  primary_contact?: GroupContact;
  policies?: Policy[];
  applications?: Application[];
  stats?: {
    total_policies: number;
    active_policies: number;
    total_applications: number;
    total_application_members: number;
    policies_by_plan: any;
    applications_by_plan: any;
  };

  created_at?: string;
  updated_at?: string;
}

export interface GroupContact {
  id: string;
  group_id: string;
  contact_type: string;
  contact_type_label?: string;
  is_primary: boolean;
  title?: string;
  first_name: string;
  last_name: string;
  full_name?: string;
  job_title?: string;
  department?: string;
  email?: string;
  phone?: string;
  mobile?: string;
  has_portal_access: boolean;
  permissions?: string[];
  is_active: boolean;
  notes?: string;
  created_at?: string;
  updated_at?: string;
}

// ============================================================================
// APPLICATION (Application-First Workflow)
// ============================================================================
export interface Application {
  id: string;
  application_number: string;
  application_type: string;
  application_type_label?: string;
  policy_type: string;
  policy_type_label?: string;

  // References
  scheme_id: string;
  plan_id: string;
  rate_card_id: string;
  group_id?: string;
  scheme?: { id: string; code: string; name: string };
  plan?: { id: string; code: string; name: string };
  rate_card?: { id: string; code: string; name: string };
  group?: { id: string; code: string; name: string };

  // Contact
  contact_name?: string;
  contact_email?: string;
  contact_phone?: string;
  applicant_name?: string;

  // Dates
  proposed_start_date?: string;
  proposed_end_date?: string;
  policy_term_months: number;
  quote_valid_until?: string;
  days_until_expiry?: number;

  // Billing
  billing_frequency: string;
  billing_frequency_label?: string;
  currency: string;

  // Premium
  base_premium: number;
  addon_premium: number;
  loading_amount: number;
  discount_amount: number;
  total_premium: number;
  tax_amount: number;
  gross_premium: number;
  monthly_premium?: number;
  annual_premium?: number;

  // Counts
  member_count: number;
  principal_count: number;
  dependent_count: number;

  // Status
  status: string;
  status_label?: string;
  underwriting_status?: string;
  underwriting_status_label?: string;
  underwriting_notes?: string;
  underwriter_id?: string;

  // Status flags
  is_draft?: boolean;
  is_quoted?: boolean;
  is_submitted?: boolean;
  is_underwriting?: boolean;
  is_approved?: boolean;
  is_declined?: boolean;
  is_accepted?: boolean;
  is_converted?: boolean;
  is_expired?: boolean;
  is_corporate?: boolean;
  is_renewal?: boolean;

  // Action flags
  can_be_edited?: boolean;
  can_be_submitted?: boolean;
  can_be_underwritten?: boolean;
  can_be_accepted?: boolean;
  can_be_converted?: boolean;

  // Approval workflow
  approval_status?: {
    has_request: boolean;
    status?: string;
    current_step?: {
      id: string;
      name: string;
      group: string;
      order: number;
    };
    progress_percentage?: number;
    current_step_number?: number;
    total_steps?: number;
  };

  // Timestamps
  quoted_at?: string;
  submitted_at?: string;
  underwriting_started_at?: string;
  underwriting_completed_at?: string;
  accepted_at?: string;
  acceptance_reference?: string;
  converted_at?: string;

  // Conversion
  renewal_of_policy_id?: string;
  renewal_of_policy?: { id: string; policy_number: string };
  converted_policy_id?: string;
  converted_policy?: { id: string; policy_number: string };

  // Sales
  source?: string;
  sales_agent_id?: string;
  broker_id?: string;
  commission_rate?: number;
  promo_code_id?: string;
  applied_discounts?: AppliedDiscount[];

  // Relations
  members?: ApplicationMember[];
  addons?: ApplicationAddon[];
  documents?: ApplicationDocument[];

  notes?: string;
  metadata?: Record<string, unknown>;
  created_at?: string;
  updated_at?: string;
}

export interface ApplicationMember {
  id: string;
  application_id: string;

  // Type
  member_type: string;
  member_type_label?: string;
  relationship?: string;
  relationship_label?: string;
  is_principal?: boolean;
  is_dependent?: boolean;

  // Principal reference
  principal_member_id?: string;
  principal?: { id: string; name: string };

  // Personal info
  title?: string;
  first_name: string;
  middle_name?: string;
  last_name: string;
  full_name?: string;
  short_name?: string;
  initials?: string;
  date_of_birth?: string;
  age?: number;
  age_at_inception?: number;
  age_band?: string;
  gender?: string;
  gender_label?: string;
  marital_status?: string;

  // Identification
  national_id?: string;
  passport_number?: string;

  // Contact
  email?: string;
  phone?: string;
  mobile?: string;
  address?: string;
  city?: string;

  // Employment
  employee_number?: string;
  job_title?: string;
  department?: string;
  employment_date?: string;
  salary?: number;
  salary_band?: string;

  // Premium
  base_premium: number;
  loading_amount: number;
  total_premium: number;

  // Medical
  has_pre_existing_conditions?: boolean;
  declared_conditions?: DeclaredCondition[];
  medical_history_notes?: string;

  // Underwriting
  underwriting_status?: string;
  underwriting_status_label?: string;
  is_underwriting_pending?: boolean;
  is_underwriting_approved?: boolean;
  is_underwriting_declined?: boolean;
  has_terms?: boolean;
  applied_loadings?: AppliedLoading[];
  applied_exclusions?: AppliedExclusion[];
  has_loadings?: boolean;
  has_exclusions?: boolean;
  loadings_count?: number;
  exclusions_count?: number;
  underwriting_notes?: string;
  underwritten_by?: string;
  underwritten_at?: string;

  // Conversion
  converted_member_id?: string;
  is_converted?: boolean;

  // Status
  is_active: boolean;
  dependent_count?: number;

  created_at?: string;
  updated_at?: string;
}

export interface ApplicationAddon {
  id: string;
  application_id: string;
  addon_id: string;
  addon_rate_id?: string;
  addon_name?: string;
  addon_code?: string;
  premium: number;
  is_active?: boolean;
  addon?: Addon;
}

export interface ApplicationDocument {
  id: string;
  application_id: string;
  application_member_id?: string;
  document_type: string;
  title: string;
  file_name: string;
  file_path?: string;
  mime_type?: string;
  file_size?: number;
  is_verified: boolean;
  verified_by?: string;
  verified_at?: string;
  member?: { id: string; first_name: string; last_name: string };
}

export interface DeclaredCondition {
  name: string;
  icd_code?: string;
  diagnosis_date?: string;
  treatment?: string;
  notes?: string;
}

export interface AppliedLoading {
  loading_rule_id?: string;
  condition_name: string;
  icd10_code?: string;
  loading_type: string;
  value: number;
  loading_amount?: number;
  duration_type?: string;
  duration_months?: number;
  notes?: string;
}

export interface AppliedExclusion {
  exclusion_name: string;
  exclusion_type?: string;
  benefit_id?: string;
  icd10_codes?: string[];
  description?: string;
  is_permanent?: boolean;
  end_date?: string;
  notes?: string;
}

export interface AppliedDiscount {
  type: string;
  code?: string;
  discount_rule_id?: string;
  amount: number;
  applied_at: string;
}

// ============================================================================
// POLICY (Post-conversion)
// ============================================================================
export interface Policy {
  id: string;
  policy_number: string;
  application_id?: string;
  policy_type: string;
  policy_type_label?: string;

  // References
  scheme_id: string;
  plan_id: string;
  rate_card_id: string;
  group_id?: string;
  principal_member_id?: string;
  scheme?: { id: string; code: string; name: string };
  plan?: { id: string; code: string; name: string };
  rate_card?: { id: string; code: string; name: string };
  group?: { id: string; code: string; name: string };

  // Policy holder
  holder_name?: string;
  holder_email?: string;
  holder_phone?: string;
  holder_address?: string;

  // Dates
  inception_date: string;
  expiry_date: string;
  renewal_date?: string;
  policy_term_months: number;

  // Billing
  billing_frequency: string;
  billing_frequency_label?: string;
  currency: string;

  // Premium
  base_premium: number;
  addon_premium: number;
  loading_amount: number;
  discount_amount: number;
  total_premium: number;
  tax_amount: number;
  gross_premium: number;

  // Counts
  member_count: number;
  principal_count: number;
  dependent_count: number;

  // Status
  status: string;
  status_label?: string;
  underwriting_status?: string;
  underwriting_status_label?: string;

  // Status flags
  is_active?: boolean;
  is_suspended?: boolean;
  is_cancelled?: boolean;
  is_expired?: boolean;
  is_expiring?: boolean;
  is_corporate?: boolean;
  is_in_force?: boolean;
  days_to_expiry?: number;

  // Underwriting (from Application)
  underwriting_notes?: string;
  underwritten_by?: string;
  underwritten_at?: string;

  // Issuance (Application Conversion)
  issued_at?: string;
  issued_by?: string;

  // Suspension
  suspended_at?: string;
  suspension_reason?: string;

  // Cancellation
  cancelled_at?: string;
  cancelled_by?: string;
  cancellation_reason?: string;
  cancellation_notes?: string;

  // Renewal
  previous_policy_id?: string;
  renewed_to_policy_id?: string;
  renewal_count?: number;
  can_be_renewed?: boolean;
  is_auto_renew?: boolean;

  // Computed premiums
  monthly_premium?: number;
  annual_premium?: number;

  // Computed display
  policy_holder_name?: string;

  // Relations
  principal_member?: Member;
  members?: Member[];
  policy_addons?: PolicyAddon[];
  documents?: PolicyDocument[];

  created_at?: string;
  updated_at?: string;
}

export interface MemberMetadata {
  // Pro-rating information
  annual_premium?: number;
  prorated_premium?: number;
  effective_date?: string;
  days_remaining?: number;
  pro_rata_method?: string;
  calculated_at?: string;

  // Additional custom data
  [key: string]: any;
}

export interface Member {
  id: string;
  policy_id: string;
  application_member_id?: string;
  member_number: string;
  member_type: string;
  member_type_label?: string;
  principal_id?: string;
  relationship?: string;
  relationship_label?: string;

  // Personal info
  title?: string;
  first_name: string;
  middle_name?: string;
  last_name: string;
  full_name?: string;
  date_of_birth?: string;
  age?: number;
  gender?: string;
  gender_label?: string;

  // Identification
  national_id?: string;
  passport_number?: string;

  // Contact
  email?: string;
  phone?: string;
  mobile?: string;
  address?: string;
  city?: string;

  // Employment
  employee_number?: string;
  job_title?: string;
  department?: string;

  // Cover
  cover_start_date: string;
  cover_end_date: string;
  waiting_period_end_date?: string;

  // Premium
  premium: number;
  loading_amount: number;

  // Card
  card_number?: string;
  card_status?: string;
  card_issued_date?: string;
  card_expiry_date?: string;

  // Status
  status: string;
  status_label?: string;
  is_active?: boolean;
  is_principal?: boolean;
  is_dependent?: boolean;
  has_pre_existing_conditions?: boolean;

  // Relations
  principal?: Member;
  dependents?: Member[];
  loadings?: MemberLoading[];
  exclusions?: MemberExclusion[];

  // Meta
  metadata?: MemberMetadata | string; // JSON field
  notes?: string;

  created_at?: string;
  updated_at?: string;
}

export interface MemberLoading {
  id: string;
  member_id: string;
  loading_rule_id?: string;
  condition_name: string;
  icd10_code?: string;
  loading_type: string;
  loading_value: number;
  loading_amount: number;
  duration_type: string;
  duration_months?: number;
  start_date: string;
  end_date?: string;
  review_date?: string;
  status: string;
  notes?: string;
}

export interface MemberExclusion {
  id: string;
  member_id: string;
  benefit_id?: string;
  exclusion_type: string;
  exclusion_name: string;
  icd10_codes?: string[];
  description?: string;
  is_permanent: boolean;
  start_date: string;
  end_date?: string;
  review_date?: string;
  status: string;
  notes?: string;
  benefit?: Benefit;
}

export interface PolicyAddon {
  id: string;
  policy_id: string;
  addon_id: string;
  addon_rate_id?: string;
  addon_name?: string;
  addon_code?: string;
  premium: number;
  effective_date?: string; // Merged from simpler interface
  end_date?: string; // Merged from simpler interface
  is_active: boolean;
  addon?: Addon;
}

export interface PolicyDocument {
  id: string;
  policy_id: string;
  document_type: string;
  title: string;
  file_name: string;
  file_path?: string;
  mime_type?: string;
  file_size?: number;
  is_verified: boolean;
  uploaded_by?: string;
}

export interface MemberDocument {
  id: string;
  member_id: string;
  document_type: string;
  document_type_label?: string;
  file_name: string;
  file_path: string;
  file_size: number;
  mime_type: string;
  is_verified: boolean;
  verified_by?: string;
  verified_at?: string;
  uploaded_at: string;
  notes?: string;
}

// ============================================================================
// QUOTE
// ============================================================================
export interface QuoteRequest {
  rate_card_id: string;
  plan_id?: string;
  members: QuoteMember[];
  addons?: { addon_id: string }[];
  billing_frequency?: string;
}

export interface QuoteMember {
  date_of_birth: string;
  member_type: string;
  gender?: string;
  age?: number;
}

export interface QuoteResult {
  success: boolean;
  base_premium: number;
  addon_premium: number;
  total_premium: number;
  tax_amount: number;
  gross_premium: number;
  member_count: number;
  member_breakdown: QuoteMemberBreakdown[];
  addon_breakdown: QuoteAddonBreakdown[];
  billing_frequency: string;
  currency: string;
  annual_amount: number;
}

export interface QuoteMemberBreakdown {
  member_type: string;
  age: number;
  premium: number;
}

export interface QuoteAddonBreakdown {
  addon_id: string;
  addon_name: string;
  premium: number;
}

// ============================================================================
// GROUP QUOTE
// ============================================================================
export interface GroupQuoteRequest {
  group_id: string;
  plan_id: string;
  rate_card_id?: string;
  billing_frequency?: string;
  members: QuoteMember[];
  addons?: { addon_id: string }[];
  discount_codes?: string[];
  show_tier_breakdown?: boolean;
  show_volume_discounts?: boolean;
  include_next_tier_info?: boolean;
}

export interface GroupQuoteResult {
  group_id: string;
  group_name: string;
  plan_info: {
    id: string;
    name: string;
    scheme_name: string;
    rate_card_id: string;
    rate_card_name: string;
    premium_basis: string;
  };
  member_summary: {
    total: number;
    principals: number;
    spouses: number;
    children: number;
    parents: number;
    other: number;
  };
  tier_info?: {
    current_tier: {
      tier_number: number;
      min_members: number;
      max_members: number | null;
      tier_premium: number;
      extra_member_premium: number;
    };
    next_tier?: {
      tier_number: number;
      min_members: number;
      max_members: number | null;
      members_needed: number;
      potential_savings: number;
      tier_premium: number;
    };
  };
  volume_discounts: {
    id: string;
    name: string;
    description: string;
    discount_type: string;
    discount_value: number;
    discount_amount: number;
    triggered_by: string;
    can_stack: boolean;
  }[];
  premium_breakdown: {
    base_premium: number;
    addon_premium: number;
    subtotal: number;
    tier_savings: number;
    volume_discounts: number;
    other_discounts: number;
    total_discounts: number;
    total_premium: number;
    per_member_average: number;
  };
  billing_frequency: string;
  currency: string;
  generated_at: string;
  valid_until: string;
}

// ============================================================================
// ENDORSEMENT
// ============================================================================
export interface Endorsement {
  id: string;
  endorsement_number: string;

  // Policy
  policy_id: string;
  policy?: {
    id: string;
    policy_number: string;
    holder_name?: string;
    status?: string;
  };

  // Type
  endorsement_type: string;
  type_label?: string;
  description: string;

  // Dates
  effective_date: string;
  request_date?: string;

  // Financial Impact
  premium_adjustment: number;
  prorated_amount: number;
  generates_invoice: boolean;
  generates_refund: boolean;
  has_financial_impact?: boolean;
  invoice_id?: string;

  // Change Details
  changes?: Record<string, unknown>;
  before_snapshot?: Record<string, unknown>;
  after_snapshot?: Record<string, unknown>;

  // Related Records
  member_id?: string;
  member?: {
    id: string;
    member_number: string;
    full_name?: string;
    member_type?: string;
    status?: string;
  };
  addon_id?: string;
  addon?: {
    id: string;
    name: string;
    code?: string;
  };
  new_plan_id?: string;
  new_plan?: {
    id: string;
    name: string;
    code?: string;
  };

  // Status
  status: string;
  status_label?: string;
  is_pending?: boolean;
  is_approved?: boolean;
  is_rejected?: boolean;
  is_processed?: boolean;
  is_cancelled?: boolean;

  // Workflow permissions
  can_be_approved?: boolean;
  can_be_rejected?: boolean;
  can_be_processed?: boolean;
  can_be_cancelled?: boolean;

  // Workflow tracking
  requested_by?: string;
  approved_by?: string;
  approved_at?: string;
  processed_by?: string;
  processed_at?: string;
  rejection_reason?: string;

  // Additional Info
  notes?: string;
  supporting_documents?: unknown[];

  // Type flags
  is_addition?: boolean;
  is_removal?: boolean;
  is_plan_change?: boolean;

  created_at?: string;
  updated_at?: string;
}

export interface EndorsementFilters {
  policy_id?: string;
  status?: string;
  endorsement_type?: string;
  search?: string;
  from_date?: string;
  to_date?: string;
  page?: number;
  per_page?: number;
  sort_by?: string;
  sort_order?: 'asc' | 'desc';
}

// ============================================================================
// STATISTICS
// ============================================================================

export interface PolicyStats {
  total: number;
  active: number;
  suspended: number;
  lapsed: number;
  cancelled: number;
  expired: number;
  for_renewal: number;
  total_premium: number;
  total_members: number;
}

export interface GroupStats {
  total_groups: number;
  active_groups: number;
  total_policies: number;
  total_members: number;
}

export interface MemberStats {
  total: number;
  active: number;
  suspended: number;
  terminated: number;
  principals: number;
  dependents: number;
  cards_issued: number;
  cards_active: number;
}

export interface ApplicationStats {
  total: number;
  draft: number;
  quoted: number;
  submitted: number;
  underwriting: number;
  approved: number;
  accepted: number;
  converted: number;
  total_quoted_premium: number;
}

export interface EndorsementStats {
  total: number;
  pending: number;
  approved: number;
  processed: number;
  rejected: number;
  cancelled: number;
  by_type?: Record<string, number>;
  total_premium_adjustments?: number;
}

// ============================================================================
// FORM PAYLOADS
// ============================================================================

export interface CreateGroupPayload {
  name: string;
  trading_name?: string;
  registration_number?: string;
  tax_number?: string;
  industry?: string;
  company_size?: string;
  employee_count?: number;
  email?: string;
  phone?: string;
  website?: string;
  physical_address?: string;
  city?: string;
  province?: string;
  payment_terms?: string;
  preferred_payment_method?: string;
  broker_id?: string;
  broker_commission_rate?: number;
  notes?: string;
  primary_contact?: Omit<CreateContactPayload, 'group_id'>;
}

export interface CreateContactPayload {
  group_id?: string;
  contact_type: string;
  first_name: string;
  last_name: string;
  job_title?: string;
  email?: string;
  phone?: string;
  mobile?: string;
  has_portal_access?: boolean;
  notes?: string;
}

export interface CreatePolicyPayload {
  policy_type: string;
  scheme_id: string;
  plan_id: string;
  rate_card_id?: string;
  group_id?: string;
  inception_date: string;
  policy_term_months: number;
  billing_frequency: string;
  is_auto_renew?: boolean;
  promo_code?: string;
  source_channel?: string;
  notes?: string;
  // For individual policies
  principal_member?: Omit<CreateMemberPayload, 'policy_id'>;
}

export interface CreateMemberPayload {
  policy_id?: string;
  member_type: string;
  principal_member_id?: string;
  relationship?: string;
  title?: string;
  first_name: string;
  middle_name?: string;
  last_name: string;
  gender: string;
  date_of_birth: string;
  marital_status?: string;
  id_type?: string;
  id_number?: string;
  email?: string;
  phone?: string;
  mobile?: string;
  address?: string;
  city?: string;
  province?: string;
  employee_number?: string;
  department?: string;
  job_title?: string;
  employment_date?: string;
  effective_date?: string;
  notes?: string;
}

export interface AddLoadingPayload {
  member_id?: string;
  loading_rule_id?: string;
  condition_name: string;
  icd10_code?: string;
  loading_type: string;
  loading_value?: number;
  duration_type: string;
  duration_months?: number;
  effective_date: string;
  notes?: string;
}

export interface AddExclusionPayload {
  member_id?: string;
  exclusion_type: string;
  condition_name?: string;
  benefit_id?: string;
  icd10_codes?: string[];
  effective_date: string;
  end_date?: string;
  is_permanent?: boolean;
  reason?: string;
  notes?: string;
}

export interface CreateEndorsementPayload {
  policy_id: string;
  endorsement_type: string;
  description: string;
  effective_date: string;
  request_date?: string;
  notes?: string;
  supporting_documents?: unknown[];

  // Type-specific fields
  member_data?: {
    member_type: string;
    first_name: string;
    last_name: string;
    date_of_birth: string;
    gender: string;
    title?: string;
    middle_name?: string;
    national_id?: string;
    passport_number?: string;
    email?: string;
    phone?: string;
    mobile?: string;
    address?: string;
    city?: string;
    principal_id?: string;
    relationship?: string;
  };
  member_id?: string;
  reason?: string;
  addon_id?: string;
  new_plan_id?: string;
  changes?: Record<string, unknown>;
}

// ============================================================================
// CLAIMS
// ============================================================================
export interface Claim {
  id: string;
  claim_number: string;
  policy_id: string;
  member_id: string;
  claim_type: string;
  claim_type_label?: string;
  submission_type?: string;
  submission_type_label?: string;
  submission_channel?: string;
  service_date: string;
  service_end_date?: string;
  admission_date?: string;
  discharge_date?: string;
  days_admitted?: number;
  is_in_patient?: boolean;
  provider_id?: string;
  provider_name?: string;
  provider_type?: string;
  provider_type_label?: string;
  provider_invoice_number?: string;
  primary_diagnosis?: string;
  primary_icd_code?: string;
  secondary_diagnoses?: Array<{ diagnosis: string; icd_code?: string }>;
  diagnosis_notes?: string;
  currency: string;
  claimed_amount: number;
  approved_amount: number;
  copay_amount: number;
  deductible_amount: number;
  excess_amount: number;
  excluded_amount: number;
  payable_amount: number;
  paid_amount: number;
  net_payable?: number;
  outstanding_amount?: number;
  payment_method?: string;
  payment_reference?: string;
  payment_date?: string;
  paid_to?: string;
  bank_name?: string;
  account_number?: string;
  requires_preauth?: boolean;
  preauth_number?: string;
  preauth_status?: string;
  preauth_amount?: number;
  preauth_at?: string;
  status: string;
  status_label?: string;
  substatus?: string;
  priority?: number;
  rejection_reason?: string;
  rejection_reason_label?: string;
  rejection_notes?: string;
  is_flagged?: boolean;
  flag_reason?: string;
  fraud_score?: number;
  requires_audit?: boolean;
  can_be_edited?: boolean;
  can_be_processed?: boolean;
  can_be_approved?: boolean;
  can_be_paid?: boolean;
  assigned_to?: string;
  assigned_at?: string;
  processed_by?: string;
  processed_at?: string;
  approved_by?: string;
  approved_at?: string;
  rejected_by?: string;
  rejected_at?: string;
  received_at?: string;
  first_response_at?: string;
  completed_at?: string;
  tat_days?: number;
  policy?: {
    id: string;
    policy_number: string;
    holder_name: string;
    status?: string;
    inception_date?: string;
    expiry_date?: string;
    plan?: { id: string; name: string; code: string };
    scheme?: { id: string; name: string; code: string };
  };
  member?: Member;
  lines?: ClaimLine[];
  documents?: ClaimDocument[];
  notes?: ClaimNote[];
  documents_count?: number;
  notes_count?: number;
  lines_count?: number;
  metadata?: Record<string, unknown>;
  internal_notes?: string;
  created_at?: string;
  updated_at?: string;
}

export interface ClaimLine {
  id: string;
  line_number: number;
  service_code?: string;
  service_description: string;
  service_date?: string;
  quantity: number;
  unit?: string;
  unit_price: number;
  claimed_amount: number;
  approved_amount: number;
  copay_amount: number;
  deductible_amount: number;
  excess_amount: number;
  excluded_amount: number;
  payable_amount: number;
  status: string;
  status_label?: string;
  rejection_reason?: string;
  rejection_reason_label?: string;
  adjudication_notes?: string;
  benefit?: {
    id: string;
    name: string;
    code: string;
    category?: string;
  };
  benefit_limit?: number;
  benefit_used_before?: number;
  benefit_remaining?: number;
}

export interface ClaimDocument {
  id: string;
  document_type: string;
  document_type_label?: string;
  title: string;
  file_name: string;
  mime_type?: string;
  file_size?: number;
  file_size_formatted?: string;
  is_verified?: boolean;
  verified_at?: string;
  created_at?: string;
}

export interface ClaimNote {
  id: string;
  note_type: string;
  note_type_label?: string;
  content: string;
  old_status?: string;
  old_status_label?: string;
  new_status?: string;
  new_status_label?: string;
  is_internal?: boolean;
  is_system?: boolean;
  created_by?: string;
  created_at?: string;
}

export interface ClaimFilters {
  search?: string;
  status?: string;
  claim_type?: string;
  policy_id?: string;
  member_id?: string;
  assigned_to?: string;
  unassigned?: boolean;
  flagged?: boolean;
  high_priority?: boolean;
  service_date_from?: string;
  service_date_to?: string;
  created_from?: string;
  created_to?: string;
  page?: number;
  per_page?: number;
  sort_by?: string;
  sort_order?: 'asc' | 'desc';
}

export interface ClaimStats {
  summary: {
    total: number;
    submitted: number;
    in_review: number;
    pending_approval: number;
    approved: number;
    rejected: number;
    paid: number;
    closed: number;
    total_claimed: number;
    total_approved: number;
    total_paid: number;
    avg_tat_days: number;
  };
  by_type: Record<string, { count: number; total_claimed: number }>;
  approval_rate: number;
}

export interface CreateClaimPayload {
  policy_id: string;
  member_id: string;
  claim_type: string;
  service_date: string;
  claimed_amount: number;
  submission_type?: string;
  submission_channel?: string;
  service_end_date?: string;
  admission_date?: string;
  discharge_date?: string;
  days_admitted?: number;
  provider_name?: string;
  provider_type?: string;
  provider_invoice_number?: string;
  primary_diagnosis?: string;
  primary_icd_code?: string;
  secondary_diagnoses?: Array<{ diagnosis: string; icd_code?: string }>;
  diagnosis_notes?: string;
  currency?: string;
  requires_preauth?: boolean;
  preauth_number?: string;
  lines?: CreateClaimLinePayload[];
}

export interface CreateClaimLinePayload {
  service_description: string;
  claimed_amount: number;
  service_date?: string;
  service_code?: string;
  benefit_id?: string;
  quantity?: number;
  unit?: string;
  unit_price?: number;
}

export interface AdjudicateLinePayload {
  line_id: string;
  action: 'approve' | 'reject';
  approved_amount?: number;
  copay_amount?: number;
  deductible_amount?: number;
  excess_amount?: number;
  excluded_amount?: number;
  reason?: string;
  notes?: string;
}

export interface RecordPaymentPayload {
  amount: number;
  payment_method: string;
  payment_reference?: string;
  payment_date?: string;
  paid_to?: string;
  bank_name?: string;
  account_number?: string;
}

// ============================================================================
// BENEFIT UTILIZATION
// ============================================================================

export interface BenefitLimitValue {
  value: number | null;
  formatted: string;
  unit: 'currency' | 'visits' | 'days' | null;
}

export interface MemberBenefitUtilization {
  id: string;
  benefit_id: string;
  benefit_name: string;
  benefit_code?: string;
  limit_type: 'monetary' | 'count' | 'days' | 'unlimited';
  limit_type_label: string;
  limit: BenefitLimitValue;
  used: BenefitLimitValue;
  remaining: BenefitLimitValue;
  utilization_percentage: number;
  is_exhausted: boolean;
  claims_count: number;
  last_claim_at?: string;
}

export interface UtilizationCategory {
  category: string;
  benefits: MemberBenefitUtilization[];
}

export interface MemberBenefitSummary {
  member_id: string;
  member_name: string;
  policy_number: string;
  plan_name: string;
  period: {
    start: string;
    end: string;
  };
  total_benefits: number;
  exhausted_benefits: number;
  benefits_by_category: Record<string, UtilizationCategory>;
  benefits: MemberBenefitUtilization[];
}

export interface BenefitUtilizationHistory {
  id: string;
  transaction_type: string;
  transaction_type_label: string;
  amount_change: number;
  count_change: number;
  days_change: number;
  balance_amount: number | null;
  claim_number?: string;
  notes?: string;
  created_at: string;
}

export interface BenefitHistoryResponse {
  utilization: {
    id: string;
    benefit_name: string;
    limit_type: string;
    limit: string;
    used: string;
    remaining: string;
    utilization_percentage: number;
    is_exhausted: boolean;
    period_start: string;
    period_end: string;
  };
  history: BenefitUtilizationHistory[];
}

export interface CheckBenefitEligibilityPayload {
  member_id: string;
  benefit_id: string;
  amount: number;
  service_date?: string;
}

export interface BenefitEligibilityResult {
  eligible: boolean;
  reason?: string;
  reason_code?: string;
  limit?: string;
  used?: string;
  remaining?: string;
  available_amount?: number;
  requested_amount?: number;
  waiting_end_date?: string;
  days_remaining?: number;
}

// ============================================================================
// BILLING - INVOICES
// ============================================================================

export interface Invoice {
  id: string;
  invoice_number: string;
  invoice_type: string;
  invoice_type_label?: string;
  status: string;
  status_label?: string;

  // Policy & Group
  policy_id?: string;
  policy?: {
    id: string;
    policy_number: string;
    holder_name?: string;
    holder_email?: string;
    status?: string;
    inception_date?: string;
    expiry_date?: string;
  };
  group_id?: string;
  group?: {
    id: string;
    code: string;
    name: string;
    billing_email?: string;
    address?: string;
  };

  // Endorsement (if applicable)
  endorsement_id?: string;
  endorsement?: {
    id: string;
    endorsement_number: string;
    endorsement_type: string;
    effective_date?: string;
  };

  // Billing Period
  billing_period_start?: string;
  billing_period_end?: string;
  billing_period?: string;

  // Dates
  invoice_date: string;
  due_date: string;
  paid_date?: string;

  // Amounts
  currency: string;
  subtotal: number;
  tax_amount: number;
  discount_amount: number;
  total_amount: number;
  paid_amount: number;
  balance: number;
  payment_progress?: number;

  // Overdue
  days_overdue: number;
  is_overdue?: boolean;

  // Bill To
  bill_to_name?: string;
  bill_to_email?: string;
  bill_to_address?: string;

  // Communication
  sent_at?: string;
  sent_via?: string;
  reminder_count?: number;
  last_reminder_at?: string;

  // Line Items
  items?: InvoiceItem[];

  // Allocations
  allocations?: PaymentAllocation[];
  total_allocated?: number;

  // State flags
  is_draft?: boolean;
  is_sent?: boolean;
  is_paid?: boolean;
  is_partially_paid?: boolean;
  is_cancelled?: boolean;
  is_written_off?: boolean;
  is_credit_note?: boolean;

  // Actions
  can_be_sent?: boolean;
  can_receive_payment?: boolean;
  can_be_cancelled?: boolean;

  // Metadata
  notes?: string;
  metadata?: Record<string, unknown>;
  created_by?: string;
  created_at?: string;
  updated_at?: string;
}

export interface InvoiceItem {
  id: string;
  line_number: number;
  item_type: string;
  item_type_label?: string;
  description: string;
  quantity: number;
  unit_price: number;
  amount: number;
  tax_amount: number;
  total: number;
  member_id?: string;
  member?: {
    id: string;
    member_number: string;
    full_name?: string;
  };
  reference_type?: string;
  reference_id?: string;
}

export interface InvoiceFilters {
  search?: string;
  status?: string;
  invoice_type?: string;
  policy_id?: string;
  group_id?: string;
  outstanding?: boolean;
  overdue?: boolean;
  invoice_date_from?: string;
  invoice_date_to?: string;
  due_date_from?: string;
  due_date_to?: string;
  page?: number;
  per_page?: number;
  sort_by?: string;
  sort_order?: 'asc' | 'desc';
}

// ============================================================================
// BILLING - PAYMENTS
// ============================================================================

export interface Payment {
  id: string;
  payment_number: string;
  status: string;
  status_label?: string;

  // Policy & Group
  policy_id?: string;
  policy?: {
    id: string;
    policy_number: string;
    holder_name?: string;
    status?: string;
  };
  group_id?: string;
  group?: {
    id: string;
    code: string;
    name: string;
  };

  // Payment Details
  payment_date: string;
  currency: string;
  amount: number;
  allocated_amount: number;
  unallocated_amount: number;
  allocation_progress?: number;

  // Payment Method
  payment_method: string;
  payment_method_label?: string;
  payment_reference?: string;
  bank_name?: string;
  cheque_number?: string;
  transaction_id?: string;

  // Payer
  payer_name?: string;
  payer_reference?: string;

  // Confirmation
  confirmed_date?: string;

  // Reconciliation
  is_reconciled: boolean;
  reconciled_by?: string;
  reconciled_at?: string;
  reconciliation_reference?: string;

  // Allocations
  allocations?: PaymentAllocation[];

  // State flags
  is_pending?: boolean;
  is_received?: boolean;
  is_confirmed?: boolean;
  is_bounced?: boolean;
  is_reversed?: boolean;
  is_refunded?: boolean;
  is_valid?: boolean;
  is_fully_allocated?: boolean;
  has_unallocated?: boolean;

  // Actions
  can_be_allocated?: boolean;
  can_be_reversed?: boolean;
  can_be_reconciled?: boolean;

  // Metadata
  notes?: string;
  received_by?: string;
  created_by?: string;
  created_at?: string;
  updated_at?: string;
}

export interface PaymentAllocation {
  id: string;
  payment_id: string;
  invoice_id: string;
  amount: number;
  allocation_date?: string;
  allocated_by?: string;
  notes?: string;
  payment?: Payment;
  invoice?: Invoice;
}

export interface PaymentFilters {
  search?: string;
  status?: string;
  payment_method?: string;
  policy_id?: string;
  group_id?: string;
  unallocated?: boolean;
  unreconciled?: boolean;
  reconciled?: boolean;
  allocated?: boolean;
  payment_date_from?: string;
  payment_date_to?: string;
  page?: number;
  per_page?: number;
  sort_by?: string;
  sort_order?: 'asc' | 'desc';
}

export interface RecordPaymentPayload {
  policy_id?: string;
  group_id?: string;
  invoice_id?: string;
  amount: number;
  payment_method: string;
  payment_date?: string;
  payment_reference?: string;
  bank_name?: string;
  cheque_number?: string;
  transaction_id?: string;
  payer_name?: string;
  payer_reference?: string;
  notes?: string;
  auto_allocate?: boolean;
  allocate_amount?: number;
}

export interface AllocatePaymentPayload {
  invoice_id: string;
  amount: number;
}

// ============================================================================
// BILLING - STATISTICS
// ============================================================================

export interface BillingStats {
  invoices: {
    summary: {
      total_invoices: number;
      total_invoiced: number;
      total_collected: number;
      total_outstanding: number;
      draft_count: number;
      sent_count: number;
      partially_paid_count: number;
      paid_count: number;
      overdue_count: number;
      overdue_amount: number;
    };
    collection_rate: number;
  };
  payments: {
    summary: {
      total_payments: number;
      total_received: number;
      total_allocated: number;
      total_unallocated: number;
      pending_count: number;
      received_count: number;
      confirmed_count: number;
      bounced_count: number;
      reversed_count: number;
      reconciled_count: number;
      bounced_amount: number;
    };
    by_method: Record<string, { count: number; total: number }>;
    allocation_rate: number;
  };
}

export interface PolicyBillingSummary {
  policy_id: string;
  policy_number: string;
  outstanding_balance: number;
  overdue_amount: number;
  total_invoiced: number;
  total_paid: number;
  standing: {
    in_good_standing: boolean;
    should_suspend: boolean;
    should_lapse: boolean;
    max_overdue_days: number;
    total_overdue_amount: number;
    overdue_invoices_count: number;
  };
}

export interface ActionRequiredInvoices {
  overdue: Invoice[];
  due_soon: Invoice[];
}
