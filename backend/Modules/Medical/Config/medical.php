<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tax Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the tax rate applied to insurance premiums.
    | The rate should be a decimal (e.g., 0.05 for 5%, 0.16 for 16% VAT)
    |
    */
    'tax_rate' => env('MEDICAL_TAX_RATE', 0.05),
    'tax_name' => env('MEDICAL_TAX_NAME', 'VAT'),

    /*
    |--------------------------------------------------------------------------
    | Quote Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for quote generation and validity.
    |
    */
    'quote_validity_days' => env('MEDICAL_QUOTE_VALIDITY_DAYS', 30),
    'quote_validity_after_approval_days' => env('MEDICAL_QUOTE_VALIDITY_AFTER_APPROVAL_DAYS', 14),

    /*
    |--------------------------------------------------------------------------
    | Invoice Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for invoice generation.
    |
    */
    'invoice' => [
        // Automatically generate first invoice when policy is created
        'auto_generate_on_policy_creation' => env('MEDICAL_AUTO_GENERATE_INVOICE', true),

        // Invoice due days from issue date
        'due_days' => env('MEDICAL_INVOICE_DUE_DAYS', 30),

        // Invoice number prefix
        'number_prefix' => env('MEDICAL_INVOICE_PREFIX', 'INV-'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Policy Configuration
    |--------------------------------------------------------------------------
    |
    | Default settings for policy creation.
    |
    */
    'policy' => [
        // Default policy term in months
        'default_term_months' => env('MEDICAL_DEFAULT_POLICY_TERM', 12),

        // Default billing frequency
        'default_billing_frequency' => env('MEDICAL_DEFAULT_BILLING_FREQUENCY', 'monthly'),

        // Default currency
        'default_currency' => env('MEDICAL_DEFAULT_CURRENCY', 'ZMW'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Underwriting Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for underwriting rules and limits.
    |
    */
    'underwriting' => [
        // Maximum loading percentage allowed
        'max_loading_percentage' => env('MEDICAL_MAX_LOADING_PERCENTAGE', 100),

        // Auto-approve applications with no pre-existing conditions
        'auto_approve_clean_applications' => env('MEDICAL_AUTO_APPROVE_CLEAN', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Renewal Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for policy renewal notifications and processing.
    |
    */
    'renewal' => [
        // Days before expiry to send first renewal reminder
        'first_reminder_days' => env('MEDICAL_RENEWAL_FIRST_REMINDER', 60),

        // Days before expiry to send second renewal reminder
        'second_reminder_days' => env('MEDICAL_RENEWAL_SECOND_REMINDER', 30),

        // Days before expiry to send final renewal reminder
        'final_reminder_days' => env('MEDICAL_RENEWAL_FINAL_REMINDER', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Claims Processing Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for real-time claims processing and auto-adjudication.
    |
    */
    'claims' => [
        // Maximum claim amount for auto-adjudication
        'auto_adjudication_max_amount' => env('MEDICAL_AUTO_ADJUDICATION_MAX', 10000),

        // Maximum fraud score for auto-approval
        'auto_adjudication_max_fraud_score' => env('MEDICAL_AUTO_MAX_FRAUD_SCORE', 40),

        // Pre-authorization validity in hours
        'preauth_validity_hours' => env('MEDICAL_PREAUTH_VALIDITY_HOURS', 72),

        // Eligibility cache TTL in seconds
        'eligibility_cache_ttl' => env('MEDICAL_ELIGIBILITY_CACHE_TTL', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fraud Detection Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for fraud detection thresholds and scoring.
    |
    */
    'fraud' => [
        // Score threshold to block claim (requires investigation)
        'block_threshold' => env('MEDICAL_FRAUD_BLOCK_THRESHOLD', 90),

        // Score threshold to flag for review
        'review_threshold' => env('MEDICAL_FRAUD_REVIEW_THRESHOLD', 50),

        // Enable ML-based fraud detection (future feature)
        'ml_enabled' => env('MEDICAL_FRAUD_ML_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for member and provider notifications.
    |
    */
    'notifications' => [
        'email' => [
            'enabled' => env('MEDICAL_EMAIL_NOTIFICATIONS', true),
        ],
        'sms' => [
            'enabled' => env('MEDICAL_SMS_NOTIFICATIONS', false),
        ],
        'websocket' => [
            'enabled' => env('MEDICAL_WEBSOCKET_ENABLED', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider API Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for hospital/provider API integration.
    |
    */
    'provider_api' => [
        // Default rate limit per minute
        'rate_limit_per_minute' => env('MEDICAL_API_RATE_LIMIT_MINUTE', 60),

        // Default rate limit per hour
        'rate_limit_per_hour' => env('MEDICAL_API_RATE_LIMIT_HOUR', 1000),

        // API request logging enabled
        'logging_enabled' => env('MEDICAL_API_LOGGING', true),

        // Eligibility check SLA in milliseconds
        'eligibility_sla_ms' => env('MEDICAL_ELIGIBILITY_SLA_MS', 500),
    ],
];
