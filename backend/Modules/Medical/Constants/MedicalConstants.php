<?php

namespace Modules\Medical\Constants;

final class MedicalConstants
{
    // =========================================================================
    // MARKET SEGMENTS
    // =========================================================================
    public const MARKET_SEGMENT_CORPORATE = 'corporate';
    public const MARKET_SEGMENT_INDIVIDUAL = 'individual';
    public const MARKET_SEGMENT_SME = 'sme';
    public const MARKET_SEGMENT_MICRO = 'micro';
    public const MARKET_SEGMENT_FAMILY = 'family';

    public const MARKET_SEGMENTS = [
        self::MARKET_SEGMENT_CORPORATE => 'Corporate',
        self::MARKET_SEGMENT_INDIVIDUAL => 'Individual',
        self::MARKET_SEGMENT_SME => 'SME',
        self::MARKET_SEGMENT_MICRO => 'Micro Insurance',
        self::MARKET_SEGMENT_FAMILY => 'Family',
    ];

    // =========================================================================
    // PLAN TYPES
    // =========================================================================
    public const PLAN_TYPE_INDIVIDUAL = 'individual';
    public const PLAN_TYPE_FAMILY = 'family';
    public const PLAN_TYPE_GROUP = 'group';

    public const PLAN_TYPES = [
        self::PLAN_TYPE_INDIVIDUAL => 'Individual',
        self::PLAN_TYPE_FAMILY => 'Family',
        self::PLAN_TYPE_GROUP => 'Group/Corporate',
    ];


    // =========================================================================
    // POLICY TYPES
    // =========================================================================
    public const POLICY_TYPE_CORPORATE = 'corporate';
    public const POLICY_TYPE_INDIVIDUAL = 'individual';
    public const POLICY_TYPE_FAMILY = 'family';
    public const POLICY_TYPE_SME = 'sme';

    public const POLICY_TYPES = [
        self::POLICY_TYPE_CORPORATE => 'Corporate',
        self::POLICY_TYPE_INDIVIDUAL => 'Individual',
        self::POLICY_TYPE_FAMILY => 'Family',
        self::POLICY_TYPE_SME => 'SME',
    ];

    // =========================================================================
    // POLICY STATUS
    // =========================================================================
    public const POLICY_STATUS_DRAFT = 'draft';
    public const POLICY_STATUS_PENDING_PAYMENT = 'pending_payment';
    public const POLICY_STATUS_ACTIVE = 'active';
    public const POLICY_STATUS_SUSPENDED = 'suspended';
    public const POLICY_STATUS_LAPSED = 'lapsed';
    public const POLICY_STATUS_CANCELLED = 'cancelled';
    public const POLICY_STATUS_EXPIRED = 'expired';
    public const POLICY_STATUS_RENEWED = 'renewed';

    public const POLICY_STATUSES = [
        self::POLICY_STATUS_DRAFT => 'Draft',
        self::POLICY_STATUS_PENDING_PAYMENT => 'Pending Payment',
        self::POLICY_STATUS_ACTIVE => 'Active',
        self::POLICY_STATUS_SUSPENDED => 'Suspended',
        self::POLICY_STATUS_LAPSED => 'Lapsed',
        self::POLICY_STATUS_CANCELLED => 'Cancelled',
        self::POLICY_STATUS_EXPIRED => 'Expired',
        self::POLICY_STATUS_RENEWED => 'Renewed',
    ];

    // =========================================================================
    // UNDERWRITING STATUS
    // =========================================================================
    // public const UW_STATUS_PENDING = 'pending';
    // public const UW_STATUS_APPROVED = 'approved';
    // public const UW_STATUS_REFERRED = 'referred';
    // public const UW_STATUS_DECLINED = 'declined';

    // public const UW_STATUSES = [
    //     self::UW_STATUS_PENDING => 'Pending Review',
    //     self::UW_STATUS_APPROVED => 'Approved',
    //     self::UW_STATUS_REFERRED => 'Referred',
    //     self::UW_STATUS_DECLINED => 'Declined',
    // ];

    // =========================================================================
    // MEMBER TYPES
    // =========================================================================
    public const MEMBER_TYPE_PRINCIPAL = 'principal';
    public const MEMBER_TYPE_SPOUSE = 'spouse';
    public const MEMBER_TYPE_CHILD = 'child';
    public const MEMBER_TYPE_PARENT = 'parent';

