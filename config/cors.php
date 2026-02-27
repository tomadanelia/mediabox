<?php

return [

  
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:3000',
        'http://127.0.0.1:3000',
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://159.89.20.100',
        'https://tv-api.telecomm1.com',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'localhost:3000,127.0.0.1:3000,localhost:5173,127.0.0.1:5173,http://159.89.20.100')),

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
