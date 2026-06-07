<?php

namespace App\Http\Controllers;

use App\Services\UsageService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function __construct(private readonly UsageService $usage) {}

    /** Plan overview: current plan, usage snapshot, and upgrade options. */
    public function index(Request $request): Response
    {
        $team = $request->user()->currentTeam;

        return Inertia::render('Billing/Index', [
            'currentPlan' => $team->plan,
            'plans'       => collect(config('plans'))->map(fn ($p, $key) => [
                'key'    => $key,
                'name'   => $p['name'],
                'limits' => $p['limits'],
            ])->values(),
            'usage'        => $this->usage->snapshot($team),
            'subscribed'   => $team->subscribed('default'),
        ]);
    }

    /** Start a Stripe Checkout session for a plan. */
    public function checkout(Request $request, string $plan)
    {
        abort_unless($this->isOwner($request), 403, 'Only the organization owner can manage billing.');

        $priceId = config("plans.{$plan}.price_id");
        abort_if(! $priceId, 404, 'That plan is not purchasable.');

        $team = $request->user()->currentTeam;

        return $team
            ->newSubscription('default', $priceId)
            ->checkout([
                'success_url' => route('billing.index').'?checkout=success',
                'cancel_url'  => route('billing.index').'?checkout=cancelled',
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
