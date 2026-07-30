<?php

namespace App\Http\Middleware;

use App\Models\Team;
use App\Services\PlanService;
use App\Services\UsageService;
use App\Support\Terminology;
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
            // The sidebar shows the organization's plan and its posts-this-cycle
            // meter on every screen, so both are shared rather than re-fetched
            // per page. Lazily evaluated — guests and the marketing page skip it.
            'planName'  => fn () => $request->user() && $team ? ucfirst((string) $team->plan) : null,
            'planUsage' => fn () => $request->user() && $team
                ? app(UsageService::class)->snapshot($team)
                : null,
            // Capability entitlements for the active organization. This is the
            // ONLY thing Vue may use to decide whether a feature is available —
            // no component compares plan names. `upgradeFor` lets a locked item
            // explain which plan unlocks it instead of just being greyed out.
            'capabilities' => fn () => $team ? app(PlanService::class)->capabilities($team) : [],
            'upgradeFor'   => fn () => $team ? $this->upgradeTargets($team) : [],
            // Customer-facing labels for internal model names, so templates
            // never hard-code "Workspace" or "Channel".
            'terms' => fn () => app(Terminology::class)->all($team),
            'unreadNotifications' => fn () => $request->user()?->unreadNotifications()->count() ?? 0,
            'isPlatformAdmin'  => (bool) $request->user()?->is_platform_admin,
            'isImpersonating'  => $request->session()->has('impersonator_id'),
            // A short status code set by controllers via back()->with('status', ...),
            // e.g. "feed-added", "imported-3-skipped-1" — rendered as a banner in AppLayout.
            'status' => fn () => $request->session()->get('status'),
        ];
    }

    /**
     * For every capability the organization does NOT have, the name of the
     * cheapest plan that grants it. A locked navigation item can then say
     * "Campaigns — on Pro and above" rather than being silently disabled,
     * which the 2.0 brief requires.
     *
     * @return array<string, string>
     */
    protected function upgradeTargets(Team $team): array
    {
        $plans   = app(PlanService::class);
        $targets = [];

        foreach ($plans->capabilities($team) as $capability => $granted) {
            if ($granted) {
                continue;
            }

            if ($name = $plans->upgradePlanFor($capability)) {
                $targets[$capability] = $name;
            }
        }

        return $targets;
    }
}
