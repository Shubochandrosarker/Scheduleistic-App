<?php

namespace App\Http\Controllers;

use App\Services\PlanService;
use App\Services\UsageService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function __construct(
        private readonly UsageService $usage,
        private readonly PlanService $plans,
    ) {}

    /** Plan overview: current plan, usage snapshot, and upgrade options. */
    public function index(Request $request): Response
    {
        $team = $request->user()->currentTeam;

        // Reconcile the stored plan with the live subscription (covers cases where
        // a webhook was missed, e.g. local dev without Stripe CLI forwarding).
        if ($team->stripe_id) {
            $team->syncPlanFromSubscription();
        }

        return Inertia::render('Billing/Index', [
            'currentPlan' => $team->plan,
            'plans' => collect(config('plans'))->map(fn ($p, $key) => [
                'key' => $key,
                'name' => $p['name'],
                'price' => $p['price'] ?? 0,
                'limits' => $p['limits'],
                // Each plan's own capabilities, not this team's — a comparison
                // table describes what every tier offers, not what entitlement
                // overrides happen to apply to the team viewing it.
                'features' => $this->plans->planFeatures($key),
            ])->values(),
            'usage' => $this->usage->snapshot($team),
            'subscribed' => $team->subscribed('default'),
        ]);
    }

    /** Start a Stripe Checkout session for a plan. */
    public function checkout(Request $request, string $plan)
    {
        abort_unless($this->isOwner($request), 403, 'Only the organization owner can manage billing.');

        $priceId = config("plans.{$plan}.price_id");
        abort_if(! $priceId, 404, 'That plan is not purchasable.');

        $team = $request->user()->currentTeam;

        // Cashier's newSubscription() would otherwise create a second
        // "default" subscription for a team that already has one active.
        if ($team->subscribed('default')) {
            return back()->withErrors([
                'plan' => 'Your organization already has an active subscription. Manage it from the billing portal instead.',
            ]);
        }

        return $team
            ->newSubscription('default', $priceId)
            ->checkout([
                'success_url' => route('billing.index').'?checkout=success',
                'cancel_url' => route('billing.index').'?checkout=cancelled',
            ]);
    }

    /** Redirect to the Stripe billing portal to manage an existing subscription. */
    public function portal(Request $request)
    {
        abort_unless($this->isOwner($request), 403);

        return $request->user()->currentTeam->redirectToBillingPortal(route('billing.index'));
    }

    protected function isOwner(Request $request): bool
    {
        $team = $request->user()->currentTeam;

        return $team && $team->owner && $team->owner->is($request->user());
    }
}
