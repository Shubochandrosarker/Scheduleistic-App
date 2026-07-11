<?php

use App\Http\Controllers\Admin\OrganizationController;
use App\Http\Controllers\AiController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\BrandingController;
use App\Http\Controllers\BulkImportController;
use App\Http\Controllers\ChannelController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\TimeSlotController;
use App\Http\Controllers\TlsController;
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

        // Posts: calendar + composer.
        Route::get('/posts', [PostController::class, 'index'])->name('posts.index');
        Route::get('/compose', [PostController::class, 'create'])->name('posts.create');
        Route::post('/posts', [PostController::class, 'store'])->name('posts.store');

        // Approval workflow.
        Route::post('/posts/{post}/submit', [ApprovalController::class, 'submit'])->name('posts.submit');
        Route::post('/posts/{post}/decision', [ApprovalController::class, 'decide'])->name('posts.decision');

        // Collaboration.
        Route::post('/posts/{post}/comments', [CommentController::class, 'store'])->name('posts.comments.store');

        // Workspace members (team + client assignment).
        Route::post('/workspaces/{workspace}/members', [WorkspaceMemberController::class, 'store'])->name('workspaces.members.store');
        Route::delete('/workspaces/{workspace}/members/{user}', [WorkspaceMemberController::class, 'destroy'])->name('workspaces.members.destroy');

        // Queue time-slot templates.
        Route::post('/workspaces/{workspace}/time-slots', [TimeSlotController::class, 'store'])->name('workspaces.time-slots.store');
        Route::delete('/workspaces/{workspace}/time-slots/{timeSlot}', [TimeSlotController::class, 'destroy'])->name('workspaces.time-slots.destroy');

        // Bulk CSV import.
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

    // Stop impersonating (available to the impersonated session).
    Route::post('/admin/stop-impersonating', [OrganizationController::class, 'stopImpersonating'])->name('admin.stop-impersonating');

    // Super-admin control plane.
    Route::middleware('platform.admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/organizations', [OrganizationController::class, 'index'])->name('organizations.index');
        Route::post('/organizations/{organization}/suspend', [OrganizationController::class, 'suspend'])->name('organizations.suspend');
        Route::post('/organizations/{organization}/impersonate', [OrganizationController::class, 'impersonate'])->name('organizations.impersonate');
    });
});
