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

    // =========================================================================
    // ENDORSEMENT TYPES
    // =========================================================================
    public const ENDORSEMENT_TYPE_ADD_MEMBER = 'add_member';
    public const ENDORSEMENT_TYPE_REMOVE_MEMBER = 'remove_member';
    public const ENDORSEMENT_TYPE_UPGRADE_PLAN = 'upgrade_plan';
    public const ENDORSEMENT_TYPE_DOWNGRADE_PLAN = 'downgrade_plan';
    public const ENDORSEMENT_TYPE_ADD_ADDON = 'add_addon';
    public const ENDORSEMENT_TYPE_REMOVE_ADDON = 'remove_addon';
    public const ENDORSEMENT_TYPE_CHANGE_DETAILS = 'change_details';
    public const ENDORSEMENT_TYPE_CORRECTION = 'correction';
    public const ENDORSEMENT_TYPE_CANCELLATION = 'cancellation';
    public const ENDORSEMENT_TYPE_REINSTATEMENT = 'reinstatement';

    public const ENDORSEMENT_TYPES = [
        self::ENDORSEMENT_TYPE_ADD_MEMBER => 'Add Member',
        self::ENDORSEMENT_TYPE_REMOVE_MEMBER => 'Remove Member',
        self::ENDORSEMENT_TYPE_UPGRADE_PLAN => 'Upgrade Plan',
        self::ENDORSEMENT_TYPE_DOWNGRADE_PLAN => 'Downgrade Plan',
        self::ENDORSEMENT_TYPE_ADD_ADDON => 'Add Add-on',
        self::ENDORSEMENT_TYPE_REMOVE_ADDON => 'Remove Add-on',
        self::ENDORSEMENT_TYPE_CHANGE_DETAILS => 'Change Details',
        self::ENDORSEMENT_TYPE_CORRECTION => 'Correction',
        self::ENDORSEMENT_TYPE_CANCELLATION => 'Cancellation',
        self::ENDORSEMENT_TYPE_REINSTATEMENT => 'Reinstatement',
    ];

    // =========================================================================
    // ENDORSEMENT STATUS
    // =========================================================================
    public const ENDORSEMENT_STATUS_PENDING = 'pending';
    public const ENDORSEMENT_STATUS_APPROVED = 'approved';
    public const ENDORSEMENT_STATUS_REJECTED = 'rejected';
    public const ENDORSEMENT_STATUS_PROCESSED = 'processed';
    public const ENDORSEMENT_STATUS_CANCELLED = 'cancelled';

    public const ENDORSEMENT_STATUSES = [
        self::ENDORSEMENT_STATUS_PENDING => 'Pending',
        self::ENDORSEMENT_STATUS_APPROVED => 'Approved',
        self::ENDORSEMENT_STATUS_REJECTED => 'Rejected',
        self::ENDORSEMENT_STATUS_PROCESSED => 'Processed',
        self::ENDORSEMENT_STATUS_CANCELLED => 'Cancelled',
    ];

    // =========================================================================
    // ENDORSEMENT PREFIX
    // =========================================================================
    public const PREFIX_ENDORSEMENT = 'END';

    // =========================================================================
    // CLAIM TYPES
    // =========================================================================
    public const CLAIM_TYPE_IN_PATIENT = 'in_patient';
    public const CLAIM_TYPE_OUT_PATIENT = 'out_patient';
    public const CLAIM_TYPE_DENTAL = 'dental';
    public const CLAIM_TYPE_OPTICAL = 'optical';
    public const CLAIM_TYPE_MATERNITY = 'maternity';
    public const CLAIM_TYPE_CHRONIC = 'chronic';
    public const CLAIM_TYPE_WELLNESS = 'wellness';
    public const CLAIM_TYPE_EMERGENCY = 'emergency';

    public const CLAIM_TYPES = [
        self::CLAIM_TYPE_IN_PATIENT => 'In-Patient',
        self::CLAIM_TYPE_OUT_PATIENT => 'Out-Patient',
        self::CLAIM_TYPE_DENTAL => 'Dental',
        self::CLAIM_TYPE_OPTICAL => 'Optical',
        self::CLAIM_TYPE_MATERNITY => 'Maternity',
        self::CLAIM_TYPE_CHRONIC => 'Chronic',
        self::CLAIM_TYPE_WELLNESS => 'Wellness',
        self::CLAIM_TYPE_EMERGENCY => 'Emergency',
    ];

    // =========================================================================
    // CLAIM STATUS
    // =========================================================================
    public const CLAIM_STATUS_SUBMITTED = 'submitted';
    public const CLAIM_STATUS_PENDING_DOCUMENTS = 'pending_documents';
    public const CLAIM_STATUS_IN_REVIEW = 'in_review';
    public const CLAIM_STATUS_PENDING_APPROVAL = 'pending_approval';
    public const CLAIM_STATUS_APPROVED = 'approved';
    public const CLAIM_STATUS_PARTIALLY_APPROVED = 'partially_approved';
    public const CLAIM_STATUS_REJECTED = 'rejected';
    public const CLAIM_STATUS_PAID = 'paid';
    public const CLAIM_STATUS_CLOSED = 'closed';

    public const CLAIM_STATUSES = [
        self::CLAIM_STATUS_SUBMITTED => 'Submitted',
        self::CLAIM_STATUS_PENDING_DOCUMENTS => 'Pending Documents',
        self::CLAIM_STATUS_IN_REVIEW => 'In Review',
        self::CLAIM_STATUS_PENDING_APPROVAL => 'Pending Approval',
        self::CLAIM_STATUS_APPROVED => 'Approved',
        self::CLAIM_STATUS_PARTIALLY_APPROVED => 'Partially Approved',
        self::CLAIM_STATUS_REJECTED => 'Rejected',
        self::CLAIM_STATUS_PAID => 'Paid',
        self::CLAIM_STATUS_CLOSED => 'Closed',
    ];

    // =========================================================================
    // CLAIM SUBMISSION TYPES
    // =========================================================================
    public const SUBMISSION_TYPE_PROVIDER = 'provider';
    public const SUBMISSION_TYPE_MEMBER = 'member';
    public const SUBMISSION_TYPE_EMPLOYER = 'employer';

    public const SUBMISSION_TYPES = [
        self::SUBMISSION_TYPE_PROVIDER => 'Provider',
        self::SUBMISSION_TYPE_MEMBER => 'Member',
        self::SUBMISSION_TYPE_EMPLOYER => 'Employer',
    ];

    // =========================================================================
    // CLAIM SUBMISSION CHANNELS
    // =========================================================================
    public const SUBMISSION_CHANNEL_PORTAL = 'portal';
    public const SUBMISSION_CHANNEL_EMAIL = 'email';
    public const SUBMISSION_CHANNEL_PAPER = 'paper';
    public const SUBMISSION_CHANNEL_API = 'api';
    public const SUBMISSION_CHANNEL_MOBILE = 'mobile';

    public const SUBMISSION_CHANNELS = [
        self::SUBMISSION_CHANNEL_PORTAL => 'Portal',
        self::SUBMISSION_CHANNEL_EMAIL => 'Email',
        self::SUBMISSION_CHANNEL_PAPER => 'Paper',
        self::SUBMISSION_CHANNEL_API => 'API',
        self::SUBMISSION_CHANNEL_MOBILE => 'Mobile App',
    ];

    // =========================================================================
    // PROVIDER TYPES
    // =========================================================================
    public const PROVIDER_TYPE_HOSPITAL = 'hospital';
    public const PROVIDER_TYPE_CLINIC = 'clinic';
    public const PROVIDER_TYPE_PHARMACY = 'pharmacy';
    public const PROVIDER_TYPE_LAB = 'lab';
    public const PROVIDER_TYPE_OPTICAL = 'optical';
    public const PROVIDER_TYPE_DENTAL = 'dental';
    public const PROVIDER_TYPE_SPECIALIST = 'specialist';

    public const PROVIDER_TYPES = [
        self::PROVIDER_TYPE_HOSPITAL => 'Hospital',
        self::PROVIDER_TYPE_CLINIC => 'Clinic',
        self::PROVIDER_TYPE_PHARMACY => 'Pharmacy',
        self::PROVIDER_TYPE_LAB => 'Laboratory',
        self::PROVIDER_TYPE_OPTICAL => 'Optical Center',
        self::PROVIDER_TYPE_DENTAL => 'Dental Clinic',
        self::PROVIDER_TYPE_SPECIALIST => 'Specialist',
    ];

    // =========================================================================
    // CLAIM LINE STATUS
    // =========================================================================
    public const CLAIM_LINE_STATUS_PENDING = 'pending';
    public const CLAIM_LINE_STATUS_APPROVED = 'approved';
    public const CLAIM_LINE_STATUS_PARTIALLY_APPROVED = 'partially_approved';
    public const CLAIM_LINE_STATUS_REJECTED = 'rejected';

    public const CLAIM_LINE_STATUSES = [
        self::CLAIM_LINE_STATUS_PENDING => 'Pending',
        self::CLAIM_LINE_STATUS_APPROVED => 'Approved',
        self::CLAIM_LINE_STATUS_PARTIALLY_APPROVED => 'Partially Approved',
        self::CLAIM_LINE_STATUS_REJECTED => 'Rejected',
    ];

    // =========================================================================
    // CLAIM REJECTION REASONS
    // =========================================================================
    public const REJECTION_BENEFIT_EXHAUSTED = 'benefit_exhausted';
    public const REJECTION_NOT_COVERED = 'not_covered';
    public const REJECTION_WAITING_PERIOD = 'waiting_period';
    public const REJECTION_PRE_EXISTING = 'pre_existing';
    public const REJECTION_POLICY_INACTIVE = 'policy_inactive';
    public const REJECTION_MEMBER_INACTIVE = 'member_inactive';
    public const REJECTION_DUPLICATE = 'duplicate';
    public const REJECTION_DOCUMENTATION = 'documentation';
    public const REJECTION_FRAUD = 'fraud';
    public const REJECTION_EXCLUSION = 'exclusion';
    public const REJECTION_OTHER = 'other';

    public const CLAIM_REJECTION_REASONS = [
        self::REJECTION_BENEFIT_EXHAUSTED => 'Benefit Exhausted',
        self::REJECTION_NOT_COVERED => 'Not Covered',
        self::REJECTION_WAITING_PERIOD => 'Within Waiting Period',
        self::REJECTION_PRE_EXISTING => 'Pre-existing Condition',
        self::REJECTION_POLICY_INACTIVE => 'Policy Not Active',
        self::REJECTION_MEMBER_INACTIVE => 'Member Not Active',
        self::REJECTION_DUPLICATE => 'Duplicate Claim',
        self::REJECTION_DOCUMENTATION => 'Missing Documentation',
        self::REJECTION_FRAUD => 'Suspected Fraud',
        self::REJECTION_EXCLUSION => 'Excluded Condition',
        self::REJECTION_OTHER => 'Other',
    ];

    // =========================================================================
    // CLAIM DOCUMENT TYPES
    // =========================================================================
    public const CLAIM_DOC_INVOICE = 'invoice';
    public const CLAIM_DOC_RECEIPT = 'receipt';
    public const CLAIM_DOC_PRESCRIPTION = 'prescription';
    public const CLAIM_DOC_MEDICAL_REPORT = 'medical_report';
    public const CLAIM_DOC_LAB_RESULT = 'lab_result';
    public const CLAIM_DOC_REFERRAL = 'referral';
    public const CLAIM_DOC_PREAUTH = 'preauth';
    public const CLAIM_DOC_DISCHARGE = 'discharge_summary';
    public const CLAIM_DOC_ID_COPY = 'id_copy';
    public const CLAIM_DOC_OTHER = 'other';

    public const CLAIM_DOCUMENT_TYPES = [
        self::CLAIM_DOC_INVOICE => 'Invoice',
        self::CLAIM_DOC_RECEIPT => 'Receipt',
        self::CLAIM_DOC_PRESCRIPTION => 'Prescription',
        self::CLAIM_DOC_MEDICAL_REPORT => 'Medical Report',
        self::CLAIM_DOC_LAB_RESULT => 'Lab Result',
        self::CLAIM_DOC_REFERRAL => 'Referral Letter',
        self::CLAIM_DOC_PREAUTH => 'Pre-authorization',
        self::CLAIM_DOC_DISCHARGE => 'Discharge Summary',
        self::CLAIM_DOC_ID_COPY => 'ID Copy',
        self::CLAIM_DOC_OTHER => 'Other',
    ];

    // =========================================================================
    // CLAIM NOTE TYPES
    // =========================================================================
    public const CLAIM_NOTE_COMMENT = 'comment';
    public const CLAIM_NOTE_STATUS_CHANGE = 'status_change';
    public const CLAIM_NOTE_ASSIGNMENT = 'assignment';
    public const CLAIM_NOTE_ESCALATION = 'escalation';
    public const CLAIM_NOTE_QUERY = 'query';
    public const CLAIM_NOTE_RESPONSE = 'response';
    public const CLAIM_NOTE_SYSTEM = 'system';

    public const CLAIM_NOTE_TYPES = [
        self::CLAIM_NOTE_COMMENT => 'Comment',
        self::CLAIM_NOTE_STATUS_CHANGE => 'Status Change',
        self::CLAIM_NOTE_ASSIGNMENT => 'Assignment',
        self::CLAIM_NOTE_ESCALATION => 'Escalation',
        self::CLAIM_NOTE_QUERY => 'Query',
        self::CLAIM_NOTE_RESPONSE => 'Response',
        self::CLAIM_NOTE_SYSTEM => 'System',
    ];

    // =========================================================================
    // PAYMENT METHODS
    // =========================================================================
    public const PAYMENT_METHOD_EFT = 'eft';
    public const PAYMENT_METHOD_CHEQUE = 'cheque';
    public const PAYMENT_METHOD_MOBILE_MONEY = 'mobile_money';
    public const PAYMENT_METHOD_CASH = 'cash';

    public const PAYMENT_METHODS = [
        self::PAYMENT_METHOD_EFT => 'EFT',
        self::PAYMENT_METHOD_CHEQUE => 'Cheque',
        self::PAYMENT_METHOD_MOBILE_MONEY => 'Mobile Money',
        self::PAYMENT_METHOD_CASH => 'Cash',
    ];

    // =========================================================================
    // CLAIM PREFIX
    // =========================================================================
    public const PREFIX_CLAIM = 'CLM';

    // =========================================================================
    // BILLING - INVOICE TYPES
    // =========================================================================
    public const INVOICE_TYPE_PREMIUM = 'premium';
    public const INVOICE_TYPE_ENDORSEMENT = 'endorsement';
    public const INVOICE_TYPE_ADJUSTMENT = 'adjustment';
    public const INVOICE_TYPE_CREDIT_NOTE = 'credit_note';

    public const INVOICE_TYPES = [
        self::INVOICE_TYPE_PREMIUM => 'Premium Invoice',
        self::INVOICE_TYPE_ENDORSEMENT => 'Endorsement Invoice',
        self::INVOICE_TYPE_ADJUSTMENT => 'Adjustment',
        self::INVOICE_TYPE_CREDIT_NOTE => 'Credit Note',
    ];

    // =========================================================================
    // BILLING - INVOICE STATUS
    // =========================================================================
    public const INVOICE_STATUS_DRAFT = 'draft';
    public const INVOICE_STATUS_SENT = 'sent';
    public const INVOICE_STATUS_PARTIALLY_PAID = 'partially_paid';
    public const INVOICE_STATUS_PAID = 'paid';
    public const INVOICE_STATUS_OVERDUE = 'overdue';
    public const INVOICE_STATUS_CANCELLED = 'cancelled';
    public const INVOICE_STATUS_WRITTEN_OFF = 'written_off';

    public const INVOICE_STATUSES = [
        self::INVOICE_STATUS_DRAFT => 'Draft',
        self::INVOICE_STATUS_SENT => 'Sent',
        self::INVOICE_STATUS_PARTIALLY_PAID => 'Partially Paid',
        self::INVOICE_STATUS_PAID => 'Paid',
        self::INVOICE_STATUS_OVERDUE => 'Overdue',
        self::INVOICE_STATUS_CANCELLED => 'Cancelled',
        self::INVOICE_STATUS_WRITTEN_OFF => 'Written Off',
    ];

    // =========================================================================
    // BILLING - INVOICE ITEM TYPES
    // =========================================================================
    public const INVOICE_ITEM_BASE_PREMIUM = 'base_premium';
    public const INVOICE_ITEM_MEMBER_PREMIUM = 'member_premium';
    public const INVOICE_ITEM_ADDON_PREMIUM = 'addon_premium';
    public const INVOICE_ITEM_LOADING = 'loading';
    public const INVOICE_ITEM_DISCOUNT = 'discount';
    public const INVOICE_ITEM_TAX = 'tax';
    public const INVOICE_ITEM_ADJUSTMENT = 'adjustment';
    public const INVOICE_ITEM_PRORATA = 'prorata';

    public const INVOICE_ITEM_TYPES = [
        self::INVOICE_ITEM_BASE_PREMIUM => 'Base Premium',
        self::INVOICE_ITEM_MEMBER_PREMIUM => 'Member Premium',
        self::INVOICE_ITEM_ADDON_PREMIUM => 'Addon Premium',
        self::INVOICE_ITEM_LOADING => 'Loading',
        self::INVOICE_ITEM_DISCOUNT => 'Discount',
        self::INVOICE_ITEM_TAX => 'Tax',
        self::INVOICE_ITEM_ADJUSTMENT => 'Adjustment',
        self::INVOICE_ITEM_PRORATA => 'Pro-rata Adjustment',
    ];

    // =========================================================================
    // BILLING - PAYMENT STATUS
    // =========================================================================
    public const PAYMENT_STATUS_PENDING = 'pending';
    public const PAYMENT_STATUS_RECEIVED = 'received';
    public const PAYMENT_STATUS_CONFIRMED = 'confirmed';
    public const PAYMENT_STATUS_BOUNCED = 'bounced';
    public const PAYMENT_STATUS_REVERSED = 'reversed';
    public const PAYMENT_STATUS_REFUNDED = 'refunded';

    public const PAYMENT_STATUSES = [
        self::PAYMENT_STATUS_PENDING => 'Pending',
        self::PAYMENT_STATUS_RECEIVED => 'Received',
        self::PAYMENT_STATUS_CONFIRMED => 'Confirmed',
        self::PAYMENT_STATUS_BOUNCED => 'Bounced',
        self::PAYMENT_STATUS_REVERSED => 'Reversed',
        self::PAYMENT_STATUS_REFUNDED => 'Refunded',
    ];

    // =========================================================================
    // BILLING - PAYMENT METHODS (Extended)
    // =========================================================================
    public const PAYMENT_METHOD_BANK_TRANSFER = 'bank_transfer';
    public const PAYMENT_METHOD_DIRECT_DEBIT = 'direct_debit';
    public const PAYMENT_METHOD_CARD = 'card';

    public const BILLING_PAYMENT_METHODS = [
        self::PAYMENT_METHOD_BANK_TRANSFER => 'Bank Transfer',
        self::PAYMENT_METHOD_EFT => 'EFT',
        self::PAYMENT_METHOD_CHEQUE => 'Cheque',
        self::PAYMENT_METHOD_MOBILE_MONEY => 'Mobile Money',
        self::PAYMENT_METHOD_CASH => 'Cash',
        self::PAYMENT_METHOD_DIRECT_DEBIT => 'Direct Debit',
        self::PAYMENT_METHOD_CARD => 'Card Payment',
    ];

    // =========================================================================
    // BILLING - SEND VIA
    // =========================================================================
    public const SEND_VIA_EMAIL = 'email';
    public const SEND_VIA_POST = 'post';
    public const SEND_VIA_PORTAL = 'portal';
    public const SEND_VIA_SMS = 'sms';

    public const SEND_VIA_OPTIONS = [
        self::SEND_VIA_EMAIL => 'Email',
        self::SEND_VIA_POST => 'Post',
        self::SEND_VIA_PORTAL => 'Portal',
        self::SEND_VIA_SMS => 'SMS',
    ];

    // =========================================================================
    // BILLING - PREFIXES
    // =========================================================================
    public const PREFIX_INVOICE = 'INV';
    public const PREFIX_PAYMENT = 'PAY';
    public const PREFIX_CREDIT_NOTE = 'CN';

    // =========================================================================
    // BILLING - OVERDUE THRESHOLDS (Days)
    // =========================================================================
    public const OVERDUE_GRACE_PERIOD = 30;
    public const OVERDUE_SUSPENSION_THRESHOLD = 60;
    public const OVERDUE_LAPSE_THRESHOLD = 90;

    // =========================================================================
    // BILLING - PAYMENT TERMS TO DAYS MAPPING
    // =========================================================================
    public const PAYMENT_TERMS_DAYS = [
        self::PAYMENT_TERMS_IMMEDIATE => 0,
        self::PAYMENT_TERMS_15_DAYS => 15,
        self::PAYMENT_TERMS_30_DAYS => 30,
        self::PAYMENT_TERMS_60_DAYS => 60,
    ];
}