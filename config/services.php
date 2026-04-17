<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],
    'telecom1' => [
    'account_id' => env('TELECOM1_ACCOUNT_ID'),
    'key' => env('TELECOM1_API_KEY'),
    'from' => env('TELECOM1_SENDER_NAME'),
    'url' => env('TELECOM1_API_URL'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'nginx' => [
        'secure_link_secret' => env('NGINX_SECURE_LINK_SECRET'),
    ],
    'monitoring' => [
        'allowed_ips' => explode(',', env('ALLOWED_STATS_AND_INTERPAY_IPS', '127.0.0.1')),
    ],
    'flussonic' => [
    'key_default' => env('FLUSSONIC_KEY_DEFAULT'),
    'key_special' => env('FLUSSONIC_KEY_SPECIAL'),
    'proxy_ge'    => env('FLUSSONIC_PROXY_GE'),
    'proxy_global' => env('FLUSSONIC_PROXY_GLOBAL'),
    'cdn' => env('FLUSSONIC_CDN', 'https://cdn.streamer.mediabox.ge'),

],
    

];
