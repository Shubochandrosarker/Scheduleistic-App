<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesWorkspaceAccess;
use App\Models\TimeSlot;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TimeSlotController extends Controller
{
    use AuthorizesWorkspaceAccess;

    public function index(Request $request, Workspace $workspace): Response
    {
        $this->guardWorkspace($request, $workspace);

        return Inertia::render('TimeSlots/Index', [
            'workspace'  => $workspace,
            'timeSlots'  => $workspace->timeSlots()->orderBy('day_of_week')->orderBy('time')->get(),
        ]);
    }

    public function store(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->guardWorkspace($request, $workspace);

        $validated = $request->validate([
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'time'        => ['required', 'date_format:H:i'],
        ]);

        $workspace->timeSlots()->firstOrCreate($validated);

        return back()->with('status', 'slot-added');
    }

    public function destroy(Request $request, Workspace $workspace, TimeSlot $timeSlot): RedirectResponse
    {
        $this->guardWorkspace($request, $workspace);
        abort_unless($timeSlot->workspace_id === $workspace->id, 403);

        $timeSlot->delete();

        return back()->with('status', 'slot-removed');
    }
}
