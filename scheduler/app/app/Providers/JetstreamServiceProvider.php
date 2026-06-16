<?php

namespace App\Providers;

use App\Actions\Jetstream\AddTeamMember;
use App\Actions\Jetstream\CreateTeam;
use App\Actions\Jetstream\DeleteTeam;
use App\Actions\Jetstream\DeleteUser;
use App\Actions\Jetstream\InviteTeamMember;
use App\Actions\Jetstream\RemoveTeamMember;
use App\Actions\Jetstream\UpdateTeamName;
use Illuminate\Support\ServiceProvider;
use Laravel\Jetstream\Jetstream;

class JetstreamServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configurePermissions();

        Jetstream::createTeamsUsing(CreateTeam::class);
        Jetstream::updateTeamNamesUsing(UpdateTeamName::class);
        Jetstream::addTeamMembersUsing(AddTeamMember::class);
        Jetstream::inviteTeamMembersUsing(InviteTeamMember::class);
        Jetstream::removeTeamMembersUsing(RemoveTeamMember::class);
        Jetstream::deleteTeamsUsing(DeleteTeam::class);
        Jetstream::deleteUsersUsing(DeleteUser::class);
    }

    /**
     * Configure the roles and permissions that are available within the application.
     */
    protected function configurePermissions(): void
    {
        Jetstream::defaultApiTokenPermissions(['read']);

        // Organization (Jetstream "team") roles for Scheduleistic.
        // The organization Owner is the team creator and implicitly has every
        // permission; the roles below are assigned to invited members.

        Jetstream::role('admin', 'Administrator', [
            'organization:manage',
            'workspace:create',
            'workspace:update',
            'workspace:delete',
            'channel:connect',
            'channel:disconnect',
            'post:create',
            'post:update',
            'post:delete',
            'post:approve',
            'analytics:view',
        ])->description('Full access to all workspaces, members, and settings (but not billing).');

        Jetstream::role('member', 'Team Member', [
            'channel:connect',
            'post:create',
            'post:update',
            'post:approve',
            'analytics:view',
        ])->description('Creates and schedules posts within assigned workspaces.');

        Jetstream::role('approver', 'Approver', [
            'post:approve',
            'analytics:view',
        ])->description('Reviews and approves or rejects scheduled posts. Cannot create posts.');

        Jetstream::role('client', 'Client', [
            'post:create',
            'post:approve',
            'analytics:view',
        ])->description('A client limited to their own workspace: review, approve, and view analytics.');
    }
}