    public const MEMBER_TYPES = [
        self::MEMBER_TYPE_PRINCIPAL => 'Principal',
        self::MEMBER_TYPE_SPOUSE => 'Spouse',
        self::MEMBER_TYPE_CHILD => 'Child',
        self::MEMBER_TYPE_PARENT => 'Parent',
    ];

    // =========================================================================
    // MEMBER STATUS
    // =========================================================================
    public const MEMBER_STATUS_PENDING = 'pending';
    public const MEMBER_STATUS_ACTIVE = 'active';
    public const MEMBER_STATUS_SUSPENDED = 'suspended';
    public const MEMBER_STATUS_TERMINATED = 'terminated';
    public const MEMBER_STATUS_DECEASED = 'deceased';

    public const MEMBER_STATUSES = [
        self::MEMBER_STATUS_PENDING => 'Pending',
        self::MEMBER_STATUS_ACTIVE => 'Active',
        self::MEMBER_STATUS_SUSPENDED => 'Suspended',
        self::MEMBER_STATUS_TERMINATED => 'Terminated',
        self::MEMBER_STATUS_DECEASED => 'Deceased',
    ];


      // =========================================================================
    // CARD STATUS
    // =========================================================================
    public const CARD_STATUS_PENDING = 'pending';
    public const CARD_STATUS_ISSUED = 'issued';
    public const CARD_STATUS_ACTIVE = 'active';
    public const CARD_STATUS_BLOCKED = 'blocked';
    public const CARD_STATUS_EXPIRED = 'expired';

    public const CARD_STATUSES = [
        self::CARD_STATUS_PENDING => 'Pending',
        self::CARD_STATUS_ISSUED => 'Issued',
        self::CARD_STATUS_ACTIVE => 'Active',
        self::CARD_STATUS_BLOCKED => 'Blocked',
        self::CARD_STATUS_EXPIRED => 'Expired',
    ];

    // =========================================================================
    // GROUP/CORPORATE STATUS
    // =========================================================================
    public const GROUP_STATUS_PROSPECT = 'prospect';
    public const GROUP_STATUS_ACTIVE = 'active';
    public const GROUP_STATUS_SUSPENDED = 'suspended';
    public const GROUP_STATUS_TERMINATED = 'terminated';

    public const GROUP_STATUSES = [
        self::GROUP_STATUS_PROSPECT => 'Prospect',
        self::GROUP_STATUS_ACTIVE => 'Active',
        self::GROUP_STATUS_SUSPENDED => 'Suspended',
        self::GROUP_STATUS_TERMINATED => 'Terminated',
    ];

// =========================================================================
    // COMPANY SIZE
    // =========================================================================
    public const COMPANY_SIZE_SME = 'sme';
    public const COMPANY_SIZE_MEDIUM = 'medium';
    public const COMPANY_SIZE_LARGE = 'large';
    public const COMPANY_SIZE_ENTERPRISE = 'enterprise';

    public const COMPANY_SIZES = [
        self::COMPANY_SIZE_SME => 'SME (1-50)',
        self::COMPANY_SIZE_MEDIUM => 'Medium (51-200)',
        self::COMPANY_SIZE_LARGE => 'Large (201-1000)',
        self::COMPANY_SIZE_ENTERPRISE => 'Enterprise (1000+)',
    ];

    // =========================================================================
    // CONTACT TYPES
    // =========================================================================
    public const CONTACT_TYPE_PRIMARY = 'primary';
    public const CONTACT_TYPE_HR = 'hr';
    public const CONTACT_TYPE_FINANCE = 'finance';
    public const CONTACT_TYPE_BROKER = 'broker';
    public const CONTACT_TYPE_ADMINISTRATOR = 'administrator';

    public const CONTACT_TYPES = [
        self::CONTACT_TYPE_PRIMARY => 'Primary Contact',
        self::CONTACT_TYPE_HR => 'HR Contact',
        self::CONTACT_TYPE_FINANCE => 'Finance Contact',
        self::CONTACT_TYPE_BROKER => 'Broker',
        self::CONTACT_TYPE_ADMINISTRATOR => 'Administrator',
    ];

    // =========================================================================
    // PAYMENT TERMS
    // =========================================================================
    public const PAYMENT_TERMS_IMMEDIATE = 'immediate';
    public const PAYMENT_TERMS_15_DAYS = '15_days';
    public const PAYMENT_TERMS_30_DAYS = '30_days';
    public const PAYMENT_TERMS_60_DAYS = '60_days';

