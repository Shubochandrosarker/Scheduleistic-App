<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SOCIALISTIC Branding
    |--------------------------------------------------------------------------
    |
    | These values drive the default (platform owner) branding. For white-label
    | tenants, the per-organization branding stored on the `organizations`
    | (Jetstream "teams") record overrides these at runtime.
    |
    */

    'name'        => env('APP_NAME', 'SOCIALISTIC'),
    'tagline'     => 'Complete Social Automated System',
    'powered_by'  => 'Powered by WPistic — A product of the WORDPRESSISTIC Ecosystem',

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
        'primary'   => env('BRAND_PRIMARY', '#4f46e5'),
        'secondary' => env('BRAND_SECONDARY', '#0ea5e9'),
    ],

    'logo'    => env('BRAND_LOGO', null),
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
        'enabled'            => env('WHITE_LABEL_ENABLED', false),
        'allow_custom_domain'=> env('WHITE_LABEL_CUSTOM_DOMAIN', true),
        'hide_powered_by'    => env('WHITE_LABEL_HIDE_POWERED_BY', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Social Providers (MVP)
    |--------------------------------------------------------------------------
    |
    | The driver registry. Each key maps to a SocialProvider driver class
    | (added in later phases). `enabled` controls availability in the UI.
    |
    */

    'providers' => [
        'linkedin'         => ['label' => 'LinkedIn (Personal)', 'enabled' => true],
        'linkedin_company' => ['label' => 'LinkedIn (Company Page)', 'enabled' => true],
        'facebook'         => ['label' => 'Facebook Page', 'enabled' => true],
        'instagram'        => ['label' => 'Instagram', 'enabled' => true],
        'google_business'  => ['label' => 'Google Business Profile', 'enabled' => true],
        'wordpress'        => ['label' => 'WordPress', 'enabled' => true],
        'website'          => ['label' => 'Website / Laravel article feed', 'enabled' => true],
    ],

];
