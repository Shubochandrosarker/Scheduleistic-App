<?php

namespace App\Social\Providers;

use App\Models\Channel;

/**
 * LinkedIn company/organization page publishing. Identical to the personal
 * driver except it posts as an organization URN. The connected organization
 * id is stored in the channel's meta during the connect flow.
 */
class LinkedInCompanyProvider extends LinkedInProvider
{
    public function key(): string
    {
        return 'linkedin_company';
    }

    public function label(): string
    {
        return 'LinkedIn (Company Page)';
    }

    protected function scopes(): array
    {
        return ['openid', 'profile', 'w_organization_social', 'r_organization_admin'];
    }

    protected function authorUrn(Channel $channel): string
    {
        $orgId = $channel->meta['organization_id'] ?? $channel->provider_account_id;

        return 'urn:li:organization:'.$orgId;
    }
}