    public const PAYMENT_TERMS = [
        self::PAYMENT_TERMS_IMMEDIATE => 'Immediate',
        self::PAYMENT_TERMS_15_DAYS => '15 Days',
        self::PAYMENT_TERMS_30_DAYS => '30 Days',
        self::PAYMENT_TERMS_60_DAYS => '60 Days',
    ];

    // =========================================================================
    // BILLING FREQUENCY
    // =========================================================================
    public const BILLING_MONTHLY = 'monthly';
    public const BILLING_QUARTERLY = 'quarterly';
    public const BILLING_SEMI_ANNUAL = 'semi_annual';
    public const BILLING_ANNUAL = 'annual';

    public const BILLING_FREQUENCIES = [
        self::BILLING_MONTHLY => 'Monthly',
        self::BILLING_QUARTERLY => 'Quarterly',
        self::BILLING_SEMI_ANNUAL => 'Semi-Annual',
        self::BILLING_ANNUAL => 'Annual',
    ];

    // =========================================================================
    // BENEFIT TYPES
    // =========================================================================
    public const BENEFIT_TYPE_CORE = 'core';
    public const BENEFIT_TYPE_OPTIONAL = 'optional';
    public const BENEFIT_TYPE_ADDON = 'addon';

    public const BENEFIT_TYPES = [
        self::BENEFIT_TYPE_CORE => 'Core Benefit',
        self::BENEFIT_TYPE_OPTIONAL => 'Optional Benefit',
        self::BENEFIT_TYPE_ADDON => 'Addon Benefit',
    ];


    public const IN_PATIENT = 'in_patient';
    public const OUT_PATIENT = 'out_patient';
    public const DENTAL = 'dental';
    public const OPTICAL = 'optical';
    public const MATERNITY = 'maternity';
    public const CHRONIC = 'chronic';
    public const WELLNESS = 'wellness';
    public const EMERGENCY = 'emergency';

    public const CATEGORIES = [
        self::IN_PATIENT => 'In-Patient',
        self::OUT_PATIENT => 'Out-Patient',
        self::DENTAL => 'Dental',
        self::OPTICAL => 'Optical',
        self::MATERNITY => 'Maternity',
        self::CHRONIC => 'Chronic',
        self::WELLNESS => 'Wellness',
        self::EMERGENCY => 'Emergency',
    ];

    // =========================================================================
    // LIMIT TYPES
    // // =========================================================================
    // public const LIMIT_TYPE_AMOUNT = 'amount';
    // public const LIMIT_TYPE_VISITS = 'visits';
    // public const LIMIT_TYPE_DAYS = 'days';
    // public const LIMIT_TYPE_UNLIMITED = 'unlimited';
    // public const LIMIT_TYPE_COMBINED = 'combined';

    // public const LIMIT_TYPES = [
    //     self::LIMIT_TYPE_AMOUNT => 'Amount (Currency)',
    //     self::LIMIT_TYPE_VISITS => 'Visits (Count)',
    //     self::LIMIT_TYPE_DAYS => 'Days (Duration)',
    //     self::LIMIT_TYPE_UNLIMITED => 'Unlimited',
    //     self::LIMIT_TYPE_COMBINED => 'Combined',
    // ];

    public const UNLIMITED = 'unlimited';
    public const MONETARY = 'monetary';
    public const COUNT = 'count';
    public const DAYS = 'days';
    public const COMBINED = 'combined';

    public const LIMIT_TYPES = [
        self::UNLIMITED => 'Unlimited',
        self::MONETARY => 'Monetary Limit',
        self::COUNT => 'Visit / Count Limit',
        self::DAYS => 'Days Limit',
        self::COMBINED => 'Combined Limit',
    ];
    // =========================================================================
    // LIMIT FREQUENCIES
    // // =========================================================================

    // public const LIMIT_FREQUENCY_ANNUAL = 'annual';
    // public const LIMIT_FREQUENCY_LIFETIME = 'lifetime';
    // public const LIMIT_FREQUENCY_PER_EVENT = 'per_event';
    // public const LIMIT_FREQUENCY_PER_VISIT = 'per_visit';

