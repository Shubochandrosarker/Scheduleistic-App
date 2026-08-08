<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminOverviewService;
use Inertia\Inertia;
use Inertia\Response;

class OverviewController extends Controller
{
    public function __construct(private readonly AdminOverviewService $overview) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Overview', [
            'stats' => $this->overview->stats(),
            'warnings' => $this->overview->warnings(),
            'recentSignups' => $this->overview->recentSignups(),
            'recentAuditEvents' => $this->overview->recentAuditEvents()->map(fn ($event) => [
                'id' => $event->id,
                'action' => $event->action,
                'actor' => $event->user?->only(['id', 'name', 'email']),
                'organization' => $event->team?->only(['id', 'name']),
                'created_at' => $event->created_at,
            ]),
        ]);
    }
}
