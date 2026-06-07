<?php

namespace App\Http\Controllers;

use App\Services\DomainVerificationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Endpoint for Caddy's on-demand TLS "ask": Caddy queries this before issuing a
 * Let's Encrypt certificate for an incoming hostname. We only approve the
 * platform's own domain and custom domains that a tenant has verified — this
 * prevents attackers from exhausting ACME rate limits with bogus hostnames.
 */
class TlsController extends Controller
{
    public function check(Request $request, DomainVerificationService $verifier): Response
    {
        $domain = strtolower(trim((string) $request->query('domain')));

        if ($domain === '') {
            return response('missing domain', 400);
        }

        $appHost = strtolower((string) parse_url(config('app.url'), PHP_URL_HOST));

        $allowed = $domain === $appHost || $verifier->teamForDomain($domain) !== null;

        return $allowed
            ? response('ok', 200)
            : response('unknown domain', 403);
    }
}
