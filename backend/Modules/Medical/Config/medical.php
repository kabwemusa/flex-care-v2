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
];
