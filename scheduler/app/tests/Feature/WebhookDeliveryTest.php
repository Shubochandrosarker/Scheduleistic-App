<?php

namespace Tests\Feature;

use App\Jobs\DeliverWebhookJob;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\Workspace;
use App\Services\WebhookDispatcher;
use App\Support\SsrfGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->withPersonalTeam()->create();
        $this->owner->currentTeam->update(['plan' => 'agency']);
    }

    protected function endpoint(array $overrides = []): WebhookEndpoint
    {
        return WebhookEndpoint::create(array_merge([
            'team_id' => $this->owner->currentTeam->id,
            'name'    => 'Automation',
            'url'     => 'https://hooks.example.com/scheduleistic',
            'events'  => ['publish.succeeded', 'publish.failed'],
        ], $overrides));
    }

    /** A guard that treats the fake host as reachable, since DNS is not. */
    protected function permissiveGuard(): SsrfGuard
    {
        return new class extends SsrfGuard
        {
            public function isFetchable(string $url): bool
            {
                return $this->isAllowed($url);
            }
        };
    }

    // --- Management -------------------------------------------------------

    public function test_creating_an_endpoint_shows_the_secret_exactly_once(): void
    {
        $response = $this->actingAs($this->owner)->post(route('webhooks.store'), [
            'name'   => 'Automation',
            'url'    => 'https://hooks.example.com/scheduleistic',
            'events' => ['publish.succeeded'],
        ]);

        $response->assertSessionHas('webhook_secret');

        $secret = session('webhook_secret');
        $this->assertStringStartsWith('whsec_', $secret);

        // Encrypted at rest — the plaintext must not be sitting in the column.
        $stored = WebhookEndpoint::first();
        $this->assertNotSame($secret, $stored->getRawOriginal('secret'));
        $this->assertSame($secret, $stored->secret);

        // …and it is never serialised back to the client.
        $this->assertArrayNotHasKey('secret', $stored->toArray());
    }

    public function test_an_endpoint_pointing_at_a_private_address_is_rejected(): void
    {
        $this->actingAs($this->owner)
            ->post(route('webhooks.store'), [
                'name'   => 'Internal',
                'url'    => 'https://127.0.0.1/hook',
                'events' => ['publish.succeeded'],
            ])
            ->assertSessionHasErrors('url');
    }

    public function test_a_plain_http_endpoint_is_rejected(): void
    {
        $this->actingAs($this->owner)
            ->post(route('webhooks.store'), [
                'name'   => 'Insecure',
                'url'    => 'http://hooks.example.com/hook',
                'events' => ['publish.succeeded'],
            ])
            ->assertSessionHasErrors('url');
    }

    public function test_an_unknown_event_is_rejected(): void
    {
        $this->actingAs($this->owner)
            ->post(route('webhooks.store'), [
                'name'   => 'Automation',
                'url'    => 'https://hooks.example.com/hook',
                'events' => ['post.launched_into_space'],
            ])
            ->assertSessionHasErrors('events.0');
    }

    public function test_rotating_the_secret_replaces_it(): void
    {
        $endpoint = $this->endpoint();
        $original = $endpoint->secret;

        $this->actingAs($this->owner)->post(route('webhooks.rotate', $endpoint))->assertSessionHas('webhook_secret');

        $this->assertNotSame($original, $endpoint->fresh()->secret);
        $this->assertNotNull($endpoint->fresh()->secret_rotated_at);
    }

    public function test_webhooks_are_unavailable_below_agency(): void
    {
        $this->owner->currentTeam->update(['plan' => 'pro']);

        $this->actingAs($this->owner)->get(route('webhooks.index'))->assertSessionHasErrors('plan');
    }

    public function test_another_accounts_endpoint_cannot_be_touched(): void
    {
        $endpoint = $this->endpoint();

        $stranger = User::factory()->withPersonalTeam()->create();
        $stranger->currentTeam->update(['plan' => 'agency']);

        $this->actingAs($stranger)->post(route('webhooks.rotate', $endpoint))->assertForbidden();
        $this->actingAs($stranger)->delete(route('webhooks.destroy', $endpoint))->assertForbidden();
    }

    // --- Dispatch ---------------------------------------------------------

    public function test_dispatch_creates_one_delivery_per_subscribed_endpoint(): void
    {
        Bus::fake();

        $this->endpoint();
        $this->endpoint(['name' => 'Second', 'events' => ['publish.failed']]);

        $count = app(WebhookDispatcher::class)->dispatch(
            $this->owner->currentTeam,
            'publish.succeeded',
            ['post_id' => 1],
        );

        // Only the endpoint subscribed to this event.
        $this->assertSame(1, $count);
        $this->assertSame(1, WebhookDelivery::count());

        Bus::assertDispatched(DeliverWebhookJob::class);
    }

    public function test_a_workspace_scoped_endpoint_only_hears_about_its_own_workspace(): void
    {
        Bus::fake();

        $mine = Workspace::create(['team_id' => $this->owner->currentTeam->id, 'name' => 'Mine']);
        $other = Workspace::create(['team_id' => $this->owner->currentTeam->id, 'name' => 'Other']);

        $this->endpoint(['workspace_id' => $mine->id, 'events' => ['publish.succeeded']]);

        $dispatcher = app(WebhookDispatcher::class);

        $this->assertSame(1, $dispatcher->dispatch($this->owner->currentTeam, 'publish.succeeded', [], $mine->id));
        $this->assertSame(0, $dispatcher->dispatch($this->owner->currentTeam, 'publish.succeeded', [], $other->id));
    }

    public function test_dispatching_an_unknown_event_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(WebhookDispatcher::class)->dispatch($this->owner->currentTeam, 'not.a.real.event', []);
    }

    // --- Delivery ---------------------------------------------------------

    public function test_a_delivery_is_signed_and_marked_delivered(): void
    {
        Http::fake(['hooks.example.com/*' => Http::response('ok', 200)]);

        $endpoint = $this->endpoint();
        $delivery = WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event_id'            => (string) \Illuminate\Support\Str::uuid(),
            'event'               => 'publish.succeeded',
            'payload'             => ['event' => 'publish.succeeded', 'data' => ['post_id' => 7]],
        ]);

        (new DeliverWebhookJob($delivery->id))->handle($this->permissiveGuard());

        $delivery->refresh();

        $this->assertSame(WebhookDelivery::STATUS_DELIVERED, $delivery->status);
        $this->assertSame(200, $delivery->response_status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertNotNull($delivery->delivered_at);

        Http::assertSent(function ($request) use ($endpoint, $delivery) {
            $timestamp = $request->header('X-Scheduleistic-Timestamp')[0];
            $signature = $request->header('X-Scheduleistic-Signature')[0];

            // The signature must verify exactly the way the docs tell
            // receivers to verify it.
            $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$request->body(), $endpoint->secret);

            return $signature === $expected
                && $request->header('X-Scheduleistic-Delivery')[0] === $delivery->event_id
                && $request->header('X-Scheduleistic-Event')[0] === 'publish.succeeded';
        });
    }

    public function test_a_5xx_response_is_retried_and_recorded_as_failed(): void
    {
        Http::fake(['hooks.example.com/*' => Http::response('boom', 500)]);

        $endpoint = $this->endpoint();
        $delivery = WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event_id'            => (string) \Illuminate\Support\Str::uuid(),
            'event'               => 'publish.succeeded',
            'payload'             => [],
        ]);

        try {
            (new DeliverWebhookJob($delivery->id))->handle($this->permissiveGuard());
            $this->fail('A 5xx must throw so the queue retries it.');
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertSame(WebhookDelivery::STATUS_FAILED, $delivery->fresh()->status);
        $this->assertSame(1, $endpoint->fresh()->consecutive_failures);
    }

    public function test_an_endpoint_is_disabled_after_repeated_failures(): void
    {
        Http::fake(['hooks.example.com/*' => Http::response('boom', 500)]);

        $endpoint = $this->endpoint([
            'consecutive_failures' => WebhookEndpoint::FAILURE_THRESHOLD - 1,
        ]);

        $delivery = WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event_id'            => (string) \Illuminate\Support\Str::uuid(),
            'event'               => 'publish.succeeded',
            'payload'             => [],
        ]);

        try {
            (new DeliverWebhookJob($delivery->id))->handle($this->permissiveGuard());
        } catch (\RuntimeException) {
            // expected
        }

        $endpoint->refresh();

        $this->assertFalse($endpoint->is_active);
        $this->assertNotNull($endpoint->disabled_at);
        $this->assertStringContainsString('consecutive failures', $endpoint->disabled_reason);
    }

    public function test_a_success_clears_the_failure_streak(): void
    {
        Http::fake(['hooks.example.com/*' => Http::response('ok', 200)]);

        $endpoint = $this->endpoint(['consecutive_failures' => 4]);
        $delivery = WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event_id'            => (string) \Illuminate\Support\Str::uuid(),
            'event'               => 'publish.succeeded',
            'payload'             => [],
        ]);

        (new DeliverWebhookJob($delivery->id))->handle($this->permissiveGuard());

        $this->assertSame(0, $endpoint->fresh()->consecutive_failures);
    }

    public function test_a_destination_that_resolves_to_a_private_address_is_never_called(): void
    {
        Http::fake();

        // Saved before the guard would have caught it (e.g. DNS re-pointed
        // after creation), so the worker must re-check at send time.
        $endpoint = WebhookEndpoint::create([
            'team_id' => $this->owner->currentTeam->id,
            'name'    => 'Rebound',
            'url'     => 'https://169.254.169.254/latest/meta-data',
            'events'  => ['publish.succeeded'],
        ]);

        $delivery = WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event_id'            => (string) \Illuminate\Support\Str::uuid(),
            'event'               => 'publish.succeeded',
            'payload'             => [],
        ]);

        (new DeliverWebhookJob($delivery->id))->handle(app(SsrfGuard::class));

        Http::assertNothingSent();

        $this->assertSame(WebhookDelivery::STATUS_FAILED, $delivery->fresh()->status);
        $this->assertFalse($endpoint->fresh()->is_active);
    }

    public function test_a_delivered_delivery_is_not_sent_twice(): void
    {
        Http::fake();

        $endpoint = $this->endpoint();
        $delivery = WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event_id'            => (string) \Illuminate\Support\Str::uuid(),
            'event'               => 'publish.succeeded',
            'payload'             => [],
            'status'              => WebhookDelivery::STATUS_DELIVERED,
        ]);

        (new DeliverWebhookJob($delivery->id))->handle($this->permissiveGuard());

        Http::assertNothingSent();
    }

    public function test_a_replay_creates_a_new_delivery_with_a_fresh_idempotency_key(): void
    {
        Bus::fake();

        $endpoint = $this->endpoint();
        $original = WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event_id'            => (string) \Illuminate\Support\Str::uuid(),
            'event'               => 'publish.succeeded',
            'payload'             => ['data' => ['post_id' => 7]],
            'status'              => WebhookDelivery::STATUS_FAILED,
        ]);

        $this->actingAs($this->owner)
            ->post(route('webhooks.replay', $original))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, WebhookDelivery::count());

        $replay = WebhookDelivery::latest('id')->first();

        $this->assertSame($original->payload, $replay->payload);
        $this->assertNotSame($original->event_id, $replay->event_id);

        Bus::assertDispatched(DeliverWebhookJob::class);
    }
}
