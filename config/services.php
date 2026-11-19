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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'rapidapi' => [
        'key' => env('RAPIDAPI_KEY', '5e1cc80557msh4d69d78125eb8dap1768d5jsncf78f12042fb'),
        'host' => env('RAPIDAPI_HOST', 'cricket-live-line1.p.rapidapi.com'),
        'base_url' => env('RAPIDAPI_BASE_URL', 'https://cricket-live-line1.p.rapidapi.com'),
        'rate_limit_per_day' => (int) env('RAPIDAPI_DAILY_LIMIT', 100),
    ],

    'cricketliveapi' => [
        'base_url' => env('CRICKETLIVEAPI_BASE_URL', 'https://cricketliveapi.com/api/v1'),
        'token' => env('CRICKETLIVEAPI_TOKEN', ''),
    ],

    'rapidapi_cricket' => [
        'key' => env('RAPIDAPI_KEY', '5e1cc80557msh4d69d78125eb8dap1768d5jsncf78f12042fb'),
        'host' => env('RAPIDAPI_CRICKET_HOST', 'cricket-live-line1.p.rapidapi.com'),
        'base_url' => env('RAPIDAPI_BASE_URL', 'https://cricket-live-line1.p.rapidapi.com'),
    ],

];
