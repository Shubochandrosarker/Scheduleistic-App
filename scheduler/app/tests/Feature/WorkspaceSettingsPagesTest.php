<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class WorkspaceSettingsPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_feeds_index_lists_the_workspace_feeds(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $workspace = Workspace::create(['team_id' => $owner->currentTeam->id, 'name' => 'Acme']);
        $workspace->feeds()->create(['url' => 'https://blog.test/feed', 'name' => 'Blog']);

        $this->actingAs($owner)
            ->get(route('workspaces.feeds.index', $workspace))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Feeds/Index')
                ->has('feeds', 1)
                ->where('feeds.0.url', 'https://blog.test/feed'));
    }

    public function test_time_slots_index_lists_slots_ordered_by_day_and_time(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $workspace = Workspace::create(['team_id' => $owner->currentTeam->id, 'name' => 'Acme']);
        $workspace->timeSlots()->create(['day_of_week' => 2, 'time' => '09:00']);
        $workspace->timeSlots()->create(['day_of_week' => 1, 'time' => '15:00']);

        $this->actingAs($owner)
            ->get(route('workspaces.time-slots.index', $workspace))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('TimeSlots/Index')
                ->has('timeSlots', 2)
                ->where('timeSlots.0.day_of_week', 1)
                ->where('timeSlots.1.day_of_week', 2));
    }

    public function test_bulk_import_create_page_lists_workspace_channels(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $workspace = Workspace::create(['team_id' => $owner->currentTeam->id, 'name' => 'Acme']);
        $workspace->channels()->create(['provider' => 'linkedin', 'name' => 'LI']);

        $this->actingAs($owner)
            ->get(route('workspaces.import.create', $workspace))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('BulkImport/Create')
                ->has('channels', 1)
                ->where('channels.0.provider', 'linkedin'));
    }

    public function test_workspaces_index_reports_feed_and_time_slot_counts(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $workspace = Workspace::create(['team_id' => $owner->currentTeam->id, 'name' => 'Acme']);
        $workspace->feeds()->create(['url' => 'https://blog.test/feed']);
        $workspace->timeSlots()->create(['day_of_week' => 1, 'time' => '09:00']);

        $this->actingAs($owner)
            ->get(route('workspaces.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Workspaces/Index')
                ->where('workspaces.0.feeds_count', 1)
                ->where('workspaces.0.time_slots_count', 1));
    }

    public function test_a_user_outside_the_organization_cannot_view_workspace_settings_pages(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $workspace = Workspace::create(['team_id' => $owner->currentTeam->id, 'name' => 'Acme']);

        $outsider = User::factory()->withPersonalTeam()->create();

        $this->actingAs($outsider)->get(route('workspaces.feeds.index', $workspace))->assertForbidden();
        $this->actingAs($outsider)->get(route('workspaces.time-slots.index', $workspace))->assertForbidden();
        $this->actingAs($outsider)->get(route('workspaces.import.create', $workspace))->assertForbidden();
    }
}
