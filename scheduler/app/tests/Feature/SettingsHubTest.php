<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The settings hub linking every settings-adjacent destination (team,
 * billing, branding, notifications, webhooks, account) in one place.
 */
class SettingsHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_view_settings(): void
    {
        $this->get(route('settings.index'))->assertRedirect(route('login'));
    }

    public function test_it_renders_for_the_organization_owner(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();

        $this->actingAs($owner)
            ->get(route('settings.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Settings/Index')
                ->where('organization.name', $owner->currentTeam->name)
                ->where('isOwner', true));
    }

    public function test_a_non_owner_member_sees_is_owner_as_false(): void
    {
        $owner = User::factory()->withPersonalTeam()->create();
        $member = User::factory()->create();
        $owner->currentTeam->users()->attach($member, ['role' => 'admin']);
        $member->switchTeam($owner->currentTeam);

        $this->actingAs($member)
            ->get(route('settings.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('isOwner', false));
    }
}
