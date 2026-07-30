<?php

namespace App\Http\Controllers;

use App\Jobs\DeliverWebhookJob;
use App\Models\AuditLog;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Support\SsrfGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Outbound webhook management.
 *
 * The plaintext secret is shown exactly once — at creation and after a
 * rotation — via a one-shot flash. It is encrypted at rest and there is no
 * endpoint that reveals it again.
 */
class WebhookEndpointController extends Controller
{
    public function index(Request $request): Response
    {
        $team = $request->user()->currentTeam;

        $endpoints = WebhookEndpoint::where('team_id', $team->id)
            ->withCount([
                'deliveries',
                'deliveries as failed_deliveries_count' => fn ($q) => $q->where('status', WebhookDelivery::STATUS_FAILED),
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'url', 'events', 'is_active', 'workspace_id', 'consecutive_failures', 'disabled_at', 'disabled_reason', 'secret_rotated_at']);

        return Inertia::render('Webhooks/Index', [
            'endpoints'  => $endpoints,
            'events'     => WebhookEndpoint::EVENTS,
            'workspaces' => $team->workspaces()->get(['id', 'name']),
            'deliveries' => WebhookDelivery::whereIn('webhook_endpoint_id', $endpoints->pluck('id'))
                ->latest()
                ->limit(50)
                ->get(['id', 'webhook_endpoint_id', 'event', 'event_id', 'status', 'response_status', 'attempts', 'duration_ms', 'created_at']),
            // Shown once, immediately after create/rotate.
            'newSecret' => $request->session()->get('webhook_secret'),
        ]);
    }

    public function store(Request $request, SsrfGuard $guard): RedirectResponse
    {
        $this->authorizeOwnerOrAdmin($request);

        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:80'],
            'url'          => ['required', 'url:https', 'max:2048', function ($attribute, $value, $fail) use ($guard) {
                if (! $guard->isAllowed($value)) {
                    $fail('That URL points at a private or reserved address.');
                }
            }],
            'events'       => ['required', 'array', 'min:1'],
            'events.*'     => [Rule::in(WebhookEndpoint::EVENTS)],
            'workspace_id' => ['nullable', 'integer', Rule::in($request->user()->visibleWorkspaceIds()->all())],
        ]);

        $secret = WebhookEndpoint::newSecret();

        $endpoint = WebhookEndpoint::create([
            ...$validated,
            'team_id' => $request->user()->currentTeam->id,
            'secret'  => $secret,
        ]);

        AuditLog::record('webhook.created', $endpoint, ['url' => $endpoint->url]);

        return back()
            ->with('status', 'webhook-created')
            ->with('webhook_secret', $secret);
    }

    public function update(Request $request, WebhookEndpoint $endpoint): RedirectResponse
    {
        $this->authorizeEndpoint($request, $endpoint);

        $validated = $request->validate([
            'name'      => ['sometimes', 'string', 'max:80'],
            'events'    => ['sometimes', 'array', 'min:1'],
            'events.*'  => [Rule::in(WebhookEndpoint::EVENTS)],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        // Re-enabling clears the automatic disable, otherwise the endpoint
        // would look active while still being skipped by the dispatcher.
        if (($validated['is_active'] ?? false) === true) {
            $validated += ['disabled_at' => null, 'disabled_reason' => null, 'consecutive_failures' => 0];
        }

        $endpoint->update($validated);

        AuditLog::record('webhook.updated', $endpoint);

        return back()->with('status', 'webhook-updated');
    }

    public function rotate(Request $request, WebhookEndpoint $endpoint): RedirectResponse
    {
        $this->authorizeEndpoint($request, $endpoint);

        $secret = $endpoint->rotateSecret();

        AuditLog::record('webhook.secret_rotated', $endpoint);

        return back()
            ->with('status', 'webhook-secret-rotated')
            ->with('webhook_secret', $secret);
    }

    /** Re-queue a delivery without re-running the action that produced it. */
    public function replay(Request $request, WebhookDelivery $delivery): RedirectResponse
    {
        $this->authorizeEndpoint($request, $delivery->endpoint);

        $replay = WebhookDelivery::create([
            'webhook_endpoint_id' => $delivery->webhook_endpoint_id,
            // A new idempotency key: this is a deliberate re-send, and the
            // receiver should not discard it as a duplicate of the original.
            'event_id'            => (string) \Illuminate\Support\Str::uuid(),
            'event'               => $delivery->event,
            'payload'             => $delivery->payload,
            'status'              => WebhookDelivery::STATUS_PENDING,
        ]);

        DeliverWebhookJob::dispatch($replay->id);

        return back()->with('status', 'webhook-replayed');
    }

    public function destroy(Request $request, WebhookEndpoint $endpoint): RedirectResponse
    {
        $this->authorizeEndpoint($request, $endpoint);

        AuditLog::record('webhook.deleted', $endpoint, ['url' => $endpoint->url]);

        $endpoint->delete();

        return back()->with('status', 'webhook-deleted');
    }

    protected function authorizeEndpoint(Request $request, ?WebhookEndpoint $endpoint): void
    {
        abort_unless($endpoint && $endpoint->team_id === $request->user()->currentTeam?->id, 403);

        $this->authorizeOwnerOrAdmin($request);
    }

    protected function authorizeOwnerOrAdmin(Request $request): void
    {
        $team = $request->user()->currentTeam;

        abort_unless(
            $team && ($request->user()->ownsTeam($team) || $request->user()->hasTeamRole($team, 'admin')),
            403,
            'Only owners and administrators can manage webhooks.',
        );
    }
}
