<?php

namespace App\Services;

use App\Jobs\DeliverWebhookJob;
use App\Models\Team;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Str;

/**
 * Fans an application event out to an organization's webhook endpoints.
 *
 * Each endpoint gets its own delivery row and its own queued job, so a slow or
 * broken receiver cannot delay the others. The delivery row is created *before*
 * the job is queued: if the worker dies mid-flight the attempt is still on
 * record, and the `event_id` it carries is the idempotency key receivers use to
 * discard a retried duplicate.
 */
class WebhookDispatcher
{
    /**
     * @param  array<string, mixed>  $payload
     * @return int  number of endpoints the event was queued for
     */
    public function dispatch(Team $team, string $event, array $payload, ?int $workspaceId = null): int
    {
        if (! in_array($event, WebhookEndpoint::EVENTS, true)) {
            throw new \InvalidArgumentException("Unknown webhook event [{$event}].");
        }

        $endpoints = WebhookEndpoint::query()
            ->where('team_id', $team->id)
            ->where('is_active', true)
            ->whereNull('disabled_at')
            // A workspace-scoped endpoint only hears about its own workspace;
            // an endpoint with no workspace hears about the whole account.
            ->where(fn ($q) => $q->whereNull('workspace_id')->orWhere('workspace_id', $workspaceId))
            ->get()
            ->filter(fn (WebhookEndpoint $endpoint) => $endpoint->subscribesTo($event));

        foreach ($endpoints as $endpoint) {
            $delivery = WebhookDelivery::create([
                'webhook_endpoint_id' => $endpoint->id,
                'event_id'            => (string) Str::uuid(),
                'event'               => $event,
                'payload'             => [
                    'event'      => $event,
                    'created_at' => now()->toIso8601String(),
                    'account_id' => $team->id,
                    'data'       => $payload,
                ],
                'status' => WebhookDelivery::STATUS_PENDING,
            ]);

            DeliverWebhookJob::dispatch($delivery->id);
        }

        return $endpoints->count();
    }
}
