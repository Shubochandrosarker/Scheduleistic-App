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
        $team = $request->user()?->currentTeam;

        return [
            ...parent::share($request),
            // White-label branding for the active organization (falls back to platform defaults).
            'branding' => $team
                ? $team->brandingConfig()
                : [
                    'name'       => config('socialistic.name'),
                    'tagline'    => config('socialistic.tagline'),
                    'powered_by' => config('socialistic.powered_by'),
                ],
            'isPlatformAdmin'  => (bool) $request->user()?->is_platform_admin,
            'isImpersonating'  => $request->session()->has('impersonator_id'),
        ];
    }
}