    // public const LIMIT_FREQUENCIES = [
    //     self::LIMIT_FREQUENCY_ANNUAL => 'Annual (Per Policy Year)',
    //     self::LIMIT_FREQUENCY_LIFETIME => 'Lifetime',
    //     self::LIMIT_FREQUENCY_PER_EVENT => 'Per Event/Claim',
    //     self::LIMIT_FREQUENCY_PER_VISIT => 'Per Visit',
    // ];

    public const PER_ANNUM = 'per_annum';
    public const PER_CLAIM = 'per_claim';
    public const PER_VISIT = 'per_visit';
    public const PER_CONDITION = 'per_condition';
    public const LIFETIME = 'lifetime';

    public const LIMIT_FREQUENCIES = [
        self::PER_ANNUM => 'Per Year',
        self::PER_CLAIM => 'Per Claim',
        self::PER_VISIT => 'Per Visit',
        self::PER_CONDITION => 'Per Condition',
        self::LIFETIME => 'Lifetime',
    ];

    // =========================================================================
    // LIMIT BASIS
    // =========================================================================
    // public const LIMIT_BASIS_INDIVIDUAL = 'individual';
    // public const LIMIT_BASIS_FAMILY = 'family';
    // public const LIMIT_BASIS_PRINCIPAL_ONLY = 'principal_only';

    // public const LIMIT_BASES = [
    //     self::LIMIT_BASIS_INDIVIDUAL => 'Individual (Per Member)',
    //     self::LIMIT_BASIS_FAMILY => 'Family (Shared/Floater)',
    //     self::LIMIT_BASIS_PRINCIPAL_ONLY => 'Principal Only',
    // ];

    public const PER_MEMBER = 'per_member';
    public const PER_FAMILY = 'per_family';
    public const SHARED_POOL = 'shared_pool';

    public const LIMIT_BASES = [
        self::PER_MEMBER => 'Per Member',
        self::PER_FAMILY => 'Per Family',
        self::SHARED_POOL => 'Shared Pool',
    ];
    // =========================================================================
    // WAITING PERIOD TYPES
    // =========================================================================
    public const WAITING_TYPE_GENERAL = 'general';
    public const WAITING_TYPE_MATERNITY = 'maternity';
    public const WAITING_TYPE_PRE_EXISTING = 'pre_existing';
    public const WAITING_TYPE_CHRONIC = 'chronic';
    public const WAITING_TYPE_NONE = 'none';

    public const WAITING_TYPES = [
        self::WAITING_TYPE_GENERAL => 'General Waiting',
        self::WAITING_TYPE_MATERNITY => 'Maternity Waiting',
        self::WAITING_TYPE_PRE_EXISTING => 'Pre-existing Condition',
        self::WAITING_TYPE_CHRONIC => 'Chronic Condition',
        self::WAITING_TYPE_NONE => 'No Waiting Period',
    ];

    // =========================================================================
    // EXCLUSION TYPES
    // =========================================================================
    public const EXCLUSION_TYPE_ABSOLUTE = 'absolute';
    public const EXCLUSION_TYPE_CONDITIONAL = 'conditional';
    public const EXCLUSION_TYPE_TIME_LIMITED = 'time_limited';
    public const EXCLUSION_TYPE_PRE_EXISTING = 'pre_existing';

    public const EXCLUSION_TYPES = [
        self::EXCLUSION_TYPE_ABSOLUTE => 'Absolute (Never Covered)',
        self::EXCLUSION_TYPE_CONDITIONAL => 'Conditional',
        self::EXCLUSION_TYPE_TIME_LIMITED => 'Time Limited',
        self::EXCLUSION_TYPE_PRE_EXISTING => 'Pre-existing Related',
    ];

    // =========================================================================
    // NETWORK TYPES
    // =========================================================================
    public const NETWORK_TYPE_OPEN = 'open';
    public const NETWORK_TYPE_CLOSED = 'closed';
    public const NETWORK_TYPE_HYBRID = 'hybrid';

    public const NETWORK_TYPES = [
        self::NETWORK_TYPE_OPEN => 'Open Network',
        self::NETWORK_TYPE_CLOSED => 'Closed Network',
        self::NETWORK_TYPE_HYBRID => 'Hybrid Network',
    ];

    // =========================================================================
    // PREMIUM FREQUENCIES
    // =========================================================================
    public const PREMIUM_FREQUENCY_MONTHLY = 'monthly';
    public const PREMIUM_FREQUENCY_QUARTERLY = 'quarterly';
    public const PREMIUM_FREQUENCY_SEMI_ANNUAL = 'semi_annual';
    public const PREMIUM_FREQUENCY_ANNUAL = 'annual';

