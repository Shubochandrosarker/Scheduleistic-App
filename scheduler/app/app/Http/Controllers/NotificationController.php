<?php

namespace App\Http\Controllers;

use App\Models\NotificationPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * In-app notification centre and per-user delivery preferences.
 */
class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->latest()
            ->paginate(30)
            ->through(fn ($n) => [
                'id'      => $n->id,
                'type'    => class_basename($n->type),
                'data'    => $n->data,
                'read'    => $n->read_at !== null,
                'created' => $n->created_at?->toIso8601String(),
            ]);

        $user->load('notificationPreferences');

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'unread'        => $user->unreadNotifications()->count(),
            'events'        => collect(NotificationPreference::EVENTS)
                ->map(fn (array $meta, string $key) => [
                    'key'         => $key,
                    'label'       => $meta[0],
                    'description' => $meta[1],
                    'preference'  => NotificationPreference::resolve($user, $key),
                ])->values(),
            'digests' => NotificationPreference::DIGESTS,
        ]);
    }

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        // Scoped to the user's own notifications: an id from another account
        // simply does not match, so this cannot be used to probe for others.
        $request->user()->notifications()->whereKey($notification)->update(['read_at' => now()]);

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', 'notifications-cleared');
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'preferences'          => ['required', 'array'],
            'preferences.*.event'  => ['required', Rule::in(array_keys(NotificationPreference::EVENTS))],
            'preferences.*.in_app' => ['required', 'boolean'],
            'preferences.*.email'  => ['required', 'boolean'],
            'preferences.*.push'   => ['nullable', 'boolean'],
            'preferences.*.digest' => ['required', Rule::in(NotificationPreference::DIGESTS)],
        ]);

        foreach ($validated['preferences'] as $preference) {
            NotificationPreference::updateOrCreate(
                ['user_id' => $request->user()->id, 'event' => $preference['event']],
                [
                    'in_app' => $preference['in_app'],
                    'email'  => $preference['email'],
                    'push'   => $preference['push'] ?? false,
                    'digest' => $preference['digest'],
                ],
            );
        }

        return back()->with('status', 'preferences-saved');
    }
}
