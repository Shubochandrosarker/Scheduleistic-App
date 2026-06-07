<?php

namespace App\Services;

use App\Models\Post;
use App\Models\Workspace;
use Illuminate\Support\Carbon;

/**
 * Resolves the next free posting time for a workspace from its TimeSlot
 * templates, skipping any slot already taken by a scheduled post. This powers
 * the composer's "add to queue" so users don't pick an exact time each time.
 */
class QueueScheduler
{
    public function nextSlot(Workspace $workspace, ?Carbon $after = null): Carbon
    {
        $after = $after ?? Carbon::now($workspace->timezone ?? 'UTC');

        $slots = $workspace->timeSlots()->get();

        // No templates configured: fall back to one hour from now.
        if ($slots->isEmpty()) {
            return $this->avoidCollision($workspace, $after->copy()->addHour());
        }

        // Look ahead up to two weeks for the first free slot.
        for ($dayOffset = 0; $dayOffset <= 14; $dayOffset++) {
            $day = $after->copy()->addDays($dayOffset);
            $dow = (int) $day->dayOfWeek;

            $daySlots = $slots->where('day_of_week', $dow)
                ->sortBy('time')
                ->values();

            foreach ($daySlots as $slot) {
                [$h, $m] = explode(':', $slot->time);
                $candidate = $day->copy()->setTime((int) $h, (int) $m, 0);

                if ($candidate->lessThanOrEqualTo($after)) {
                    continue;
                }

                if (! $this->slotTaken($workspace, $candidate)) {
                    return $candidate;
                }
            }
        }

        // Everything full for two weeks: just stack after the last scheduled post.
        return $this->avoidCollision($workspace, $after->copy()->addHour());
    }

    protected function slotTaken(Workspace $workspace, Carbon $at): bool
    {
        return Post::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', Post::STATUS_SCHEDULED)
            ->whereBetween('scheduled_at', [$at->copy()->subMinutes(1), $at->copy()->addMinutes(1)])
            ->exists();
    }

    protected function avoidCollision(Workspace $workspace, Carbon $at): Carbon
    {
        while ($this->slotTaken($workspace, $at)) {
            $at = $at->copy()->addHour();
        }

        return $at;
    }
}
