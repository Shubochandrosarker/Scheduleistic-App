<?php

/*
|--------------------------------------------------------------------------
| Scheduleistic Subscription Plans
|--------------------------------------------------------------------------
|
| Each plan declares a Stripe price id (set in .env), the usage limits it
| grants, and the capability flags it unlocks. A limit of -1 means unlimited.
|
| `capabilities` is the canonical entitlement surface: backend gates and the
| Vue layer both resolve features through PlanService::can(), never by
| comparing plan names. `features` is the legacy flag map kept for backward
| compatibility with pre-2.0 call sites; it is derived from the same truth.
|
| `price` is display-only (USD/month). Capability keys are documented in
| docs/SCHEDULEISTIC_2_ARCHITECTURE.md.
|
*/

/**
 * Every capability the product knows about, defaulting to off. Plans below
 * switch on what they grant, so adding a new capability never silently
 * enables it for an existing plan.
 */
$capabilities = [
    // Planning + creation
    'planner_advanced'      => false, // week/agenda/grid views, saved filter views
    'composer_variants'     => false, // per-network caption/media overrides
    'media_library'         => false,
    'campaigns'             => false,
    'ideas'                 => false,
    'content_templates'     => false,

    // Collaboration
    'approvals'             => false,
    'approvals_multi_stage' => false,
    'guest_approval'        => false,

    // Engagement
    'social_inbox'          => false,
    'saved_replies'         => false,

    // Intelligence
    'analytics_basic'       => false,
    'analytics_advanced'    => false,
    'best_times'            => false,
    'reports'               => false,
    'reports_branded'       => false,
    'reports_scheduled'     => false,
    'competitor_tracking'   => false,

    // Links + presence
    'link_tracking'         => false,
    'link_in_bio'           => false,

    // Agency / white-label
    'client_portal'         => false,
    'white_label'           => false,
    'custom_domain'         => false,

    // Ecosystem
    'api_access'            => false,
    'webhooks'              => false,
    'mcp'                   => false,
    'wordpress_integration' => false,

    // AI
    'ai_captions'           => false,
    'ai_agents'             => false,
    'ai_copilot'            => false,

    // Platform
    'priority_processing'   => false,
    'sso'                   => false,
];

$grant = static function (array $keys) use ($capabilities): array {
    foreach ($keys as $key) {
        $capabilities[$key] = true;
    }

    return $capabilities;
};

return [

    'free' => [
        'name'     => 'Free',
        'price'    => 0,
        'price_id' => null,
        'limits'   => [
            'workspaces'    => 1,
            'channels'      => 3,
            'members'       => 1,
            'monthly_posts' => 30,
            'storage_mb'    => 200,
        ],
        'capabilities' => $grant([]),
    ],

    'solo' => [
        'name'     => 'Solo',
        'price'    => 9,
        'price_id' => env('STRIPE_PRICE_SOLO'),
        'limits'   => [
            'workspaces'    => 1,
            'channels'      => 10,
            'members'       => 1,
            'monthly_posts' => 500,
            'storage_mb'    => 2_000,
        ],
        'capabilities' => $grant([
            'planner_advanced',
            'composer_variants',
            'content_templates',
            'analytics_basic',
            'link_tracking',
            'link_in_bio',
            'ai_captions',
            'wordpress_integration',
        ]),
    ],

    'pro' => [
        'name'     => 'Pro',
        'price'    => 19,
        'price_id' => env('STRIPE_PRICE_PRO'),
        'limits'   => [
            'workspaces'    => 5,
            'channels'      => 25,
            'members'       => 5,
            'monthly_posts' => 2_000,
            'storage_mb'    => 10_000,
        ],
        'capabilities' => $grant([
            'planner_advanced',
            'composer_variants',
            'media_library',
            'campaigns',
            'ideas',
            'content_templates',
            'approvals',
            'social_inbox',
            'saved_replies',
            'analytics_basic',
            'best_times',
            'reports',
            'link_tracking',
            'link_in_bio',
            'ai_captions',
            'ai_agents',
            'wordpress_integration',
        ]),
    ],

    'agency' => [
        'name'     => 'Agency',
        'price'    => 49,
        'price_id' => env('STRIPE_PRICE_AGENCY'),
        'limits'   => [
            'workspaces'    => 20,
            'channels'      => 75,
            'members'       => 15,
            'monthly_posts' => 10_000,
            'storage_mb'    => 50_000,
        ],
        'capabilities' => $grant([
            'planner_advanced',
            'composer_variants',
            'media_library',
            'campaigns',
            'ideas',
            'content_templates',
            'approvals',
            'approvals_multi_stage',
            'guest_approval',
            'social_inbox',
            'saved_replies',
            'analytics_basic',
            'best_times',
            'reports',
            'reports_branded',
            'reports_scheduled',
            'link_tracking',
            'link_in_bio',
            'client_portal',
            'white_label',
            'custom_domain',
            'api_access',
            'webhooks',
            'ai_captions',
            'ai_agents',
            'ai_copilot',
            'wordpress_integration',
        ]),
    ],

    'scale' => [
        'name'     => 'Scale',
        'price'    => 99,
        'price_id' => env('STRIPE_PRICE_SCALE'),
        'limits'   => [
            'workspaces'    => 50,
            'channels'      => 200,
            'members'       => 50,
            'monthly_posts' => 50_000,
            'storage_mb'    => 250_000,
        ],
        'capabilities' => $grant([
            'planner_advanced',
            'composer_variants',
            'media_library',
            'campaigns',
            'ideas',
            'content_templates',
            'approvals',
            'approvals_multi_stage',
            'guest_approval',
            'social_inbox',
            'saved_replies',
            'analytics_basic',
            'analytics_advanced',
            'best_times',
            'reports',
            'reports_branded',
            'reports_scheduled',
            'competitor_tracking',
            'link_tracking',
            'link_in_bio',
            'client_portal',
            'white_label',
            'custom_domain',
            'api_access',
            'webhooks',
            'mcp',
            'ai_captions',
            'ai_agents',
            'ai_copilot',
            'priority_processing',
            'wordpress_integration',
        ]),
    ],

    'enterprise' => [
        'name'     => 'Enterprise',
        'price'    => null, // "Talk to us" — no self-serve checkout.
        'price_id' => env('STRIPE_PRICE_ENTERPRISE'),
        'limits'   => [
            'workspaces'    => -1,
            'channels'      => -1,
            'members'       => -1,
            'monthly_posts' => -1,
            'storage_mb'    => -1,
        ],
        'capabilities' => $grant(array_keys($capabilities)),
    ],

];
