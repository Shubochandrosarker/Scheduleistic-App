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

    // --- SOCIALISTIC social provider OAuth credentials --------------------

    'linkedin' => [
        'client_id'     => env('LINKEDIN_CLIENT_ID'),
        'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
    ],

    'linkedin_company' => [
        'client_id'     => env('LINKEDIN_CLIENT_ID'),
        'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
    ],

    'facebook' => [
        'client_id'     => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
    ],

    'instagram' => [
        'client_id'     => env('INSTAGRAM_CLIENT_ID', env('FACEBOOK_CLIENT_ID')),
        'client_secret' => env('INSTAGRAM_CLIENT_SECRET', env('FACEBOOK_CLIENT_SECRET')),
    ],

    'google_business' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    ],

    // YouTube uses the same Google Cloud OAuth client as Google Business.
    'youtube' => [
        'client_id'     => env('YOUTUBE_CLIENT_ID', env('GOOGLE_CLIENT_ID')),
        'client_secret' => env('YOUTUBE_CLIENT_SECRET', env('GOOGLE_CLIENT_SECRET')),
    ],

    // Threads uses the same Meta app as Facebook/Instagram.
    'threads' => [
        'client_id'     => env('THREADS_CLIENT_ID', env('FACEBOOK_CLIENT_ID')),
        'client_secret' => env('THREADS_CLIENT_SECRET', env('FACEBOOK_CLIENT_SECRET')),
    ],

    'pinterest' => [
        'client_id'     => env('PINTEREST_CLIENT_ID'),
        'client_secret' => env('PINTEREST_CLIENT_SECRET'),
    ],

    'tiktok' => [
        'client_id'     => env('TIKTOK_CLIENT_ID'),
        'client_secret' => env('TIKTOK_CLIENT_SECRET'),
    ],

];