    public const PREMIUM_FREQUENCIES = [
        self::PREMIUM_FREQUENCY_MONTHLY => 'Monthly',
        self::PREMIUM_FREQUENCY_QUARTERLY => 'Quarterly',
        self::PREMIUM_FREQUENCY_SEMI_ANNUAL => 'Semi-Annual',
        self::PREMIUM_FREQUENCY_ANNUAL => 'Annual',
    ];

    // =========================================================================
    // PREMIUM BASIS
    // =========================================================================
    public const PREMIUM_BASIS_PER_MEMBER = 'per_member';
    public const PREMIUM_BASIS_PER_FAMILY = 'per_family';
    public const PREMIUM_BASIS_TIERED = 'tiered';

    public const PREMIUM_BASES = [
        self::PREMIUM_BASIS_PER_MEMBER => 'Per Member',
        self::PREMIUM_BASIS_PER_FAMILY => 'Per Family (Flat)',
        self::PREMIUM_BASIS_TIERED => 'Tiered (Family Size)',
    ];

    // =========================================================================
    // ADDON TYPES
    // =========================================================================
    public const ADDON_TYPE_OPTIONAL = 'optional';
    public const ADDON_TYPE_MANDATORY = 'mandatory';
    public const ADDON_TYPE_CONDITIONAL = 'conditional';

    public const ADDON_TYPES = [
        self::ADDON_TYPE_OPTIONAL => 'Optional',
        self::ADDON_TYPE_MANDATORY => 'Mandatory',
        self::ADDON_TYPE_CONDITIONAL => 'Conditional',
    ];

    // =========================================================================
    // ADDON AVAILABILITY
    // =========================================================================
    public const ADDON_AVAILABILITY_OPTIONAL = 'optional';
    public const ADDON_AVAILABILITY_MANDATORY = 'mandatory';
    public const ADDON_AVAILABILITY_INCLUDED = 'included';
    public const ADDON_AVAILABILITY_CONDITIONAL = 'conditional';

    public const ADDON_AVAILABILITIES = [
        self::ADDON_AVAILABILITY_OPTIONAL => 'Optional',
        self::ADDON_AVAILABILITY_MANDATORY => 'Mandatory',
        self::ADDON_AVAILABILITY_INCLUDED => 'Included Free',
        self::ADDON_AVAILABILITY_CONDITIONAL => 'Conditional',
    ];

    // =========================================================================
    // ADDON PRICING TYPES
    // =========================================================================
    public const ADDON_PRICING_FIXED = 'fixed';
    public const ADDON_PRICING_PER_MEMBER = 'per_member';
    public const ADDON_PRICING_PERCENTAGE = 'percentage';
    public const ADDON_PRICING_AGE_RATED = 'age_rated';

    public const ADDON_PRICING_TYPES = [
        self::ADDON_PRICING_FIXED => 'Fixed Amount',
        self::ADDON_PRICING_PER_MEMBER => 'Per Member',
        self::ADDON_PRICING_PERCENTAGE => 'Percentage of Premium',
        self::ADDON_PRICING_AGE_RATED => 'Age Rated',
    ];

    // =========================================================================
    // DISCOUNT/LOADING ADJUSTMENT TYPES
    // =========================================================================
    public const ADJUSTMENT_TYPE_DISCOUNT = 'discount';
    public const ADJUSTMENT_TYPE_LOADING = 'loading';

    public const ADJUSTMENT_TYPES = [
        self::ADJUSTMENT_TYPE_DISCOUNT => 'Discount',
        self::ADJUSTMENT_TYPE_LOADING => 'Loading',
    ];

    // =========================================================================
    // DISCOUNT VALUE TYPES
    // =========================================================================
    public const VALUE_TYPE_PERCENTAGE = 'percentage';
    public const VALUE_TYPE_FIXED = 'fixed';

    public const VALUE_TYPES = [
        self::VALUE_TYPE_PERCENTAGE => 'Percentage',
        self::VALUE_TYPE_FIXED => 'Fixed Amount',
    ];

    // =========================================================================
    // DISCOUNT APPLICATION METHODS
    // =========================================================================
    public const APPLICATION_METHOD_AUTOMATIC = 'automatic';
    public const APPLICATION_METHOD_MANUAL = 'manual';
    public const APPLICATION_METHOD_PROMO_CODE = 'promo_code';

