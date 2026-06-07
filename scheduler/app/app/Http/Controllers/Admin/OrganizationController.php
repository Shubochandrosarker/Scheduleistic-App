<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Super-admin control plane: manage every organization on the platform.
 */
class OrganizationController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Organizations', [
            'organizations' => Team::query()
                ->withCount('workspaces')
                ->with('owner:id,name,email')
                ->latest()
                ->limit(500)
                ->get()
                ->map(fn (Team $t) => [
                    'id'         => $t->id,
                    'name'       => $t->name,
                    'plan'       => $t->plan,
                    'owner'      => $t->owner?->only('name', 'email'),
                    'workspaces' => $t->workspaces_count,
                    'suspended'  => $t->isSuspended(),
                    'subscribed' => $t->subscribed('default'),
                ]),
            'stats' => [
                'organizations' => Team::count(),
                'subscribed'    => Team::whereHas('subscriptions', fn ($q) => $q->where('stripe_status', 'active'))->count(),
            ],
        ]);
    }

    public function suspend(Request $request, Team $organization): RedirectResponse
    {
        $organization->update(['suspended_at' => $organization->isSuspended() ? null : now()]);

        return back()->with('status', 'organization-toggled');
    }

    /** Impersonate the organization owner for support. */
    public function impersonate(Request $request, Team $organization): RedirectResponse
    {
        $owner = $organization->owner;
        abort_if(! $owner, 404);

        // Disallow nested impersonation.
        abort_if($request->session()->has('impersonator_id'), 409, 'Already impersonating.');

        // Audit trail: who impersonated whom.
        Log::warning('Platform admin started impersonation', [
            'admin_id'        => $request->user()->id,
            'organization_id' => $organization->id,
            'owner_id'        => $owner->id,
        ]);

        $request->session()->put('impersonator_id', $request->user()->id);
        auth()->guard('web')->login($owner);

        return redirect()->route('dashboard')->with('status', 'impersonating');
    }

    public function stopImpersonating(Request $request): RedirectResponse
    {
        $impersonatorId = $request->session()->pull('impersonator_id');
        abort_if(! $impersonatorId, 403);

        auth()->guard('web')->loginUsingId($impersonatorId);

        return redirect()->route('admin.organizations.index');
    }
}
