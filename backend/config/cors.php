<?php
// backend/config/cors.php
 return [
        'paths' => ['api/*', 'sanctum/csrf-cookie', 'v1/*'],
        'allowed_methods' => ['*'],
        
        // 1. Allow all origins temporarily
        'allowed_origins' => ['*'], 
        
        'allowed_origins_patterns' => [],
        'allowed_headers' => ['*'],
        'exposed_headers' => [],
        'max_age' => 0,
        
        // 2. You MUST set this to false if using wildcard '*' origins
        'supports_credentials' => false, 
    ];

