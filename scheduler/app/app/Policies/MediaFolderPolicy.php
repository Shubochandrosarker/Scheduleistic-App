<?php

namespace App\Policies;

/**
 * Tenancy + permissions for MediaFolder. See WorkspaceScopedPolicy for the
 * two-step check every workspace-scoped model shares.
 */
class MediaFolderPolicy extends WorkspaceScopedPolicy
{
    protected string $createPermission = 'post:create';

    protected string $updatePermission = 'post:update';

    protected string $deletePermission = 'post:delete';
}