    public const APPLICATION_METHODS = [
        self::APPLICATION_METHOD_AUTOMATIC => 'Automatic',
        self::APPLICATION_METHOD_MANUAL => 'Manual',
        self::APPLICATION_METHOD_PROMO_CODE => 'Promo Code',
    ];

    // =========================================================================
    // DISCOUNT APPLIES TO
    // =========================================================================
    public const APPLIES_TO_BASE = 'base';
    public const APPLIES_TO_TOTAL = 'total';
    public const APPLIES_TO_ADDON = 'addon';

    public const APPLIES_TO_OPTIONS = [
        self::APPLIES_TO_BASE => 'Base Premium Only',
        self::APPLIES_TO_TOTAL => 'Total Premium',
        self::APPLIES_TO_ADDON => 'Addon Only',
    ];

    // =========================================================================
    // LOADING TYPES
    // =========================================================================
    public const LOADING_TYPE_PERCENTAGE = 'percentage';
    public const LOADING_TYPE_FIXED = 'fixed';
    public const LOADING_TYPE_EXCLUSION = 'exclusion';

    public const LOADING_TYPES = [
        self::LOADING_TYPE_PERCENTAGE => 'Percentage Loading',
        self::LOADING_TYPE_FIXED => 'Fixed Amount Loading',
        self::LOADING_TYPE_EXCLUSION => 'Benefit Exclusion',
    ];

    // =========================================================================
    // LOADING DURATION TYPES
    // =========================================================================
    public const LOADING_DURATION_PERMANENT = 'permanent';
    public const LOADING_DURATION_TIME_LIMITED = 'time_limited';
    public const LOADING_DURATION_REVIEWABLE = 'reviewable';

    public const LOADING_DURATIONS = [
        self::LOADING_DURATION_PERMANENT => 'Permanent',
        self::LOADING_DURATION_TIME_LIMITED => 'Time Limited',
        self::LOADING_DURATION_REVIEWABLE => 'Reviewable',
    ];

    // =========================================================================
    // CONDITION CATEGORIES (for medical loadings)
    // =========================================================================
    public const CONDITION_CATEGORY_CHRONIC = 'chronic';
    public const CONDITION_CATEGORY_PRE_EXISTING = 'pre_existing';
    public const CONDITION_CATEGORY_LIFESTYLE = 'lifestyle';

    public const CONDITION_CATEGORIES = [
        self::CONDITION_CATEGORY_CHRONIC => 'Chronic Condition',
        self::CONDITION_CATEGORY_PRE_EXISTING => 'Pre-existing Condition',
        self::CONDITION_CATEGORY_LIFESTYLE => 'Lifestyle Related',
    ];

    // =========================================================================
    // CO-PAY TYPES
    // =========================================================================
    // public const COPAY_TYPE_NONE = 'none';
    // public const COPAY_TYPE_FIXED = 'fixed';
    // public const COPAY_TYPE_PERCENTAGE = 'percentage';

    // public const COPAY_TYPES = [
    //     self::COPAY_TYPE_NONE => 'No Co-pay',
    //     self::COPAY_TYPE_FIXED => 'Fixed Amount',
    //     self::COPAY_TYPE_PERCENTAGE => 'Percentage',
    // ];

    // =========================================================================
    // CODE PREFIXES (for auto-generation)
    // =========================================================================
    public const PREFIX_SCHEME = 'SCH';
    public const PREFIX_PLAN = 'PLN';
    public const PREFIX_BENEFIT_CATEGORY = 'CAT';
    public const PREFIX_BENEFIT = 'BEN';
    public const PREFIX_RATE_CARD = 'RC';
    public const PREFIX_ADDON = 'ADD';
    public const PREFIX_DISCOUNT = 'DISC';
    public const PREFIX_PROMO = 'PROMO';
    public const PREFIX_LOADING = 'LOAD';
    public const PREFIX_EXCLUSION = 'EXC';

