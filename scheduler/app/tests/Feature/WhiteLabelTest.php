<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhiteLabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_update_branding_and_it_merges_over_defaults(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)
            ->put(route('branding.update'), [
                'name'    => 'AgencyOne Social',
                'primary' => '#ff0000',
            ])
            ->assertSessionHasNoErrors();

        $team = $user->currentTeam->fresh();
        $branding = $team->brandingConfig();

        $this->assertSame('AgencyOne Social', $branding['name']);
        $this->assertSame('#ff0000', $branding['primary']);
        // Untouched values fall back to platform defaults.
        $this->assertSame(config('scheduleistic.powered_by'), $branding['powered_by']);
    }

    public function test_setting_a_custom_domain_issues_a_verification_token_and_marks_unverified(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)
            ->put(route('branding.domain'), ['custom_domain' => 'app.youragency.com'])
            ->assertSessionHasNoErrors();

        $team = $user->currentTeam->fresh();
        $this->assertSame('app.youragency.com', $team->custom_domain);
        $this->assertFalse($team->customDomainVerified());
        $this->assertStringStartsWith('scheduleistic-verify-', $team->custom_domain_token);
    }

    public function test_a_non_owner_cannot_change_white_label_settings(): void
    {
        $owner  = User::factory()->withPersonalTeam()->create();
        $member = User::factory()->create();
        $owner->currentTeam->users()->attach($member, ['role' => 'admin']);
        $member->switchTeam($owner->currentTeam);

        $this->actingAs($member)
            ->put(route('branding.update'), ['name' => 'Hijack'])
            ->assertForbidden();
    }
}
