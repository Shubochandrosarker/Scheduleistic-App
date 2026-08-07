<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\ChannelHealthEvent;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\ChannelNeedsAttention;
use App\Social\ProviderManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * `refreshToken()` is implemented on every OAuth driver but, before this,
 * was never called by anything — a channel's token would just silently
 * expire and force a full reconnect. This command is the one place it now
 * runs, proactively, before the health evaluation that decides whether to
 * warn anyone.
 */
class CheckChannelHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Real drivers, not FakeProvider — refreshToken()'s actual HTTP call
        // is exactly what this command is meant to exercise.
        $this->app->instance(ProviderManager::class, new ProviderManager(fake: false));

        config(['services.linkedin.client_id' => 'id', 'services.linkedin.client_secret' => 'secret']);
    }

    protected function linkedInChannel(array $attributes = []): Channel
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $workspace = Workspace::create(['team_id' => $owner->currentTeam->id, 'name' => 'Brand']);

        return $workspace->channels()->create(array_merge([
            'provider' => 'linkedin',
            'name' => 'Member',
            'access_token' => 'old-token',
            'refresh_token' => 'old-refresh',
        ], $attributes));
    }

    public function test_a_token_expiring_soon_is_proactively_refreshed(): void
    {
        Http::fake([
            'www.linkedin.com/oauth/v2/accessToken' => Http::response([
                'access_token' => 'new-token', 'expires_in' => 5_184_000, // 60 days
            ]),
        ]);

        $channel = $this->linkedInChannel(['token_expires_at' => now()->addDays(3)]);

        $this->artisan('channels:check-health')->assertExitCode(0);

        $channel->refresh();
        $this->assertSame('new-token', $channel->access_token);
        $this->assertTrue($channel->token_expires_at->isAfter(now()->addDays(59)));
        $this->assertSame(ChannelHealthEvent::STATE_CONNECTED, $channel->health_state);

        Http::assertSent(fn ($request) => $request['grant_type'] === 'refresh_token');
    }

    public function test_a_token_not_yet_near_expiry_is_left_alone(): void
    {
        Http::fake(); // any request at all fails the assertion below

        $channel = $this->linkedInChannel(['token_expires_at' => now()->addDays(30)]);

        $this->artisan('channels:check-health')->assertExitCode(0);

        Http::assertNothingSent();
        $this->assertSame('old-token', $channel->fresh()->access_token);
    }

    public function test_a_failed_refresh_still_lets_evaluate_flag_the_channel_and_notify_the_owner(): void
    {
        Notification::fake();
        Http::fake(['www.linkedin.com/oauth/v2/accessToken' => Http::response('invalid_grant', 400)]);

        $channel = $this->linkedInChannel(['token_expires_at' => now()->addDay()]);

        $this->artisan('channels:check-health')->assertExitCode(0);

        $channel->refresh();
        // The refresh attempt itself marks the channel "expired" (its own
        // failure path); evaluate() then confirms the same conclusion from
        // the still-imminent stored expiry — refreshing must never mask a
        // real problem.
        $this->assertContains($channel->health_state, [
            ChannelHealthEvent::STATE_TOKEN_EXPIRING,
            ChannelHealthEvent::STATE_TOKEN_EXPIRED,
        ]);

        Notification::assertSentTo($channel->workspace->team->owner, ChannelNeedsAttention::class);
    }

    public function test_a_channel_with_no_refresh_token_is_evaluated_without_error(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $workspace = Workspace::create(['team_id' => $owner->currentTeam->id, 'name' => 'Brand']);
        $workspace->channels()->create([
            'provider' => 'mastodon',
            'name' => 'Masto',
            'access_token' => 'token',
            'meta' => ['instance' => 'https://mastodon.social'],
        ]);

        $this->artisan('channels:check-health')->assertExitCode(0);

        $this->assertTrue(true); // reaching here without an exception is the assertion
    }
}
