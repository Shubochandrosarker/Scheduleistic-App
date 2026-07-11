<?php

/*
|--------------------------------------------------------------------------
| Scheduleistic Subscription Plans
|--------------------------------------------------------------------------
|
| Each plan declares a Stripe price id (set in .env) and the usage limits it
| grants. A limit of -1 means unlimited. The PlanService + UsageService read
| this to gate features per organization. `price` is display-only (USD/month).
| `features` flags drive plan-gated capabilities and the pricing UI.
|
*/

return [

    'free' => [
        'name' => 'Free Trial',
        'price' => 0,
        'price_id' => null,
        'limits' => [
            'workspaces' => 1,
            'channels' => 3,
            'members' => 1,
            'monthly_posts' => 30,
        ],
        'features' => [
            'client_approval' => false,
            'white_label' => false,
            'analytics' => 'none',
            'ai_captions' => false,
            'ai_agents' => false,
        ],
    ],

    'pro' => [
        'name' => 'Pro',
        'price' => 19,
        'price_id' => env('STRIPE_PRICE_PRO'),
        'limits' => [
            'workspaces' => 3,
            'channels' => 10,
            'members' => 3,
            'monthly_posts' => 300,
        ],
        'features' => [
            'client_approval' => false,
            'white_label' => false,
            'analytics' => 'basic',
            'ai_captions' => true,
            'ai_agents' => true,
        ],
    ],

    'agency' => [
        'name' => 'Agency',
        'price' => 49,
        'price_id' => env('STRIPE_PRICE_AGENCY'),
        'limits' => [
            'workspaces' => 15,
            'channels' => 50,
            'members' => 10,
            'monthly_posts' => 2000,
        ],
        'features' => [
            'client_approval' => true,
            'white_label' => true,
            'analytics' => 'basic',
            'ai_captions' => true,
            'ai_agents' => true,
        ],
    ],

    'scale' => [
        'name' => 'Scale',
        'price' => 99,
        'price_id' => env('STRIPE_PRICE_SCALE'),
        'limits' => [
            'workspaces' => 50,
            'channels' => 150,
            'members' => 50,
            'monthly_posts' => 10000,
        ],
        'features' => [
            'client_approval' => true,
            'white_label' => true,
            'analytics' => 'advanced',
            'ai_captions' => true,
            'ai_agents' => true,
        ],
    ],

];
