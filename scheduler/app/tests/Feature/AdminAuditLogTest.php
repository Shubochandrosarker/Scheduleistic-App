<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admins_cannot_view_the_audit_log(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)->get(route('admin.audit-logs.index'))->assertForbidden();
    }

    public function test_platform_admin_sees_a_paginated_list(): void
    {
        $admin = User::factory()->platformAdmin()->withPersonalTeam()->create();
        AuditLog::record('campaign.created', null, ['name' => 'A'], teamId: $admin->currentTeam->id, userId: $admin->id);
        AuditLog::record('campaign.deleted', null, [], teamId: $admin->currentTeam->id, userId: $admin->id);

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/AuditLogs/Index')
                ->has('logs.data', 2));
    }

    public function test_filtering_by_actor_narrows_results(): void
    {
        $admin = User::factory()->platformAdmin()->withPersonalTeam()->create();
        $otherAdmin = User::factory()->platformAdmin()->withPersonalTeam()->create(['name' => 'Zelda Admin', 'email' => 'zelda@example.test']);

        AuditLog::record('campaign.created', null, [], teamId: $admin->currentTeam->id, userId: $admin->id);
        AuditLog::record('campaign.created', null, [], teamId: $otherAdmin->currentTeam->id, userId: $otherAdmin->id);

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index', ['actor' => 'zelda']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('logs.data', 1)
                ->where('logs.data.0.actor.id', $otherAdmin->id));
    }

    public function test_filtering_by_action_narrows_results(): void
    {
        $admin = User::factory()->platformAdmin()->withPersonalTeam()->create();
        AuditLog::record('campaign.created', null, [], teamId: $admin->currentTeam->id, userId: $admin->id);
        AuditLog::record('idea.converted', null, [], teamId: $admin->currentTeam->id, userId: $admin->id);

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index', ['action' => 'idea.converted']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('logs.data', 1)
                ->where('logs.data.0.action', 'idea.converted'));
    }

    public function test_filtering_by_organization_narrows_results(): void
    {
        $admin = User::factory()->platformAdmin()->withPersonalTeam()->create();
        $owner = User::factory()->withPersonalTeam()->create();

        AuditLog::record('organization.suspended', null, [], teamId: $admin->currentTeam->id, userId: $admin->id);
        AuditLog::record('organization.suspended', null, [], teamId: $owner->currentTeam->id, userId: $admin->id);

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index', ['organization_id' => $owner->currentTeam->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('logs.data', 1)
                ->where('logs.data.0.organization.id', $owner->currentTeam->id));
    }

    public function test_context_is_returned_already_redacted(): void
    {
        $admin = User::factory()->platformAdmin()->withPersonalTeam()->create();
        AuditLog::record('webhook.secret_rotated', null, ['old_secret' => 'whsec_leak'], teamId: $admin->currentTeam->id, userId: $admin->id);

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('logs.data.0.context.old_secret', '[redacted]'));
    }

    public function test_there_is_no_update_or_delete_route(): void
    {
        $this->assertFalse(Route::has('admin.audit-logs.update'));
        $this->assertFalse(Route::has('admin.audit-logs.destroy'));
    }
}
