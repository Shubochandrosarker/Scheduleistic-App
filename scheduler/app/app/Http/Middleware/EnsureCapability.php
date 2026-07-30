<?php

namespace App\Http\Middleware;

use App\Services\PlanService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a route behind a plan capability.
 *
 *     Route::get(...)->middleware('capability:media_library');
 *
 * Hiding a navigation item is presentation; this is the enforcement. Every
 * capability-gated route carries it, so a hand-crafted request cannot reach a
 * feature the organization has not paid for.
 *
 * Responds 402 Payment Required (not 403) so the client can tell "you need to
 * upgrade" apart from "you are not allowed", and names the plan that unlocks it.
 */
class EnsureCapability
{
    public function __construct(private readonly PlanService $plans) {}

    public function handle(Request $request, Closure $next, string $capability): Response
    {
        $team = $request->user()?->currentTeam;

        if (! $team) {
            abort(403, 'No active organization.');
        }

        if ($this->plans->can($team, $capability)) {
            return $next($request);
        }

        $upgrade = $this->plans->upgradePlanFor($capability);

        $message = $upgrade
            ? "This feature is available on the {$upgrade} plan and above."
            : 'This feature is not available on your plan.';

        if ($request->expectsJson()) {
            abort(402, $message);
        }

        return back()->withErrors(['plan' => $message]);
    }
}
