<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // Branding precedence: the custom-domain tenant (works for guests too),
        // then the authenticated user's current organization.
        $team = $request->attributes->get('tenant_team') ?? $request->user()?->currentTeam;

        return [
            ...parent::share($request),
            // White-label branding for the active organization (falls back to platform defaults).
            'branding' => $team
                ? $team->brandingConfig()
                : [
                    'name'       => config('scheduleistic.name'),
                    'tagline'    => config('scheduleistic.tagline'),
                    'powered_by' => config('scheduleistic.powered_by'),
                ],
            'isPlatformAdmin'  => (bool) $request->user()?->is_platform_admin,
            'isImpersonating'  => $request->session()->has('impersonator_id'),
        ];
    }
}
