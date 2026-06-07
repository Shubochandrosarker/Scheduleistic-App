<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function __construct(private readonly AnalyticsService $analytics) {}

    public function index(Request $request): Response
    {
        $team = $request->user()->currentTeam;

        return Inertia::render('Analytics/Index', [
            'totals' => $this->analytics->totalsForTeam($team),
        ]);
    }
}
