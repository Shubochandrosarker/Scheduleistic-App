<?php

namespace App\Services;

use App\Models\Team;
use Closure;

/**
 * Verifies that an organization owns the custom domain it configured, by
 * checking for a DNS TXT record containing the issued verification token.
 *
 * Expected record:  TXT  _socialistic.<custom_domain>  =  <custom_domain_token>
 *
 * The DNS resolver is injectable so the behaviour is unit-testable without
 * real network lookups.
 */
class DomainVerificationService
{
    /** @var Closure(string): array<int, string> */
    private Closure $resolver;

    /**
     * @param  (Closure(string): array<int, string>)|null  $resolver  hostname → list of TXT strings
     */
    public function __construct(?Closure $resolver = null)
    {
        $this->resolver = $resolver ?? function (string $host): array {
            $records = @dns_get_record($host, DNS_TXT) ?: [];

            return array_values(array_filter(array_map(
                fn ($r) => $r['txt'] ?? null,
                $records,
            )));
        };
    }

    public function recordName(string $domain): string
    {
        return '_socialistic.'.$domain;
    }

    /** Run the DNS check and persist verification state. Returns true if verified. */
    public function verify(Team $team): bool
    {
        if (! $team->custom_domain || ! $team->custom_domain_token) {
            return false;
        }

        $txts = ($this->resolver)($this->recordName($team->custom_domain));

        $verified = in_array($team->custom_domain_token, $txts, true);

        if ($verified && ! $team->customDomainVerified()) {
            $team->forceFill(['custom_domain_verified_at' => now()])->save();
        }

        return $verified;
    }

    /** Is this host a domain we're currently serving (verified) for some tenant? */
    public function teamForDomain(string $host): ?Team
    {
        return Team::query()
            ->where('custom_domain', $host)
            ->whereNotNull('custom_domain_verified_at')
            ->first();
    }
}
