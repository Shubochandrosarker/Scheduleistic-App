<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesWorkspaceAccess;
use App\Models\TimeSlot;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TimeSlotController extends Controller
{
    use AuthorizesWorkspaceAccess;

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
