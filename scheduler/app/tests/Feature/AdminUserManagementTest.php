<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admins_cannot_access_the_user_index(): void
    {
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_platform_admin_sees_a_paginated_user_index(): void
    {
        $admin = User::factory()->platformAdmin()->withPersonalTeam()->create();
        User::factory()->count(3)->withPersonalTeam()->create();

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Users/Index')
                ->has('users.data', 4) // 3 + the admin themselves
                ->where('users.total', 4));
    }

    public function test_pagination_is_bounded_by_per_page(): void
    {
        $admin = User::factory()->platformAdmin()->withPersonalTeam()->create();
        User::factory()->count(15)->withPersonalTeam()->create();

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['per_page' => 10]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('users.data', 10)
                ->where('users.total', 16));
    }

    public function test_search_filters_by_name_or_email(): void
    {
        $admin = User::factory()->platformAdmin()->withPersonalTeam()->create();
        $match = User::factory()->withPersonalTeam()->create(['name' => 'Zelda Scheduler', 'email' => 'zelda@example.test']);
        User::factory()->withPersonalTeam()->create(['name' => 'Someone Else', 'email' => 'someone@example.test']);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['search' => 'zelda']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.id', $match->id));
    }

    public function test_status_filter_narrows_to_suspended_or_active(): void
    {
        $admin = User::factory()->platformAdmin()->withPersonalTeam()->create();
        $suspended = User::factory()->withPersonalTeam()->create();
        $suspended->forceFill(['suspended_at' => now()])->save();
        User::factory()->withPersonalTeam()->create(); // active

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['status' => 'suspended']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.id', $suspended->id));
    }

    public function test_invalid_sort_column_is_rejected(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['sort' => 'password']))
            ->assertSessionHasErrors('sort');
    }

    public function test_admin_can_suspend_and_reactivate_a_user_with_audit(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.suspend', $user->id), ['reason' => 'Abuse report'])
            ->assertRedirect();

        $user->refresh();
        $this->assertTrue($user->isSuspended());
        $this->assertSame('Abuse report', $user->suspension_reason);
        $this->assertSame($admin->id, $user->suspended_by);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.suspended',
            'user_id' => $admin->id,
            'subject_type' => User::class,
            'subject_id' => $user->id,
        ]);

        $this->actingAs($admin)->post(route('admin.users.suspend', $user->id));

        $this->assertFalse($user->fresh()->isSuspended());
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.reactivated', 'subject_id' => $user->id]);
    }

    public function test_suspending_requires_a_reason(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $user = User::factory()->withPersonalTeam()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.suspend', $user->id), [])
            ->assertSessionHasErrors('reason');

        $this->assertFalse($user->fresh()->isSuspended());
    }

    public function test_an_admin_cannot_suspend_their_own_account(): void
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->post(route('admin.users.suspend', $admin->id), ['reason' => 'oops'])
            ->assertStatus(422);

        $this->assertFalse($admin->fresh()->isSuspended());
    }

    public function test_reset_link_uses_framework_behavior_and_never_exposes_a_token(): void
    {
        Notification::fake();

        $admin = User::factory()->platformAdmin()->create();
        $user = User::factory()->withPersonalTeam()->create();

        $response = $this->actingAs($admin)->post(route('admin.users.reset-link', $user->id));

        $response->assertRedirect();
        $response->assertSessionMissing('token');

        // The token-bearing email is dispatched through the framework's own
        // notification, never returned in the response or written to the
        // audit log.
        Notification::assertSentTo($user, ResetPassword::class);

        $audit = AuditLog::where('action', 'user.password_reset_link_sent')->first();
        $this->assertNotNull($audit);
        $this->assertSame($user->id, $audit->subject_id);
        $this->assertArrayNotHasKey('token', $audit->context ?? []);
    }

    public function test_revoking_sessions_deletes_the_users_session_rows(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $user = User::factory()->withPersonalTeam()->create();

        DB::table('sessions')->insert([
            'id' => 'session-one',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => base64_encode('x'),
            'last_activity' => time(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.users.revoke-sessions', $user->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('sessions', ['id' => 'session-one']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.sessions_revoked', 'subject_id' => $user->id]);
    }
}
