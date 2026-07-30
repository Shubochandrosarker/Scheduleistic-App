<?php

namespace App\Policies;

/**
 * Tenancy + permissions for MediaAsset. See WorkspaceScopedPolicy for the
 * two-step check every workspace-scoped model shares.
 */
class MediaAssetPolicy extends WorkspaceScopedPolicy
{
    protected string $createPermission = 'post:create';

    protected string $updatePermission = 'post:update';

    protected string $deletePermission = 'post:delete';
}
