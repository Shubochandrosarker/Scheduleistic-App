<?php

use App\Http\Controllers\Admin\OrganizationController;
use App\Http\Controllers\Admin\OrganizationDetailController;
use App\Http\Controllers\Admin\OverviewController as AdminOverviewController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AiController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\BrandingController;
use App\Http\Controllers\BulkImportController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\ChannelController;
use App\Http\Controllers\ChannelHealthController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ContentPillarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\IdeaController;
use App\Http\Controllers\MediaAssetController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PlannerController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TimeSlotController;
use App\Http\Controllers\TlsController;
use App\Http\Controllers\WebhookEndpointController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\WorkspaceMemberController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Public endpoint for Caddy on-demand TLS (no auth): approves cert issuance
// only for the platform domain + verified tenant custom domains.
Route::get('/tls/check', [TlsController::class, 'check'])->name('tls.check');

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    // A suspended individual account (distinct from a suspended
    // organization) loses the whole authenticated app; only logout, which
    // Fortify registers outside this group, stays reachable.
    'user.active',
])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Feature routes are gated by organization status: a suspended org keeps
    // billing access (to reactivate) but loses the publishing tooling.
    Route::middleware('org.active')->group(function () {

        // Workspaces (clients).
        Route::get('/workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
        Route::post('/workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
        Route::delete('/workspaces/{workspace}', [WorkspaceController::class, 'destroy'])->name('workspaces.destroy');

        // Channels (connected social accounts) within a workspace.
        Route::get('/workspaces/{workspace}/channels', [ChannelController::class, 'index'])->name('workspaces.channels.index');
        Route::get('/workspaces/{workspace}/channels/connect/{provider}', [ChannelController::class, 'connect'])->name('workspaces.channels.connect');
        Route::post('/workspaces/{workspace}/channels/token/{provider}', [ChannelController::class, 'storeToken'])->name('workspaces.channels.token');
        Route::delete('/workspaces/{workspace}/channels/{channel}', [ChannelController::class, 'destroy'])->name('workspaces.channels.destroy');

        // OAuth callback (provider redirects here).
        Route::get('/channels/callback/{provider}', [ChannelController::class, 'callback'])->name('channels.callback');

        // Posts: composer + the legacy list (kept as a redirect target for old links).
        Route::get('/posts', [PostController::class, 'index'])->name('posts.index');
        Route::get('/compose', [PostController::class, 'create'])->name('posts.create');
        Route::post('/posts', [PostController::class, 'store'])->name('posts.store');

        // ── Planner ──────────────────────────────────────────────────────
        // The 2.0 calendar. Every view (month/week/agenda/list/grid) is the
        // same endpoint with a different `view` parameter, so filters survive
        // a view switch and the URL is always shareable.
        Route::get('/planner', [PlannerController::class, 'index'])->name('planner.index');
        Route::get('/planner/posts/{post}', [PlannerController::class, 'show'])->name('planner.posts.show');
        Route::patch('/planner/posts/{post}/schedule', [PlannerController::class, 'reschedule'])->name('planner.posts.reschedule');
        Route::post('/planner/bulk', [PlannerController::class, 'bulk'])->name('planner.bulk');

        // ── Campaigns, pillars, tags ─────────────────────────────────────
        Route::middleware('capability:campaigns')->group(function () {
            Route::get('/campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
            Route::post('/campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
            Route::put('/campaigns/{campaign}', [CampaignController::class, 'update'])->name('campaigns.update');
            Route::delete('/campaigns/{campaign}', [CampaignController::class, 'destroy'])->name('campaigns.destroy');
        });

        // Pillars and tags are free: they are how content gets organised at
        // all, and gating them would make the planner useless on the low tiers.
        Route::post('/pillars', [ContentPillarController::class, 'store'])->name('pillars.store');
        Route::put('/pillars/{pillar}', [ContentPillarController::class, 'update'])->name('pillars.update');
        Route::delete('/pillars/{pillar}', [ContentPillarController::class, 'destroy'])->name('pillars.destroy');

        Route::post('/tags', [TagController::class, 'store'])->name('tags.store');
        Route::delete('/tags/{tag}', [TagController::class, 'destroy'])->name('tags.destroy');

        // ── Ideas ────────────────────────────────────────────────────────
        Route::middleware('capability:ideas')->group(function () {
            Route::get('/ideas', [IdeaController::class, 'index'])->name('ideas.index');
            Route::post('/ideas', [IdeaController::class, 'store'])->name('ideas.store');
            Route::put('/ideas/{idea}', [IdeaController::class, 'update'])->name('ideas.update');
            Route::delete('/ideas/{idea}', [IdeaController::class, 'destroy'])->name('ideas.destroy');
            Route::post('/ideas/{idea}/convert', [IdeaController::class, 'convert'])->name('ideas.convert');
        });

        // ── Media library ────────────────────────────────────────────────
        Route::middleware('capability:media_library')->group(function () {
            Route::get('/media', [MediaAssetController::class, 'index'])->name('media.index');
            Route::post('/media', [MediaAssetController::class, 'store'])->name('media.store');
            Route::get('/media/{asset}/status', [MediaAssetController::class, 'status'])->name('media.status');
            Route::put('/media/{asset}', [MediaAssetController::class, 'update'])->name('media.update');
            Route::delete('/media/{asset}', [MediaAssetController::class, 'destroy'])->name('media.destroy');
            Route::post('/media/folders', [MediaAssetController::class, 'storeFolder'])->name('media.folders.store');
            Route::delete('/media/folders/{folder}', [MediaAssetController::class, 'destroyFolder'])->name('media.folders.destroy');
        });

        // ── Channel health ───────────────────────────────────────────────
        Route::get('/social-profiles/health', [ChannelHealthController::class, 'index'])->name('channels.health');
        Route::post('/social-profiles/health/refresh', [ChannelHealthController::class, 'refresh'])->name('channels.health.refresh');

        // Approval workflow.
        Route::post('/posts/{post}/submit', [ApprovalController::class, 'submit'])->name('posts.submit');
        Route::post('/posts/{post}/decision', [ApprovalController::class, 'decide'])->name('posts.decision');

        // Collaboration.
        Route::post('/posts/{post}/comments', [CommentController::class, 'store'])->name('posts.comments.store');

        // Workspace members (team + client assignment).
        Route::post('/workspaces/{workspace}/members', [WorkspaceMemberController::class, 'store'])->name('workspaces.members.store');
        Route::delete('/workspaces/{workspace}/members/{user}', [WorkspaceMemberController::class, 'destroy'])->name('workspaces.members.destroy');

        // Queue time-slot templates.
        Route::get('/workspaces/{workspace}/time-slots', [TimeSlotController::class, 'index'])->name('workspaces.time-slots.index');
        Route::post('/workspaces/{workspace}/time-slots', [TimeSlotController::class, 'store'])->name('workspaces.time-slots.store');
        Route::delete('/workspaces/{workspace}/time-slots/{timeSlot}', [TimeSlotController::class, 'destroy'])->name('workspaces.time-slots.destroy');

        // Bulk CSV import.
        Route::get('/workspaces/{workspace}/import', [BulkImportController::class, 'create'])->name('workspaces.import.create');
        Route::post('/workspaces/{workspace}/import', [BulkImportController::class, 'store'])->name('workspaces.import');

        // AI caption assistant (throttled to limit paid-API cost abuse).
        Route::post('/ai/generate', [AiController::class, 'generate'])
            ->middleware('throttle:20,1')
            ->name('ai.generate');

        // Post AI agents (Pro+): cleanup/rewrite, hashtag suggestion, quality check.
        Route::post('/posts/{post}/ai/rewrite', [AiController::class, 'rewrite'])
            ->middleware('throttle:20,1')
            ->name('posts.ai.rewrite');
        Route::post('/posts/{post}/ai/hashtags', [AiController::class, 'optimizeHashtags'])
            ->middleware('throttle:20,1')
            ->name('posts.ai.hashtags');
        Route::post('/posts/{post}/ai/quality', [AiController::class, 'qualityCheck'])
            ->middleware('throttle:20,1')
            ->name('posts.ai.quality');

        // Analytics dashboard.
        Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');

        // RSS / WordPress article feeds.
        Route::get('/workspaces/{workspace}/feeds', [FeedController::class, 'index'])->name('workspaces.feeds.index');
        Route::post('/workspaces/{workspace}/feeds', [FeedController::class, 'store'])->name('workspaces.feeds.store');
        Route::delete('/workspaces/{workspace}/feeds/{feed}', [FeedController::class, 'destroy'])->name('workspaces.feeds.destroy');

    }); // end org.active group

    // Billing (Stripe / Cashier) — per organization.
    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::post('/billing/checkout/{plan}', [BillingController::class, 'checkout'])->name('billing.checkout');
    Route::get('/billing/portal', [BillingController::class, 'portal'])->name('billing.portal');

    // White-label branding + custom domain.
    Route::get('/branding', [BrandingController::class, 'edit'])->name('branding.edit');
    Route::put('/branding', [BrandingController::class, 'update'])->name('branding.update');
    Route::put('/branding/domain', [BrandingController::class, 'updateDomain'])->name('branding.domain');
    Route::post('/branding/domain/verify', [BrandingController::class, 'verifyDomain'])->name('branding.domain.verify');

    // Notification centre + per-user delivery preferences. Deliberately
    // outside org.active: a suspended organization must still be able to read
    // why it was suspended.
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::put('/notifications/preferences', [NotificationController::class, 'updatePreferences'])->name('notifications.preferences');

    // Outbound webhooks (Agency and above).
    Route::middleware('capability:webhooks')->group(function () {
        Route::get('/webhooks', [WebhookEndpointController::class, 'index'])->name('webhooks.index');
        Route::post('/webhooks', [WebhookEndpointController::class, 'store'])->name('webhooks.store');
        Route::put('/webhooks/{endpoint}', [WebhookEndpointController::class, 'update'])->name('webhooks.update');
        Route::post('/webhooks/{endpoint}/rotate', [WebhookEndpointController::class, 'rotate'])->name('webhooks.rotate');
        Route::post('/webhook-deliveries/{delivery}/replay', [WebhookEndpointController::class, 'replay'])->name('webhooks.replay');
        Route::delete('/webhooks/{endpoint}', [WebhookEndpointController::class, 'destroy'])->name('webhooks.destroy');
    });

    // Stop impersonating (available to the impersonated session). Exempt from
    // user.active: if the impersonated account is suspended mid-session (by
    // another admin, or the same one), the admin must still be able to get
    // back to their own account rather than being locked out by the very
    // suspension they may have just applied.
    Route::post('/admin/stop-impersonating', [OrganizationController::class, 'stopImpersonating'])
        ->withoutMiddleware('user.active')
        ->name('admin.stop-impersonating');

    // Super-admin control plane.
    Route::middleware('platform.admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminOverviewController::class, 'index'])->name('overview');

        Route::get('/organizations', [OrganizationController::class, 'index'])->name('organizations.index');
        Route::get('/organizations/{organization}', [OrganizationDetailController::class, 'show'])->name('organizations.show');
        Route::post('/organizations/{organization}/suspend', [OrganizationController::class, 'suspend'])->name('organizations.suspend');

        // Impersonation is a loaded gun: require a fresh password confirmation
        // in addition to platform.admin before it can start.
        Route::post('/organizations/{organization}/impersonate', [OrganizationController::class, 'impersonate'])
            ->middleware('password.confirm')
            ->name('organizations.impersonate');

        // Plan overrides bypass Stripe entirely, so they get the same
        // password-confirmation bar as impersonation.
        Route::middleware('password.confirm')->group(function () {
            Route::post('/organizations/{organization}/plan-override', [OrganizationDetailController::class, 'updatePlanOverride'])
                ->name('organizations.plan-override.update');
            Route::delete('/organizations/{organization}/plan-override', [OrganizationDetailController::class, 'removePlanOverride'])
                ->name('organizations.plan-override.destroy');
        });
        Route::post('/organizations/{organization}/resync-plan', [OrganizationDetailController::class, 'resyncPlan'])
            ->name('organizations.resync-plan');
        Route::post('/organizations/{organization}/entitlements', [OrganizationDetailController::class, 'grantEntitlement'])
            ->name('organizations.entitlements.grant');
        Route::delete('/organizations/{organization}/entitlements', [OrganizationDetailController::class, 'clearEntitlement'])
            ->name('organizations.entitlements.clear');

        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::post('/users/{user}/suspend', [AdminUserController::class, 'suspend'])->name('users.suspend');
        Route::post('/users/{user}/reset-link', [AdminUserController::class, 'sendResetLink'])->name('users.reset-link');
        Route::post('/users/{user}/revoke-sessions', [AdminUserController::class, 'revokeSessions'])->name('users.revoke-sessions');
    });
});
