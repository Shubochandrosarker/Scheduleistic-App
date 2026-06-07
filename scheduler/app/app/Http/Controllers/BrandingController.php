<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * White-label settings for the current organization: dashboard branding and a
 * custom domain (CNAME) with a verification token.
 */
class BrandingController extends Controller
{
    public function edit(Request $request): Response
    {
        $team = $request->user()->currentTeam;

        return Inertia::render('Branding/Edit', [
            'branding'        => $team->brandingConfig(),
            'customDomain'    => $team->custom_domain,
            'domainVerified'  => $team->customDomainVerified(),
            'verifyToken'     => $team->custom_domain_token,
            'cnameTarget'     => parse_url(config('app.url'), PHP_URL_HOST),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->guardOwner($request);

        $validated = $request->validate([
            'name'       => ['nullable', 'string', 'max:120'],
            'tagline'    => ['nullable', 'string', 'max:200'],
            'primary'    => ['nullable', 'string', 'max:9'],
            'secondary'  => ['nullable', 'string', 'max:9'],
            'logo'       => ['nullable', 'url', 'max:500'],
        ]);

        $request->user()->currentTeam->update([
            'branding' => array_filter($validated, fn ($v) => $v !== null && $v !== ''),
        ]);

        return back()->with('status', 'branding-updated');
    }

    /** Set (or change) the custom domain and issue a fresh verification token. */
    public function updateDomain(Request $request): RedirectResponse
    {
        $this->guardOwner($request);

        $validated = $request->validate([
            'custom_domain' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9.-]+\.[a-z]{2,}$/i'],
        ]);

        $request->user()->currentTeam->update([
            'custom_domain'             => $validated['custom_domain'] ?? null,
            'custom_domain_verified_at' => null,
            'custom_domain_token'       => 'socialistic-verify-'.Str::random(24),
        ]);

        return back()->with('status', 'domain-updated');
    }

    protected function guardOwner(Request $request): void
    {
        $team = $request->user()->currentTeam;
        abort_unless($team && $team->owner->is($request->user()), 403, 'Only the organization owner can change white-label settings.');
    }
}
