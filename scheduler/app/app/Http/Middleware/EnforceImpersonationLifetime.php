<?php

namespace App\Http\Middleware;

use App\Services\ImpersonationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force-ends an impersonation session that has run past its maximum lifetime,
 * so a platform admin who forgets to click "Stop impersonating" can't remain
 * logged in as a customer indefinitely.
 */
class EnforceImpersonationLifetime
{
    public function __construct(private readonly ImpersonationService $impersonation) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->impersonation->isImpersonating($request) && $this->impersonation->expired($request)) {
            $this->impersonation->stop($request, 'impersonation.expired');

            return redirect()->route('dashboard')->with('status', 'impersonation-expired');
        }

        return $next($request);
    }
}
