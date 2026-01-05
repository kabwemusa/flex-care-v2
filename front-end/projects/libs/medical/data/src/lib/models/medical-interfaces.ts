// libs/medical/data/src/lib/models/medical.models.ts

// ============================================================================
// API Response
// ============================================================================
export interface ApiResponse<T> {
  status: 'success' | 'error';
  message: string;
  data: T;
  errors?: Record<string, string[]>;
}

export interface PaginatedResponse<T> {
  status: 'success' | 'error';
  message: string;
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

// ============================================================================
// SCHEME
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
// PLAN
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
export interface BenefitCategory {
  id: string;
  code: string;
  name: string;
  description?: string;
  icon?: string;
  color?: string;
  sort_order: number;
  is_active: boolean;
  benefits_count?: number;
  benefits?: Benefit[];
}

export interface Benefit {
  id: string;
  category_id: string;
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
  category?: BenefitCategory;
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
  effective_from?: string;
  effective_to?: string;
  is_active: boolean;
  is_effective?: boolean;
  sort_order: number;
  addon_benefits_count?: number;
  plan_addons_count?: number;
  addon_benefits?: AddonBenefit[];
  rates?: AddonRate[];
}

export interface AddonBenefit {
  id: string;
  addon_id: string;
  benefit_id: string;
  limit_type?: string;
  limit_frequency?: string;
  limit_basis?: string;
  limit_amount?: number;
  limit_count?: number;
  limit_days?: number;
  waiting_period_days?: number;
  display_value?: string;
  benefit?: Benefit;
}

export interface AddonRate {
  id: string;
  addon_id: string;
  plan_id?: string;
  pricing_type: string;
  pricing_type_label?: string;
  amount?: number;
  percentage?: number;
  percentage_basis?: string;
  effective_from: string;
  effective_to?: string;
  is_active: boolean;
  is_effective?: boolean;
  is_global?: boolean;
  is_plan_specific?: boolean;
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
// DROPDOWN OPTIONS
// ============================================================================
// export interface DropdownOption {
//   id: string;
//   code?: string;
//   name: string;
//   [key: string]: unknown;
// }

// libs/medical/data/src/lib/models/policy-admin.models.ts
// Policy Administration Models - Aligned with Backend Resources

// =============================================================================
// CORPORATE GROUPS
// =============================================================================

// export interface CorporateGroup {
//   id: string;
//   code: string;
//   name: string;
//   trading_name?: string;
//   registration_number?: string;
//   tax_number?: string;

//   // Industry & Size
//   industry?: string;
//   industry_label?: string;
//   company_size?: string;
//   company_size_label?: string;
//   employee_count?: number;

//   // Contact
//   email?: string;
//   phone?: string;
//   website?: string;

//   // Address
//   physical_address?: string;
//   city?: string;
//   province?: string;
//   country: string;
//   postal_code?: string;

//   // Billing
//   billing_email?: string;
//   billing_address?: string;
//   payment_terms: string;
//   payment_terms_label?: string;
//   preferred_payment_method?: string;

//   // Broker
//   broker_id?: string;
//   broker_commission_rate?: number;
//   broker_name?: string;

//   // Status
//   status: 'prospect' | 'active' | 'suspended' | 'terminated';
//   status_label?: string;
//   is_active: boolean;

//   // Relations
//   primary_contact?: GroupContact;
//   contacts?: GroupContact[];
//   policies?: Policy[];
//   active_policies?: Policy[];

//   // Counts
//   policies_count?: number;
//   contacts_count?: number;
//   active_members_count?: number;

//   // Meta
//   notes?: string;
//   created_at: string;
//   updated_at: string;
// }

export interface GroupContact {
  id: string;
  group_id: string;
  contact_type: string;
  contact_type_label?: string;
  first_name: string;
  last_name: string;
  full_name?: string;
  job_title?: string;
  email?: string;
  phone?: string;
  mobile?: string;
  has_portal_access: boolean;
  permissions?: string[];
  is_primary: boolean;
  is_active: boolean;
  notes?: string;
  created_at: string;
  updated_at: string;
}

// =============================================================================
// POLICIES
// =============================================================================

// export interface Policy {
//   id: string;
//   policy_number: string;
//   policy_type: 'individual' | 'family' | 'corporate' | 'sme';
//   policy_type_label?: string;

//   // Product
//   scheme_id: string;
//   plan_id: string;
//   rate_card_id?: string;
//   scheme?: Scheme;
//   plan?: Plan;
//   rate_card?: RateCard;

//   // Corporate
//   group_id?: string;
//   group?: CorporateGroup;
//   is_corporate: boolean;
//   policy_holder_name?: string;

//   // Principal (for individual/family)
//   principal_member_id?: string;
//   principal_member?: Member;

//   // Dates
//   inception_date: string;
//   expiry_date: string;
//   renewal_date?: string;
//   policy_term_months: number;
//   is_auto_renew: boolean;
//   days_to_expiry?: number;
//   is_expired?: boolean;
//   is_expiring_soon?: boolean;

//   // Status
//   status:
//     | 'draft'
//     | 'pending_payment'
//     | 'active'
//     | 'suspended'
//     | 'cancelled'
//     | 'expired'
//     | 'renewed';
//   status_label?: string;
//   cancellation_reason?: string;
//   cancellation_date?: string;
//   cancellation_notes?: string;

//   // Premium
//   billing_frequency: string;
//   billing_frequency_label?: string;
//   base_premium: number;
//   total_loadings: number;
//   total_discounts: number;
//   net_premium: number;
//   currency: string;
//   promo_code_id?: string;
//   promo_code?: string;

//   // Source
//   source_channel?: string;
//   sales_agent_id?: string;

//   // Relations
//   members?: Member[];
//   addons?: PolicyAddon[];

//   // Counts
//   members_count?: number;
//   dependents_count?: number;
//   addons_count?: number;

//   // Meta
//   notes?: string;
//   created_at: string;
//   updated_at: string;

//   // Renewal
//   renewed_from_id?: string;
//   renewed_to_id?: string;
// }

export interface PolicyAddon {
  id: string;
  policy_id: string;
  addon_id: string;
  addon_name?: string;
  addon_code?: string;
  addon_premium: number;
  effective_date: string;
  end_date?: string;
  is_active: boolean;
}

// =============================================================================
// MEMBERS
// =============================================================================

// export interface Member {
//   id: string;
//   member_number: string;
//   policy_id: string;
//   policy_number?: string;

//   // Type
//   member_type: 'principal' | 'spouse' | 'child' | 'parent';
//   member_type_label?: string;
//   principal_member_id?: string;
//   relationship?: string;
//   relationship_label?: string;

//   // Personal Info
//   title?: string;
//   first_name: string;
//   middle_name?: string;
//   last_name: string;
//   full_name?: string;
//   gender: 'male' | 'female';
//   date_of_birth: string;
//   age?: number;
//   marital_status?: string;
//   marital_status_label?: string;

//   // ID
//   id_type?: string;
//   id_number?: string;
//   passport_number?: string;

//   // Contact
//   email?: string;
//   phone?: string;
//   mobile?: string;
//   address?: string;
//   city?: string;
//   province?: string;

//   // Employment (for corporate)
//   employee_number?: string;
//   department?: string;
//   job_title?: string;
//   employment_date?: string;

//   // Dates
//   effective_date: string;
//   termination_date?: string;
//   termination_reason?: string;

//   // Status
//   status: 'pending' | 'active' | 'suspended' | 'terminated' | 'deceased';
//   status_label?: string;

//   // Waiting Periods
//   general_waiting_end?: string;
//   maternity_waiting_end?: string;
//   pre_existing_waiting_end?: string;
//   chronic_waiting_end?: string;
//   has_active_waiting?: boolean;

//   // Card
//   card_number?: string;
//   card_status?: 'pending' | 'issued' | 'active' | 'blocked' | 'expired';
//   card_status_label?: string;
//   card_issued_date?: string;
//   card_expiry_date?: string;

//   // Premium
//   premium_amount?: number;
//   premium_loaded?: number;
//   total_premium?: number;

//   // Relations
//   dependents?: Member[];
//   loadings?: MemberLoading[];
//   exclusions?: MemberExclusion[];
//   documents?: MemberDocument[];
//   principal?: Member;

//   // Counts
//   dependents_count?: number;
//   loadings_count?: number;
//   exclusions_count?: number;

//   // Portal
//   has_portal_access: boolean;
//   portal_last_login?: string;

//   // Meta
//   notes?: string;
//   created_at: string;
//   updated_at: string;
// }

// export interface MemberLoading {
//   id: string;
//   member_id: string;
//   loading_rule_id?: string;
//   condition_name: string;
//   icd10_code?: string;
//   loading_type: 'percentage' | 'fixed' | 'exclusion';
//   loading_value?: number;
//   effective_date: string;
//   end_date?: string;
//   duration_type: 'permanent' | 'time_limited' | 'reviewable';
//   review_date?: string;
//   is_active: boolean;
//   approved_by?: string;
//   approved_at?: string;
//   notes?: string;
// }

// export interface MemberExclusion {
//   id: string;
//   member_id: string;
//   exclusion_type: 'pre_existing' | 'specific' | 'benefit' | 'waiting_period';
//   exclusion_type_label?: string;
//   condition_name?: string;
//   benefit_id?: string;
//   benefit_name?: string;
//   icd10_codes?: string[];
//   effective_date: string;
//   end_date?: string;
//   is_permanent: boolean;
//   is_active: boolean;
//   reason?: string;
//   notes?: string;
// }

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

// =============================================================================
// SUPPORTING TYPES (Referenced from Product Config)
// =============================================================================

export interface Scheme {
  id: string;
  name: string;
  slug: string;
  is_active: boolean;
  description?: string;
}

export interface Plan {
  id: string;
  scheme_id: string;
  name: string;
  code: string;
  type: string;
  tier?: string;
  is_active: boolean;
}

export interface RateCard {
  id: string;
  plan_id: string;
  name: string;
  version: string;
  effective_date: string;
  is_active: boolean;
}

// =============================================================================
// API RESPONSE TYPES
// =============================================================================

// export interface PolicyStats {
//   total_policies: number;
//   active_policies: number;
//   pending_renewal: number;
//   expiring_soon: number;
//   total_premium: number;
//   total_members: number;
// }

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

// export interface MemberStats {
//   total_members: number;
//   active_members: number;
//   pending_activation: number;
//   with_loadings: number;
//   with_exclusions: number;
// }

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

// =============================================================================
// FORM PAYLOADS
// =============================================================================

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

export interface ApplicationStats {
  total: number;
  draft: number;
  quoted: number;
  submitted: number;
  underwriting: number; // Matches PHP 'underwriting' alias
  approved: number;
  accepted: number;
  converted: number;
  total_quoted_premium: number;
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

//===================================================================================================
// libs/medical/data/src/lib/models/medical.models.ts

// ============================================================================
// API Response
// ============================================================================
export interface ApiResponse<T> {
  status: 'success' | 'error';
  message: string;
  data: T;
  errors?: Record<string, string[]>;
}

export interface PaginatedResponse<T> {
  status: 'success' | 'error';
  message: string;
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

// ============================================================================
// SCHEME
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
// PLAN
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
export interface BenefitCategory {
  id: string;
  code: string;
  name: string;
  description?: string;
  icon?: string;
  color?: string;
  sort_order: number;
  is_active: boolean;
  benefits_count?: number;
  benefits?: Benefit[];
}

export interface Benefit {
  id: string;
  category_id: string;
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
  category?: BenefitCategory;
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
  effective_from?: string;
  effective_to?: string;
  is_active: boolean;
  is_effective?: boolean;
  sort_order: number;
  addon_benefits_count?: number;
  plan_addons_count?: number;
  addon_benefits?: AddonBenefit[];
  rates?: AddonRate[];
}

export interface AddonBenefit {
  id: string;
  addon_id: string;
  benefit_id: string;
  limit_type?: string;
  limit_frequency?: string;
  limit_basis?: string;
  limit_amount?: number;
  limit_count?: number;
  limit_days?: number;
  waiting_period_days?: number;
  display_value?: string;
  benefit?: Benefit;
}

export interface AddonRate {
  id: string;
  addon_id: string;
  plan_id?: string;
  pricing_type: string;
  pricing_type_label?: string;
  amount?: number;
  percentage?: number;
  percentage_basis?: string;
  effective_from: string;
  effective_to?: string;
  is_active: boolean;
  is_effective?: boolean;
  is_global?: boolean;
  is_plan_specific?: boolean;
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
  city?: string;
  country?: string;
  email?: string;
  phone?: string;
  website?: string;
  status: string;
  status_label?: string;
  is_active?: boolean;
  is_prospect?: boolean;
  is_suspended?: boolean;
  credit_limit?: number;
  payment_terms_days?: number;
  billing_contact_name?: string;
  billing_email?: string;
  notes?: string;
  contacts_count?: number;
  policies_count?: number;
  contacts?: GroupContact[];
  primary_contact?: GroupContact;
  policies?: Policy[];
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
  is_active: boolean;
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
  is_corporate?: boolean;

  // Renewal
  previous_policy_id?: string;
  renewed_to_policy_id?: string;
  renewal_count?: number;

  // Timestamps
  issued_at?: string;
  issued_by?: string;

  // Relations
  principal_member?: Member;
  members?: Member[];
  policy_addons?: PolicyAddon[];
  documents?: PolicyDocument[];

  created_at?: string;
  updated_at?: string;
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
  premium: number;
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
// DROPDOWN OPTIONS
// ============================================================================
export interface DropdownOption {
  id: string;
  code?: string;
  name: string;
  [key: string]: unknown;
}
