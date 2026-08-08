<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\ChannelHealthEvent;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The health index only ever loads openHealthEvents(); this is the first
 * read path for the full timeline including resolved events — documented
 * as intended in docs/SCHEDULEISTIC_2_ADMIN_GUIDE.md but never built.
 */
class ChannelHealthHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function channel(User $owner): Channel
    {
        $workspace = Workspace::create(['team_id' => $owner->currentTeam->id, 'name' => 'Brand']);

        return $workspace->channels()->create(['provider' => 'linkedin', 'name' => 'Company page']);
    }

    public function test_the_history_page_includes_resolved_events(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $channel = $this->channel($owner);

        $channel->healthEvents()->create([
            'state' => ChannelHealthEvent::STATE_RATE_LIMITED,
            'severity' => 'warning',
            'message' => 'Slow down',
            'detected_at' => now()->subDays(3),
            'resolved_at' => now()->subDays(2),
        ]);
        $channel->healthEvents()->create([
            'state' => ChannelHealthEvent::STATE_TOKEN_EXPIRED,
            'severity' => 'critical',
            'message' => 'Reconnect required',
            'detected_at' => now()->subHour(),
            'resolved_at' => null,
        ]);

        $this->actingAs($owner)
            ->get(route('channels.health.history', $channel->id))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Channels/HealthHistory')
                ->where('channel.id', $channel->id)
                ->has('events.data', 2)
                ->where('events.data.0.resolved', false) // most recent (detected_at desc) is the open one
                ->where('events.data.1.resolved', true));
    }

    public function test_a_channel_with_no_events_shows_an_empty_history(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $channel = $this->channel($owner);

        $this->actingAs($owner)
            ->get(route('channels.health.history', $channel->id))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('events.data', 0));
    }

    public function test_a_user_cannot_view_another_tenants_channel_history(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $channel = $this->channel($owner);

        $intruder = User::factory()->withPersonalTeam()->create();

        $this->actingAs($intruder)
            ->get(route('channels.health.history', $channel->id))
            ->assertForbidden();
    }
}
