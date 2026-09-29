<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'parliament_vic' => [
        'base_url' => 'https://www.parliament.vic.gov.au',
        'allowed_hosts' => ['www.parliament.vic.gov.au', 'parliament.vic.gov.au'],
        'user_agent' => 'DoTheyRepresentMe/1.0 (+'.env('APP_URL', 'http://localhost').')',
        'max_document_bytes' => 20 * 1024 * 1024,
        'request_delay_ms' => (int) env('PARLIAMENT_VIC_REQUEST_DELAY_MS', 1000),
    ],

];
