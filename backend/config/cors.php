<?php
// backend/config/cors.php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:4200',                  // Keep this for local development
        'https://flex-care-v2.vercel.app',   // <--- PASTE YOUR VERCEL URL HERE
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true, // <--- SET THIS TO TRUE if using Cookies/Sanctum

];