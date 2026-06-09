<?php

namespace App\Listeners;

use App\Models\Team;
use Illuminate\Support\Arr;
use Laravel\Cashier\Events\WebhookReceived;

/**
 * Keeps each organization's `plan` column (which drives usage limits) in sync
 * with its Stripe subscription, reacting to Cashier's webhook events.
 */
class SyncTeamPlan
{
    public function handle(WebhookReceived $event): void
    {
        $type = $event->payload['type'] ?? '';

        if (! str_starts_with($type, 'customer.subscription.')) {
            return;
        }

        $customerId = Arr::get($event->payload, 'data.object.customer');

        if (! $customerId) {
            return;
        }

        $team = Team::where('stripe_id', $customerId)->first();

        $team?->syncPlanFromSubscription();
    }
}
