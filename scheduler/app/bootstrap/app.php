<?php

use App\Http\Middleware\BlockHighRiskActionsDuringImpersonation;
use App\Http\Middleware\EnforceImpersonationLifetime;
use App\Http\Middleware\EnsureCapability;
use App\Http\Middleware\EnsureOrganizationActive;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureUserActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveTenantDomain;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(
            prepend: [
                // Resolve white-label tenant by custom domain before sharing branding.
                ResolveTenantDomain::class,
            ],
            append: [
                // Must run before Inertia shares `isImpersonating` — an expired
                // session should never render a page still claiming to be one.
                EnforceImpersonationLifetime::class,
                // Runs after the lifetime check so a just-auto-stopped session
                // is never mistakenly treated as still impersonating here.
                BlockHighRiskActionsDuringImpersonation::class,
                HandleInertiaRequests::class,
                AddLinkHeadersForPreloadedAssets::class,
            ],
        );

        // Behind the Caddy reverse proxy (TLS termination); trust its forwarded headers.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'platform.admin' => EnsurePlatformAdmin::class,
            'org.active' => EnsureOrganizationActive::class,
            'user.active' => EnsureUserActive::class,
            'capability' => EnsureCapability::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
