<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The settings hub the master prompt calls for: a single landing page
 * linking every settings-adjacent destination (team, billing, branding,
 * notifications, webhooks, account) that otherwise only exist as scattered
 * sidebar entries. Deliberately thin — every destination already enforces
 * its own authorization and plan gating; this page only decides what to
 * *show*, via the same `capabilities` shared prop every other page uses, so
 * nothing here duplicates a security decision made elsewhere.
 */
class SettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $team = $request->user()->currentTeam;

        return Inertia::render('Settings/Index', [
            'organization' => $team?->only(['id', 'name']),
            'isOwner' => $team && $team->owner && $team->owner->is($request->user()),
        ]);
    }
}
