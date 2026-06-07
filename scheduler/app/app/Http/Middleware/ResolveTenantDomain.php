<?php

namespace App\Http\Middleware;

use App\Services\DomainVerificationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When a request arrives on a tenant's verified custom domain (white-label),
 * resolve that organization and stash it on the request so the UI — including
 * guest pages like login — renders with the tenant's branding.
 */
class ResolveTenantDomain
{
    public function __construct(private readonly DomainVerificationService $verifier) {}

    public function handle(Request $request, Closure $next): Response
    {
        $host    = strtolower($request->getHost());
        $appHost = strtolower((string) parse_url(config('app.url'), PHP_URL_HOST));

        if ($host !== $appHost) {
            $team = $this->verifier->teamForDomain($host);

            if ($team) {
                // Consumed by HandleInertiaRequests to theme guest + auth pages.
                $request->attributes->set('tenant_team', $team);
            }
        }

        return $next($request);
    }
}
