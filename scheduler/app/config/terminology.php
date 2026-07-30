<?php

/*
|--------------------------------------------------------------------------
| Presentation Terminology
|--------------------------------------------------------------------------
|
| Scheduleistic's database and models keep their engineering names —
| Organization (Jetstream team), Workspace, Channel, PostTarget — because
| renaming tables and relationships buys nothing and breaks everything.
|
| Customers, however, do not think in those words. This map is the single
| place where an internal concept is given its customer-facing label. It is
| shared with Inertia so Vue reads labels from props instead of hard-coding
| them, and `App\Support\Terminology` reads it server-side for validation
| messages and notification copy.
|
| Changing a label here changes it everywhere. Changing a *key* does not,
| because keys mirror model names.
|
*/

return [

    'organization' => ['one' => 'Account',        'many' => 'Accounts'],
    'workspace'    => ['one' => 'Brand',          'many' => 'Brands'],
    'channel'      => ['one' => 'Social profile', 'many' => 'Social profiles'],
    'member'       => ['one' => 'Team member',    'many' => 'Team members'],
    'post_target'  => ['one' => 'Destination',    'many' => 'Destinations'],
    'provider'     => ['one' => 'Network',        'many' => 'Networks'],
    'post'         => ['one' => 'Post',           'many' => 'Posts'],
    'campaign'     => ['one' => 'Campaign',       'many' => 'Campaigns'],
    'pillar'       => ['one' => 'Content pillar', 'many' => 'Content pillars'],
    'idea'         => ['one' => 'Idea',           'many' => 'Ideas'],
    'media_asset'  => ['one' => 'Asset',          'many' => 'Assets'],

    /*
    | Agencies manage other people's brands and say "client"; in-house teams
    | manage their own and say "brand". The organization picks which noun the
    | workspace concept uses; everything else is fixed.
    */
    'workspace_modes' => [
        'brand'  => ['one' => 'Brand',  'many' => 'Brands'],
        'client' => ['one' => 'Client', 'many' => 'Clients'],
    ],

];
