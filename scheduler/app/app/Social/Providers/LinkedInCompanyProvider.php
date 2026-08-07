<?php

namespace App\Social\Providers;

use App\Models\Channel;
use App\Social\Data\ConnectedAccount;
use App\Social\Exceptions\PublishException;
use App\Social\Providers\Concerns\SwitchesAvailableAccount;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * LinkedIn company/organization page publishing. Identical to the personal
 * driver except it posts as an organization URN. The connected organization
 * id is stored in the channel's meta during the connect flow.
 */
class LinkedInCompanyProvider extends LinkedInProvider
{
    use SwitchesAvailableAccount;

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

    /**
     * The personal driver's mapAccount() fetches /v2/userinfo — a person's
     * profile, not an organization. Connecting a company page needs its own
     * resolution: which organizations this member administers, since
     * nothing else ever supplied one (the previous behavior silently
     * treated the connecting *person's* id as the organization id, which it
     * essentially never is).
     */
    protected function mapAccount(array $tokenResponse): ConnectedAccount
    {
        $accessToken = (string) Arr::get($tokenResponse, 'access_token');
        $organizations = $this->listAdministeredOrganizations($accessToken);

        if ($organizations === []) {
            throw new PublishException(
                'This LinkedIn member does not administer any Company Page. You must be an '
                    .'administrator of at least one LinkedIn Page to connect it.',
                retryable: false,
            );
        }

        $chosen = $organizations[0];

        return new ConnectedAccount(
            providerAccountId: $chosen['id'],
            name: $chosen['name'],
            accessToken: $accessToken,
            refreshToken: Arr::get($tokenResponse, 'refresh_token'),
            expiresAt: isset($tokenResponse['expires_in'])
                ? Carbon::now()->addSeconds((int) $tokenResponse['expires_in'])
                : null,
            scopes: $this->scopes(),
            meta: [
                'organization_id' => $chosen['id'],
                // Every other organization this member administers, for the
                // channel-settings "switch page" control — a pure local
                // update, since re-listing organizations only ever needs
                // this same member's own token.
                'available_accounts' => $organizations,
            ],
        );
    }

    /**
     * Every LinkedIn organization this member has an approved administrator
     * role on.
     *
     * @return array<int, array{id: string, name: string}>
     */
    protected function listAdministeredOrganizations(string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->withHeaders(['X-Restli-Protocol-Version' => '2.0.0'])
            ->get('https://api.linkedin.com/v2/organizationAcls', [
                'q' => 'roleAssignee',
                'role' => 'ADMINISTRATOR',
                'state' => 'APPROVED',
                'projection' => '(elements*(organization,organization~(id,localizedName)))',
            ]);

        $elements = (array) Arr::get($response->json(), 'elements', []);

        return collect($elements)
            ->map(function ($element) {
                // Prefer the resolved projection's numeric id; fall back to
                // parsing it out of the bare URN reference so both response
                // shapes normalize to the same plain numeric id — mixing a
                // raw id with a full "urn:li:organization:..." string here
                // would double-prefix the URN authorUrn() builds below.
                $id = Arr::get($element, 'organization~.id')
                    ?: Str::afterLast((string) Arr::get($element, 'organization', ''), ':');

                return $id ? [
                    'id' => (string) $id,
                    'name' => (string) Arr::get($element, 'organization~.localizedName', 'LinkedIn Page'),
                ] : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    protected function authorUrn(Channel $channel): string
    {
        $orgId = $channel->meta['organization_id'] ?? $channel->provider_account_id;

        return 'urn:li:organization:'.$orgId;
    }

    public function selectAccount(Channel $channel, string $accountId): void
    {
        $this->applyAccountSelection($channel, $accountId, 'organization_id');
    }
}