     // DOCUMENT TYPES
    // =========================================================================
    public const DOC_TYPE_CERTIFICATE = 'certificate';
    public const DOC_TYPE_SCHEDULE = 'schedule';
    public const DOC_TYPE_ENDORSEMENT = 'endorsement';
    public const DOC_TYPE_TERMS = 'terms';
    public const DOC_TYPE_INVOICE = 'invoice';
    public const DOC_TYPE_RECEIPT = 'receipt';
    public const DOC_TYPE_CLAIM_FORM = 'claim_form';
    public const DOC_TYPE_ID_COPY = 'id_copy';
    public const DOC_TYPE_PASSPORT = 'passport';
    public const DOC_TYPE_BIRTH_CERT = 'birth_certificate';
    public const DOC_TYPE_MARRIAGE_CERT = 'marriage_certificate';
    public const DOC_TYPE_MEDICAL_REPORT = 'medical_report';
    public const DOC_TYPE_DECLARATION = 'declaration_form';
    public const DOC_TYPE_PHOTO = 'photo';

    public const POLICY_DOCUMENT_TYPES = [
        self::DOC_TYPE_CERTIFICATE => 'Policy Certificate',
        self::DOC_TYPE_SCHEDULE => 'Policy Schedule',
        self::DOC_TYPE_ENDORSEMENT => 'Endorsement',
        self::DOC_TYPE_TERMS => 'Terms & Conditions',
        self::DOC_TYPE_INVOICE => 'Invoice',
        self::DOC_TYPE_RECEIPT => 'Receipt',
        self::DOC_TYPE_CLAIM_FORM => 'Claim Form',
    ];

    public const MEMBER_DOCUMENT_TYPES = [
        self::DOC_TYPE_ID_COPY => 'ID Copy',
        self::DOC_TYPE_PASSPORT => 'Passport',
        self::DOC_TYPE_BIRTH_CERT => 'Birth Certificate',
        self::DOC_TYPE_MARRIAGE_CERT => 'Marriage Certificate',
        self::DOC_TYPE_MEDICAL_REPORT => 'Medical Report',
        self::DOC_TYPE_DECLARATION => 'Declaration Form',
        self::DOC_TYPE_PHOTO => 'Photo',
    ];

    // =========================================================================
    // DEFAULT VALUES
    // =========================================================================
    public const DEFAULT_CURRENCY = 'ZMW';
    public const DEFAULT_CHILD_AGE_LIMIT = 21;
    public const DEFAULT_CHILD_STUDENT_AGE_LIMIT = 25;
    public const DEFAULT_PARENT_AGE_LIMIT = 70;
    public const DEFAULT_MAX_DEPENDENTS = 5;
    public const DEFAULT_GENERAL_WAITING_DAYS = 30;
    public const DEFAULT_MATERNITY_WAITING_DAYS = 300;
    public const DEFAULT_PRE_EXISTING_WAITING_DAYS = 365;
    public const DEFAULT_CHRONIC_WAITING_DAYS = 365;
    public const DEFAULT_POLICY_TERM_MONTHS = 12;
      // Policy Administration Prefixes
      public const PREFIX_GROUP = 'GRP';
      public const PREFIX_POLICY = 'POL';
      public const PREFIX_MEMBER = 'MEM';
  
      // =========================================================================
      // DEFAULT VALUES
      // =========================================================================

      

     // =========================================================================
    // APPLICATION TYPES
    // =========================================================================
    public const APPLICATION_TYPE_NEW = 'new_business';
    public const APPLICATION_TYPE_RENEWAL = 'renewal';
    public const APPLICATION_TYPE_ADDITION = 'addition';

    public const APPLICATION_TYPES = [
        self::APPLICATION_TYPE_NEW => 'New Business',
        self::APPLICATION_TYPE_RENEWAL => 'Renewal',
        self::APPLICATION_TYPE_ADDITION => 'Addition',
    ];

    // =========================================================================
    // APPLICATION STATUS
    // =========================================================================
    public const APPLICATION_STATUS_DRAFT = 'draft';
    public const APPLICATION_STATUS_QUOTED = 'quoted';
    public const APPLICATION_STATUS_SUBMITTED = 'submitted';
    public const APPLICATION_STATUS_UNDERWRITING = 'underwriting';
    public const APPLICATION_STATUS_APPROVED = 'approved';
    public const APPLICATION_STATUS_DECLINED = 'declined';
    public const APPLICATION_STATUS_REFERRED = 'referred';
    public const APPLICATION_STATUS_ACCEPTED = 'accepted';
    public const APPLICATION_STATUS_CONVERTED = 'converted';
    public const APPLICATION_STATUS_EXPIRED = 'expired';
    public const APPLICATION_STATUS_CANCELLED = 'cancelled';
    public const APPLICATION_STATUS_ON_HOLD = 'on_hold';

