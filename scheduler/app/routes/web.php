<?php

use App\Http\Controllers\ChannelController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    // Workspaces (clients).
    Route::get('/workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::post('/workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
    Route::delete('/workspaces/{workspace}', [WorkspaceController::class, 'destroy'])->name('workspaces.destroy');

    // Channels (connected social accounts) within a workspace.
    Route::get('/workspaces/{workspace}/channels', [ChannelController::class, 'index'])->name('workspaces.channels.index');
    Route::get('/workspaces/{workspace}/channels/connect/{provider}', [ChannelController::class, 'connect'])->name('workspaces.channels.connect');
    Route::delete('/workspaces/{workspace}/channels/{channel}', [ChannelController::class, 'destroy'])->name('workspaces.channels.destroy');

    // OAuth callback (provider redirects here).
    Route::get('/channels/callback/{provider}', [ChannelController::class, 'callback'])->name('channels.callback');

    // Posts: calendar + composer.
    Route::get('/posts', [PostController::class, 'index'])->name('posts.index');
    Route::get('/compose', [PostController::class, 'create'])->name('posts.create');
    Route::post('/posts', [PostController::class, 'store'])->name('posts.store');
});
