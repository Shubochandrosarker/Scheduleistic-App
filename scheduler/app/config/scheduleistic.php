<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Scheduleistic Branding
    |--------------------------------------------------------------------------
    |
    | These values drive the default (platform owner) branding. For white-label
    | tenants, the per-organization branding stored on the `organizations`
    | (Jetstream "teams") record overrides these at runtime.
    |
    */

    'name' => env('APP_NAME', 'Scheduleistic'),
    'tagline' => 'Schedule posts. Manage clients. Publish with confidence.',
    'powered_by' => env('BRAND_POWERED_BY', 'Powered by Scheduleistic'),

    /*
    |--------------------------------------------------------------------------
    | Fake publishing
    |--------------------------------------------------------------------------
    |
    | When true, every provider resolves to the FakeProvider — no live network
    | calls are made. Useful for local development before OAuth apps exist.
    |
    */

    'fake_publishing' => env('SOCIAL_FAKE', false),

    'colors' => [
        // The dashboard retints itself from these two values, so they are the
        // platform's accent until a tenant sets its own in white-label settings.
        // These match the marketing site's brand-500 and blue, so the product
        // and scheduleistic.com read as one brand.
        'primary' => env('BRAND_PRIMARY', '#6366F1'),
        'secondary' => env('BRAND_SECONDARY', '#2878D0'),
    ],

    'logo' => env('BRAND_LOGO', null),
    'favicon' => env('BRAND_FAVICON', null),

    /*
    |--------------------------------------------------------------------------
    | White-Label
    |--------------------------------------------------------------------------
    |
    | Managed white-label: tenants point a CNAME at the platform and get a
    | branded dashboard on their own custom domain. When white_label.enabled
    | is true, the "powered_by" line may be hidden for licensed tenants.
    |
    */

    'white_label' => [
        'enabled' => env('WHITE_LABEL_ENABLED', false),
        'allow_custom_domain' => env('WHITE_LABEL_CUSTOM_DOMAIN', true),
        'hide_powered_by' => env('WHITE_LABEL_HIDE_POWERED_BY', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider display labels (cosmetic only — NOT a connect-UI gate)
    |--------------------------------------------------------------------------
    |
    | This is not what decides which networks appear on the channel-connect
    | screen. That list comes entirely from `App\Social\ProviderManager`'s
    | driver registry — every registered driver appears there, gated only by
    | whether its OAuth app is configured on this instance
    | (`AbstractOAuthProvider::isConfigured()`). This array is read in exactly
    | one place, `MediaValidator::label()`, as a friendlier fallback string
    | for validation error/warning messages. A provider missing from this
    | list still works fine — it just falls back to an auto-generated label.
    |
    | To add a network to the connect UI, register its driver in
    | `ProviderManager` and add its capability block to
    | `config/provider_capabilities.php` — see
    | docs/SCHEDULEISTIC_2_PROVIDER_CAPABILITIES.md §7. Editing this array
    | alone does nothing.
    |
    */

    'providers' => [
        'linkedin' => ['label' => 'LinkedIn (Personal)'],
        'linkedin_company' => ['label' => 'LinkedIn (Company Page)'],
        'facebook' => ['label' => 'Facebook Page'],
        'instagram' => ['label' => 'Instagram'],
        'google_business' => ['label' => 'Google Business Profile'],
        'pinterest' => ['label' => 'Pinterest'],
        'threads' => ['label' => 'Threads'],
        'tiktok' => ['label' => 'TikTok'],
        'youtube' => ['label' => 'YouTube'],
        'mastodon' => ['label' => 'Mastodon'],
        'bluesky' => ['label' => 'Bluesky'],
        'medium' => ['label' => 'Medium'],
        'wordpress' => ['label' => 'WordPress'],
    ],

];
