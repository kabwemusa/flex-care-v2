<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Password Policy Configuration
    |--------------------------------------------------------------------------
    |
    | Configure password expiration, strength requirements, and account lockout
    | policies for your application.
    |
    */

    // Password expiration settings
    'expiration' => [
        'enabled' => env('PASSWORD_EXPIRATION_ENABLED', true),
        'days' => env('PASSWORD_EXPIRATION_DAYS', 90), // Password expires after 90 days
        'warning_days' => env('PASSWORD_EXPIRATION_WARNING_DAYS', 7), // Warn user 7 days before expiration
    ],

    // Password strength requirements
    'strength' => [
        'min_length' => env('PASSWORD_MIN_LENGTH', 8),
        'require_uppercase' => env('PASSWORD_REQUIRE_UPPERCASE', true),
        'require_lowercase' => env('PASSWORD_REQUIRE_LOWERCASE', true),
        'require_numbers' => env('PASSWORD_REQUIRE_NUMBERS', true),
        'require_special' => env('PASSWORD_REQUIRE_SPECIAL', true),
    ],

    // Password history (prevent reuse)
    'history' => [
        'enabled' => env('PASSWORD_HISTORY_ENABLED', true),
        'count' => env('PASSWORD_HISTORY_COUNT', 5), // Remember last 5 passwords
    ],

    // Account lockout policy
    'lockout' => [
        'enabled' => env('ACCOUNT_LOCKOUT_ENABLED', true),
        'max_attempts' => env('ACCOUNT_LOCKOUT_MAX_ATTEMPTS', 5), // Lock after 5 failed attempts
        'lockout_duration' => env('ACCOUNT_LOCKOUT_DURATION', 15), // Lock for 15 minutes
    ],

    // Session management
    'session' => [
        'lifetime' => env('SESSION_LIFETIME', 120), // 2 hours in minutes (absolute token lifetime)
        'inactivity_timeout' => env('SESSION_INACTIVITY_TIMEOUT', 15), // 15 minutes of inactivity
        'refresh_token_lifetime' => env('REFRESH_TOKEN_LIFETIME', 10080), // 7 days in minutes
    ],

];