    public const APPLICATION_STATUSES = [
        self::APPLICATION_STATUS_DRAFT => 'Draft',
        self::APPLICATION_STATUS_QUOTED => 'Quoted',
        self::APPLICATION_STATUS_SUBMITTED => 'Submitted',
        self::APPLICATION_STATUS_UNDERWRITING => 'Underwriting',
        self::APPLICATION_STATUS_APPROVED => 'Approved',
        self::APPLICATION_STATUS_DECLINED => 'Declined',
        self::APPLICATION_STATUS_REFERRED => 'Referred',
        self::APPLICATION_STATUS_ACCEPTED => 'Accepted',
        self::APPLICATION_STATUS_CONVERTED => 'Converted to Policy',
        self::APPLICATION_STATUS_EXPIRED => 'Expired',
        self::APPLICATION_STATUS_CANCELLED => 'Cancelled',
        self::APPLICATION_STATUS_ON_HOLD => 'On Hold',
    ];

    // =========================================================================
    // UNDERWRITING STATUS
    // =========================================================================
    public const UW_STATUS_PENDING = 'pending';
    public const UW_STATUS_IN_PROGRESS = 'in_progress';
    public const UW_STATUS_APPROVED = 'approved';
    public const UW_STATUS_REFERRED = 'referred';
    public const UW_STATUS_DECLINED = 'declined';
    public const UW_STATUS_TERMS = 'terms'; // Approved with terms (loadings/exclusions)

    public const UW_STATUSES = [
        self::UW_STATUS_PENDING => 'Pending Review',
        self::UW_STATUS_IN_PROGRESS => 'In Progress',
        self::UW_STATUS_APPROVED => 'Approved',
        self::UW_STATUS_REFERRED => 'Referred',
        self::UW_STATUS_DECLINED => 'Declined',
        self::UW_STATUS_TERMS => 'Approved with Terms',
    ];
    // =========================================================================
    // CO-PAY TYPES
    // =========================================================================
    public const COPAY_TYPE_NONE = 'none';
    public const COPAY_TYPE_FIXED = 'fixed';
    public const COPAY_TYPE_PERCENTAGE = 'percentage';

    public const COPAY_TYPES = [
        self::COPAY_TYPE_NONE => 'No Co-pay',
        self::COPAY_TYPE_FIXED => 'Fixed Amount',
        self::COPAY_TYPE_PERCENTAGE => 'Percentage',
    ];

    // =========================================================================
    // CODE PREFIXES (for auto-generation)
    // =========================================================================
   
    
    // Policy Administration Prefixes
    public const PREFIX_APPLICATION = 'APP-';
   

    // =========================================================================
    // RELATIONSHIPS (for dependents)
    // =========================================================================
    public const RELATIONSHIP_WIFE = 'wife';
    public const RELATIONSHIP_HUSBAND = 'husband';
    public const RELATIONSHIP_SON = 'son';
    public const RELATIONSHIP_DAUGHTER = 'daughter';
    public const RELATIONSHIP_FATHER = 'father';
    public const RELATIONSHIP_MOTHER = 'mother';
    public const RELATIONSHIP_PARTNER = 'partner';

    public const RELATIONSHIPS = [
        self::RELATIONSHIP_WIFE => 'Wife',
        self::RELATIONSHIP_HUSBAND => 'Husband',
        self::RELATIONSHIP_SON => 'Son',
        self::RELATIONSHIP_DAUGHTER => 'Daughter',
        self::RELATIONSHIP_FATHER => 'Father',
        self::RELATIONSHIP_MOTHER => 'Mother',
        self::RELATIONSHIP_PARTNER => 'Partner',
    ];

    // =========================================================================
    // APPLICATION SOURCES
    // =========================================================================
    public const SOURCE_ONLINE = 'online';
    public const SOURCE_WALK_IN = 'walk_in';
    public const SOURCE_AGENT = 'agent';
    public const SOURCE_BROKER = 'broker';
    public const SOURCE_REFERRAL = 'referral';
    public const SOURCE_RENEWAL = 'renewal';

    public const APPLICATION_SOURCES = [
        self::SOURCE_ONLINE => 'Online',
        self::SOURCE_WALK_IN => 'Walk-in',
        self::SOURCE_AGENT => 'Agent',
        self::SOURCE_BROKER => 'Broker',
        self::SOURCE_REFERRAL => 'Referral',
        self::SOURCE_RENEWAL => 'Renewal',
    ];

    
     
}