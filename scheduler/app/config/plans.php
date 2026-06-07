<?php

/*
|--------------------------------------------------------------------------
| SOCIALISTIC Subscription Plans
|--------------------------------------------------------------------------
|
| Each plan declares a Stripe price id (set in .env) and the usage limits it
| grants. A limit of -1 means unlimited. The PlanService + UsageService read
| this to gate features per organization.
|
*/

return [

    'free' => [
        'name'     => 'Free',
        'price_id' => null,
        'limits'   => [
            'workspaces'    => 1,
            'channels'      => 3,
            'members'       => 1,
            'monthly_posts' => 30,
        ],
    ],

    'pro' => [
        'name'     => 'Pro',
        'price_id' => env('STRIPE_PRICE_PRO'),
        'limits'   => [
            'workspaces'    => 5,
            'channels'      => 25,
            'members'       => 5,
            'monthly_posts' => 1000,
        ],
    ],

    'agency' => [
        'name'     => 'Agency',
        'price_id' => env('STRIPE_PRICE_AGENCY'),
        'limits'   => [
            'workspaces'    => -1,
            'channels'      => -1,
            'members'       => -1,
            'monthly_posts' => -1,
        ],
    ],

];
