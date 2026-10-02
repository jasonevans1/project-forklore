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

    'openweather' => [
        'key' => env('OPENWEATHER_API_KEY'),
    ],

    'typesafe' => [
        'key' => env('TYPESAFE_API_KEY'),
        'model' => env('TYPESAFE_MODEL', 'jev-latest'),
        'timeout' => (int) env('TYPESAFE_TIMEOUT', 5),
        'daily_quota' => (int) env('TYPESAFE_DAILY_QUOTA', 10000),
        'min_confidence' => (float) env('TYPESAFE_MIN_CONFIDENCE', 0.6),
    ],

    'google_places' => [
        'key' => env('GOOGLE_PLACES_API_KEY'),
        'daily_quota' => (int) env('GOOGLE_PLACES_DAILY_QUOTA', 1000),
    ],

];
